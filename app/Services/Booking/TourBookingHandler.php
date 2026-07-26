<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\Tour;
use App\Services\TourAvailabilityService;
use App\Support\SiteSettings;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TourBookingHandler implements BookingHandlerInterface
{
    public function __construct(private readonly TourAvailabilityService $tourAvailabilityService) {}

    public function validate(array $data): void
    {
        $tour = Tour::query()
            ->whereKey($data['tour_id'])
            ->lockForUpdate()
            ->firstOrFail();

        // Antelación mínima GLOBAL: la fecha debe estar a al menos N días de hoy.
        $minAdvanceDays = SiteSettings::minAdvanceDays();
        if ($minAdvanceDays > 0) {
            $minDate = Carbon::today()->addDays($minAdvanceDays);
            if (Carbon::parse($data['booking_date'])->lt($minDate)) {
                throw ValidationException::withMessages([
                    'data.attributes.booking_date' => "Se requiere reservar con al menos {$minAdvanceDays} día(s) de antelación.",
                ]);
            }
        }

        $remainingCapacity = $this->tourAvailabilityService->availableCapacity($tour, $data['booking_date']);
        $requestedPax = (int) $data['pax_count'];

        if ($requestedPax > $remainingCapacity) {
            throw ValidationException::withMessages([
                'data.attributes.pax_count' => 'The selected tour does not have enough capacity for the requested date.',
            ]);
        }

        // Opción de vehículo (opcional): el índice debe existir entre las opciones del tour.
        if (isset($data['upgrade_option_index']) && $data['upgrade_option_index'] !== null && $data['upgrade_option_index'] !== '') {
            $this->resolveVehicleOption($tour, (int) $data['upgrade_option_index']);
        }

        $appSettings = json_decode(optional(Setting::where('key', 'app')->first())->value ?? '{}', true);
        $maxPerDay = isset($appSettings['max_daily_bookings']) ? (int) $appSettings['max_daily_bookings'] : 0;

        if ($maxPerDay > 0) {
            $bookingsToday = Booking::query()
                ->where('bookable_type', Booking::bookableClassFor(Booking::TYPE_TOUR))
                ->whereDate('starts_at', $data['booking_date'])
                ->whereNotIn('status', [Booking::STATUS_CANCELLED])
                ->count();

            if ($bookingsToday >= $maxPerDay) {
                throw ValidationException::withMessages([
                    'data.attributes.booking_date' => "The maximum number of bookings allowed for this date ({$maxPerDay}) has been reached.",
                ]);
            }
        }
    }

    public function prepare(array $data): array
    {
        $tour = Tour::findOrFail($data['tour_id']);
        $requestedPax = (int) $data['pax_count'];

        // Precio por persona con oferta (si aplica) y descuento del tramo escalonado
        // correspondiente al tamaño del grupo.
        $unitPrice = $tour->unitPriceFor($requestedPax);

        $subtotal = round($unitPrice * $requestedPax, 2);

        [$feesTotal, $feesSnapshot] = $this->resolveServiceFees(
            $tour,
            $data['service_fees'] ?? [],
            $requestedPax
        );

        // Opción de vehículo elegida: cargo adicional fijo (precio definido por el admin)
        // que no altera el precio base. Se referencia el vehículo del catálogo.
        $upgradeVehicleId = null;
        $upgradeLabel = null;
        $upgradeSurcharge = null;
        if (isset($data['upgrade_option_index']) && $data['upgrade_option_index'] !== null && $data['upgrade_option_index'] !== '') {
            $option = $this->resolveVehicleOption($tour, (int) $data['upgrade_option_index']);
            $upgradeVehicleId = $option['vehicle_id'];
            $upgradeLabel = $option['name'];
            $upgradeSurcharge = round((float) $option['surcharge'], 2);
        }

        return [
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'upgrade_vehicle_id' => $upgradeVehicleId,
            'upgrade_label' => $upgradeLabel,
            'starts_at' => Carbon::parse($data['booking_date'])->startOfDay(),
            'ends_at' => null,
            'party_size' => $requestedPax,
            'total_price' => round($subtotal + $feesTotal + (float) $upgradeSurcharge, 2),
            'service_fees' => $feesSnapshot ?: null,
            'upgrade_surcharge' => $upgradeSurcharge,
            'currency_code' => SiteSettings::currency(),
            'notes' => $data['notes'] ?? null,
            'pickup_address' => $data['pickup_address'] ?? null,
            'pickup_lat' => $data['pickup_lat'] ?? null,
            'pickup_lng' => $data['pickup_lng'] ?? null,
        ];
    }

    /**
     * Resuelve la opción de vehículo (configurada por tour) según su índice.
     * Lanza ValidationException si el índice no corresponde a una opción válida.
     *
     * @return array{vehicle_id: int|null, name: string, surcharge: float}
     */
    private function resolveVehicleOption(Tour $tour, int $index): array
    {
        $options = $tour->vehicleOptionsList();

        if (! isset($options[$index])) {
            throw ValidationException::withMessages([
                'data.attributes.upgrade_option_index' => 'La opción de vehículo seleccionada no es válida para este tour.',
            ]);
        }

        return $options[$index];
    }

    /**
     * Resuelve los servicios extra seleccionados contra la definición del tour.
     * Devuelve [total, snapshot] donde snapshot es la lista cobrada realmente.
     *
     * @param  array<int, int>  $selected  índices de tour.service_fees
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function resolveServiceFees(Tour $tour, array $selected, int $pax): array
    {
        $available = is_array($tour->service_fees) ? $tour->service_fees : [];
        $total = 0.0;
        $snapshot = [];

        $indices = array_values(array_unique(array_map('intval', $selected)));

        foreach ($indices as $idx) {
            if (! isset($available[$idx]) || ! is_array($available[$idx])) {
                continue;
            }

            $fee = $available[$idx];
            $amount = round((float) ($fee['amount'] ?? 0), 2);
            $calc = ($fee['calc'] ?? 'fixed') === 'per_person' ? 'per_person' : 'fixed';
            $lineTotal = round($calc === 'per_person' ? $amount * $pax : $amount, 2);

            $total += $lineTotal;
            $snapshot[] = [
                'name' => (string) ($fee['name'] ?? 'Servicio'),
                'amount' => $amount,
                'calc' => $calc,
                'total' => $lineTotal,
            ];
        }

        return [round($total, 2), $snapshot];
    }
}
