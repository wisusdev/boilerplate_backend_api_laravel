<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\TransportVehicle;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TransportBookingHandler implements BookingHandlerInterface
{
    public function validate(array $data): void
    {
        $vehicle = TransportVehicle::query()
            ->whereKey($data['transport_vehicle_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $overlapping = Booking::query()
            ->where('bookable_type', TransportVehicle::class)
            ->where('bookable_id', $vehicle->id)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->where('starts_at', '<', $data['dropoff_at'])
            ->where('ends_at', '>', $data['pickup_at'])
            ->lockForUpdate()
            ->count();

        if ($overlapping > 0) {
            throw ValidationException::withMessages([
                'data.attributes.pickup_at' => 'The selected vehicle is not available for the requested schedule.',
            ]);
        }
    }

    public function prepare(array $data): array
    {
        $vehicle = TransportVehicle::findOrFail($data['transport_vehicle_id']);
        $quantity = (int) ($data['quantity'] ?? 1);
        $totalPrice = $this->calculatePrice(
            $vehicle,
            $data['rental_type'],
            $data['pickup_at'],
            $data['dropoff_at'],
            $quantity
        );

        return [
            'bookable_type' => TransportVehicle::class,
            'bookable_id'   => $vehicle->id,
            'starts_at'     => $data['pickup_at'],
            'ends_at'       => $data['dropoff_at'],
            'party_size'    => $quantity,
            'total_price'   => $totalPrice,
            'currency_code' => $data['currency_code'] ?? $vehicle->currency_code,
            'notes'         => $data['notes'] ?? null,
            'details'       => [
                'pickup_location'  => $data['pickup_location'],
                'dropoff_location' => $data['dropoff_location'],
                'rental_type'      => $data['rental_type'],
            ],
        ];
    }

    private function calculatePrice(TransportVehicle $vehicle, string $rentalType, string $pickupAt, string $dropoffAt, int $quantity): float
    {
        $pickup = Carbon::parse($pickupAt);
        $dropoff = Carbon::parse($dropoffAt);
        $hours = max($pickup->diffInHours($dropoff, false), 1);
        $days = max((int) ceil($pickup->diffInHours($dropoff) / 24), 1);

        // Aplica la tarifa de oferta cuando existe y es menor que la tarifa base.
        $effectiveRate = function (?float $rate, ?float $saleRate): float {
            $rate = (float) ($rate ?? 0);
            if ($saleRate !== null && (float) $saleRate < $rate) {
                return (float) $saleRate;
            }
            return $rate;
        };

        $base = $rentalType === 'daily'
            ? $effectiveRate($vehicle->daily_rate, $vehicle->sale_daily_rate) * $days
            : $effectiveRate($vehicle->hourly_rate, $vehicle->sale_hourly_rate) * $hours;

        return round($base * max($quantity, 1), 2);
    }
}
