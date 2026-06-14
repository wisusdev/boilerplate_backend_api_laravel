<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $inv = $this->resource;
        return [
            'booking_id'           => $inv->booking_id,
            'amount'               => $inv->amount,
            'status'               => $inv->status,
            'issued_at'            => $inv->issued_at,
            // DTE
            'dte_type'             => $inv->dte_type,
            'dte_number'           => $inv->dte_number,
            'dte_generation_code'  => $inv->dte_generation_code,
            'dte_seal'             => $inv->dte_seal,
            'dte_code'             => $inv->dte_code ?? $inv->dte_seal, // backward compat
            'dte_status'           => $inv->dte_status,
            'dte_environment'      => $inv->dte_environment,
            'dte_submitted_at'     => $inv->dte_submitted_at,
            'dte_accepted_at'      => $inv->dte_accepted_at,
            // Receptor snapshot
            'receptor_name'        => $inv->receptor_name,
            'receptor_document'    => $inv->receptor_document,
            'receptor_email'       => $inv->receptor_email,
            // Booking context (if loaded)
            'booking_type'         => $inv->relationLoaded('booking') ? $inv->booking?->booking_type : null,
            'tour_title'           => $inv->relationLoaded('booking') && $inv->booking?->booking_type === 'tour'
                ? $inv->booking->bookable?->title : null,
            'vehicle_title'        => $inv->relationLoaded('booking') && $inv->booking?->booking_type === 'transport'
                ? $inv->booking->bookable?->title : null,
            // MH response (admin only or for error debugging)
            'mh_response'          => $inv->mh_response,
            'created_at'           => $inv->created_at,
            'updated_at'           => $inv->updated_at,
        ];
    }
}
