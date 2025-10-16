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
		return [
			[
				'name' => 'business',
				'route' => 'businesses.show',
				'params' => $this->resource->business
			]
		];
	}

    public function getIncludes(): array
    {
        return [
	        $this->whenLoaded('business', BusinessResource::make($this->resource->business))
        ];
    }

	protected function getRouteParameters(): array
	{
		return [
			'business' => $this->resource->business,
			'brand' => $this->resource
		];
	}
}
