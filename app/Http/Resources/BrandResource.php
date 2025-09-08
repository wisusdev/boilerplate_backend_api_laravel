<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'business_id' => $this->resource->business_id,
            'created_by' => $this->resource->created_by,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'slug' => $this->resource->slug,
            'logo' => $this->resource->logo,
            'is_active' => (bool)$this->resource->is_active,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'deleted_at' => $this->resource->deleted_at
        ];
    }

    public function getRelationshipLinks(): array
    {
        $links = [];

        if ($this->resource->business_id) {
            $links['business'] = route('businesses.show', $this->resource->business_id);
        }

        return $links;
    }

    public function getIncludes(): array
    {
        return [
            BusinessResource::make($this->resource->business)
        ];
    }
}
