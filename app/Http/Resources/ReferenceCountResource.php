<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferenceCountResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'ref_type' => $this->resource->ref_type,
            'ref_count' => $this->resource->ref_count,
            'business_id' => $this->resource->business_id,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    protected function getRouteParameters()
    {
        return ['business' => $this->resource->business, 'reference_count' => $this->resource];
    }
}
