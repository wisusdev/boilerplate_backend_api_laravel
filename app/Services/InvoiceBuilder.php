<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Construye y actualiza facturas manuales.
 *
 * Los conceptos pueden venir del catálogo (un tour o un vehículo) o ser líneas
 * libres. En ambos casos la descripción y el precio se guardan como snapshot: la
 * factura emitida no cambia aunque después se retoque el catálogo o se borre el
 * producto.
 */
class InvoiceBuilder
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'booking_id' => null,
                'amount' => 0,
                'currency_code' => SiteSettings::currency(),
                'status' => $data['status'] ?? 'pending',
                'dte_status' => Invoice::DTE_NOT_GENERATED,
                'issued_at' => $data['issued_at'] ?? now(),
                'receptor_name' => $data['receptor_name'] ?? null,
                'receptor_document' => $data['receptor_document'] ?? null,
                'receptor_email' => $data['receptor_email'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items'] ?? []);

            return $invoice->fresh(['items']);
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update(array_filter([
                'status' => $data['status'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'receptor_name' => $data['receptor_name'] ?? null,
                'receptor_document' => $data['receptor_document'] ?? null,
                'receptor_email' => $data['receptor_email'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => $v !== null));

            // Los conceptos solo se tocan si el cliente los envía: así un PATCH
            // que solo cambia el receptor no borra las líneas.
            if (array_key_exists('items', $data)) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $data['items']);
            }

            return $invoice->fresh(['items']);
        });
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $orden => $linea) {
            [$descripcion, $precio, $tourId, $vehiculoId] = $this->resolveSource($linea);

            $cantidad = max((int) ($linea['quantity'] ?? 1), 1);
            $unitario = round((float) ($linea['unit_price'] ?? $precio), 2);

            $invoice->items()->create([
                'description' => $descripcion,
                'quantity' => $cantidad,
                'unit_price' => $unitario,
                // El total de la línea se calcula aquí; no se acepta del cliente.
                'total' => round($unitario * $cantidad, 2),
                'tour_id' => $tourId,
                'transport_vehicle_id' => $vehiculoId,
                'sort_order' => $orden,
            ]);
        }

        $invoice->recalculateTotal();
    }

    /**
     * Resuelve una línea del catálogo o una línea libre.
     *
     * @param  array<string,mixed>  $linea
     * @return array{0: string, 1: float, 2: ?int, 3: ?int}
     */
    private function resolveSource(array $linea): array
    {
        if (! empty($linea['tour_id'])) {
            $tour = Tour::findOrFail($linea['tour_id']);

            return [
                trim($linea['description'] ?? '') ?: $tour->title,
                (float) $tour->price,
                $tour->id,
                null,
            ];
        }

        if (! empty($linea['transport_vehicle_id'])) {
            $vehiculo = TransportVehicle::findOrFail($linea['transport_vehicle_id']);

            return [
                trim($linea['description'] ?? '') ?: $vehiculo->title,
                (float) ($vehiculo->daily_rate ?? $vehiculo->hourly_rate ?? 0),
                null,
                $vehiculo->id,
            ];
        }

        return [trim($linea['description'] ?? '') ?: 'Concepto', 0.0, null, null];
    }
}
