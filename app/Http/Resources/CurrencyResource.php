<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'symbol' => $this->resource->symbol,
            'rate_to_usd' => $this->resource->rate_to_usd,
            'is_default' => $this->resource->is_default,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
