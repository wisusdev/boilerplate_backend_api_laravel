<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class ReportService
{
    public function overview(?string $startDate = null, ?string $endDate = null): array
    {
        $start = Carbon::parse($startDate ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endDate ?? now())->endOfDay();

        $tourBookings = Booking::query()
            ->where('booking_type', Booking::TYPE_TOUR)
            ->whereBetween('created_at', [$start, $end]);

        $transportBookings = Booking::query()
            ->where('booking_type', Booking::TYPE_TRANSPORT)
            ->whereBetween('created_at', [$start, $end]);

        return [
            'tour_bookings_count'       => (clone $tourBookings)->count(),
            'transport_bookings_count'  => (clone $transportBookings)->count(),
            'tour_revenue'              => (float) (clone $tourBookings)->where('status', 'confirmed')->sum('total_price'),
            'transport_revenue'         => (float) (clone $transportBookings)->where('status', 'confirmed')->sum('total_price'),
            'payments_total'            => (float) Payment::query()->whereBetween('created_at', [$start, $end])->where('status', 'paid')->sum('amount'),
            'pending_tour_bookings'     => (clone $tourBookings)->where('status', 'pending')->count(),
            'pending_transport_bookings' => (clone $transportBookings)->where('status', 'pending')->count(),
        ];
    }
}
