<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourAvailability;

class TourAvailabilityService
{
    public function availableCapacity(Tour $tour, string $date): int
    {
        $baseCapacity = $tour->max_capacity;

        $availability = TourAvailability::query()
            ->where('tour_id', $tour->id)
            ->whereDate('available_date', $date)
            ->first();

        if ($availability?->is_closed) {
            return 0;
        }

        if ($availability && $availability->capacity_override !== null) {
            $baseCapacity = (int) $availability->capacity_override;
        }

        $booked = Booking::query()
            ->where('bookable_type', Tour::class)
            ->where('bookable_id', $tour->id)
            ->whereDate('starts_at', $date)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->sum('party_size');

        return max($baseCapacity - (int) $booked, 0);
    }
}
