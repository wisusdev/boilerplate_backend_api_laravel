<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\Tour;
use App\Services\TourAvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TourBookingHandler implements BookingHandlerInterface
{
    public function __construct(private readonly TourAvailabilityService $tourAvailabilityService)
    {
    }

    public function validate(array $data): void
    {
        $tour = Tour::query()
            ->whereKey($data['tour_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $remainingCapacity = $this->tourAvailabilityService->availableCapacity($tour, $data['booking_date']);
        $requestedPax = (int) $data['pax_count'];

        if ($requestedPax > $remainingCapacity) {
            throw ValidationException::withMessages([
                'data.attributes.pax_count' => 'The selected tour does not have enough capacity for the requested date.',
            ]);
        }

        $appSettings = json_decode(optional(Setting::where('key', 'app')->first())->value ?? '{}', true);
        $maxPerDay = isset($appSettings['max_daily_bookings']) ? (int) $appSettings['max_daily_bookings'] : 0;

        if ($maxPerDay > 0) {
            $bookingsToday = Booking::query()
                ->where('booking_type', Booking::TYPE_TOUR)
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

        return [
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'starts_at'     => Carbon::parse($data['booking_date'])->startOfDay(),
            'ends_at'       => null,
            'party_size'    => $requestedPax,
            'total_price'   => round((float) $tour->price * $requestedPax, 2),
            'currency_code' => $tour->currency_code ?? 'USD',
            'notes'         => $data['notes'] ?? null,
        ];
    }
}
