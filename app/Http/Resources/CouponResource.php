<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $c = $this->resource;

        return [
            'code' => $c->code,
            'description' => $c->description,
            'type' => $c->type,
            'value' => $c->value,
            'max_discount' => $c->max_discount,
            'min_pax' => $c->min_pax,
            'min_amount' => $c->min_amount,
            'applies_to' => $c->applies_to,
            'usage_limit' => $c->usage_limit,
            'used_count' => $c->used_count,
            'per_user_limit' => $c->per_user_limit,
            'starts_at' => $c->starts_at,
            'expires_at' => $c->expires_at,
            'is_active' => $c->is_active,
            'created_at' => $c->created_at,
            'updated_at' => $c->updated_at,
        ];
    }
}
