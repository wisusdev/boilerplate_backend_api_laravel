<?php

namespace App\Http\Resources;

use App\Models\PaymentLink;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentLinkResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        /** @var PaymentLink $link */
        $link = $this->resource;
        $booking = $link->booking();
        $payment = $link->payment;

        return [
            'reference' => $link->reference,
            'provider' => $link->provider,
            'status' => $link->status,
            'url' => $link->url,
            // El host se manda aparte para poder enseñarle al cliente a dónde va
            // sin que el frontend tenga que trocear la URL.
            'host' => $link->host(),
            'amount' => $link->amount,
            'currency_code' => $link->currency_code,
            'expires_at' => $link->expires_at,
            'past_due' => $link->isPastDue(),
            'sent_at' => $link->sent_at,
            'reported_at' => $link->reported_at,
            'reported_ref' => $link->reported_ref,
            'has_proof' => $link->relationLoaded('media')
                ? $link->media->isNotEmpty()
                : $link->hasMedia('payment_proof'),

            'payment_id' => $link->payment_id,
            'payment_status' => $payment?->status,
            'paid_at' => $payment?->paid_at,
            'transaction_reference' => $payment?->transaction_reference,

            'booking_id' => $booking?->id,
            'booking_status' => $booking?->status,
            'booking_title' => $booking?->bookable?->title,
            'customer_name' => $booking?->user?->name,
            'customer_email' => $booking?->user?->email,

            'created_at' => $link->created_at,
            'updated_at' => $link->updated_at,
        ];
    }
}
