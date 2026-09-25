<?php

namespace App\Services\Dte;

use App\Models\CreditNote;
use App\Models\DteDocument;
use App\Models\Invoice;
use App\Services\DteService;
use Illuminate\Support\Facades\DB;

/**
 * Notas de crédito y de débito sobre un CCF sellado.
 *
 * Reglas:
 *  - solo ajustan un CCF (03) con sello; una Factura de consumidor final no
 *    se ajusta con notas, se invalida;
 *  - lo acreditado por notas vigentes (sin IVA) no supera el CCF más lo
 *    cargado por notas de débito vigentes. Se comprueba con la factura
 *    bloqueada para que dos notas simultáneas no pasen ambas;
 *  - la nota de débito no tiene tope.
 *
 * Aquí solo se guarda la nota; quien la crea emite su DTE a continuación
 * (DteService::processCreditNote). Si el DTE no sale (datos, MH), la nota
 * queda y se reintenta.
 */
class CreditNoteService
{
    /** Estados de una nota que ya cuentan (o van a contar) contra el CCF. */
    private const VIGENTES_EXCLUIDOS = [Invoice::DTE_REJECTED, Invoice::DTE_INVALIDATED];

    /**
     * @param  array{kind: string, motivo: string, items: list<array{description: string, quantity: int, unit_price: float|string}>}  $data
     *
     * @throws DteException
     */
    public function create(Invoice $invoice, array $data, ?string $userId = null): CreditNote
    {
        // Sin DTE no hay nota válida: no se guarda una que no se puede emitir.
        DteConfig::assertEnabled();
        $ccf = $invoice->dteDocuments()->where('estado', DteDocument::TRANSMITTED)->latest('id')->first();
        if (! $ccf || $ccf->tipo_dte !== FacturaBuilder::TIPO_CCF) {
            throw new DteException('Las notas de crédito y débito solo ajustan un comprobante de crédito fiscal con sello. Una factura de consumidor final se corrige invalidándola.');
        }

        $kind = $data['kind'] === CreditNote::DEBIT ? CreditNote::DEBIT : CreditNote::CREDIT;
        $lineas = [];
        $subtotalCents = $ivaCents = 0;
        foreach (array_values($data['items']) as $i => $l) {
            $cantidad = max((int) $l['quantity'], 1);
            $totalCents = DteParts::cents($l['unit_price']) * $cantidad;
            $lineas[] = [
                'description' => trim($l['description']),
                'quantity' => $cantidad,
                'unit_price' => round((float) $l['unit_price'], 2),
                'total' => $totalCents / 100,
                'sort_order' => $i,
            ];
            $subtotalCents += $totalCents;
            $ivaCents += (int) round($totalCents * DteParts::IVA_RATE);
        }

        $note = DB::transaction(function () use ($invoice, $ccf, $kind, $data, $lineas, $subtotalCents, $ivaCents, $userId) {
            Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            if ($kind === CreditNote::CREDIT) {
                $vigentes = fn (string $k) => DteParts::cents(
                    $invoice->creditNotes()->where('kind', $k)->whereNotIn('dte_status', self::VIGENTES_EXCLUIDOS)->sum('subtotal')
                );
                $tope = DteParts::cents($ccf->document()['resumen']['totalGravada'] ?? 0) + $vigentes(CreditNote::DEBIT);
                $acreditado = $vigentes(CreditNote::CREDIT);

                if ($acreditado + $subtotalCents > $tope) {
                    throw new DteException(sprintf(
                        'La nota ($%s) más lo ya acreditado ($%s) supera el valor del CCF sin IVA ($%s).',
                        number_format($subtotalCents / 100, 2), number_format($acreditado / 100, 2), number_format($tope / 100, 2),
                    ));
                }
            }

            // Número interno propio por tipo: NC-00001, ND-00001.
            $numero = DteService::nextNumber('IN', $kind === CreditNote::DEBIT ? 'ND' : 'NC');
            $note = $invoice->creditNotes()->create([
                'kind' => $kind,
                'number' => sprintf('%s-%05d', $kind === CreditNote::DEBIT ? 'ND' : 'NC', $numero),
                'motivo' => trim($data['motivo']),
                'subtotal' => $subtotalCents / 100,
                'iva' => $ivaCents / 100,
                'total' => ($subtotalCents + $ivaCents) / 100,
                'created_by' => $userId,
            ]);
            $note->items()->createMany($lineas);

            return $note;
        });

        return $note;
    }
}
