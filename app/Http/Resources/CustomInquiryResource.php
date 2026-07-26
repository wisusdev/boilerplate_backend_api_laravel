<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomInquiryResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $user = $this->resource->user; // puede ser null (lead anónimo desde la web)
        $userName = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : null;

        return [
            'user_id' => $this->resource->user_id,
            // Contacto: datos del formulario; si el lead es de un usuario registrado, fallback a sus datos.
            'name' => $this->resource->contact_name ?: ($userName ?: null),
            'email' => $this->resource->contact_email ?: ($user->email ?? null),
            'phone' => $this->resource->contact_phone ?: ($user->phone ?? null),
            'contact_name' => $this->resource->contact_name,
            'contact_email' => $this->resource->contact_email,
            'contact_phone' => $this->resource->contact_phone,
            'preferred_destinations' => $this->resource->preferred_destinations,
            'travel_start_date' => $this->resource->travel_start_date,
            'travel_end_date' => $this->resource->travel_end_date,
            'budget_min' => $this->resource->budget_min,
            'budget_max' => $this->resource->budget_max,
            'travelers_count' => $this->resource->travelers_count,
            'currency_code' => $this->resource->currency_code,
            'message' => $this->resource->message,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
