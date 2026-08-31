<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'gateway' => $this->resource->gateway,
            'method' => $this->resource->method,
            'amount' => $this->resource->amount,
            'currency_code' => $this->resource->currency_code,
            'status' => $this->resource->status,
            'transaction_reference' => $this->resource->transaction_reference,
            'payload' => $this->resource->payload,
            'paid_at' => $this->resource->paid_at,
            // Sin esto el cliente no sabía a qué reserva pertenece el pago y no
            // había forma de enlazar su comprobante desde "Pagos & Facturas".
            'booking_id' => $this->resource->payable_type === Booking::class
                ? $this->resource->payable_id
                : null,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
