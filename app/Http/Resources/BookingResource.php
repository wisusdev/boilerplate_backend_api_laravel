<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $booking  = $this->resource;
        $user     = $booking->relationLoaded('user')          ? $booking->user          : null;
        $bookable = $booking->relationLoaded('bookable')      ? $booking->bookable      : null;
        $payment  = $booking->relationLoaded('latestPayment') ? $booking->latestPayment : null;
        $detail   = $booking->relationLoaded('transportDetail') ? $booking->transportDetail : null;

        $isTour      = $booking->booking_type === Booking::TYPE_TOUR;
        $isTransport = $booking->booking_type === Booking::TYPE_TRANSPORT;

        return [
            'booking_type'         => $booking->booking_type,

            // User info
            'user_id'              => $booking->user_id,
            'user_name'            => $user?->name,
            'user_email'           => $user?->email,
            'user_phone'           => $user?->phone ?? null,
            'user_phone_secondary' => $user?->phone_secondary ?? null,

            // Tour-specific (null for other types)
            'tour_id'              => $isTour ? $booking->bookable_id : null,
            'tour_title'           => $isTour ? $bookable?->title : null,
            'tour_category'        => $isTour ? $bookable?->category : null,
            'booking_date'         => $isTour ? $booking->starts_at?->toDateString() : null,
            'pax_count'            => $isTour ? $booking->party_size : null,

            // Transport-specific (null for other types)
            'transport_vehicle_id' => $isTransport ? $booking->bookable_id : null,
            'vehicle_title'        => $isTransport ? $bookable?->title : null,
            'pickup_at'            => $isTransport ? $booking->starts_at : null,
            'dropoff_at'           => $isTransport ? $booking->ends_at : null,
            'pickup_location'      => $isTransport ? $detail?->pickup_location : null,
            'dropoff_location'     => $isTransport ? $detail?->dropoff_location : null,
            'rental_type'          => $isTransport ? $detail?->rental_type : null,
            'quantity'             => $isTransport ? $booking->party_size : null,

            // Common fields
            'starts_at'            => $booking->starts_at,
            'ends_at'              => $booking->ends_at,
            'party_size'           => $booking->party_size,
            'total_price'          => $booking->total_price,
            'currency_code'        => $booking->currency_code,
            'status'               => $booking->status,
            'notes'                => $booking->notes,

            // Payment snapshot
            'payment_gateway'      => $payment?->gateway,
            'payment_method'       => $payment?->method,
            'payment_amount'       => $payment?->amount,
            'payment_status'       => $payment?->status,
            'payment_reference'    => $payment?->transaction_reference,

            'created_at'           => $booking->created_at,
            'updated_at'           => $booking->updated_at,
        ];
    }
}
