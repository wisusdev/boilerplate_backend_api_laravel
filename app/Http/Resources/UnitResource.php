<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
	use JsonApiResource;

	public function toJsonApi(): array
	{
		return [
			'business_id' => $this->resource->business_id,
			'created_by' => $this->resource->created_by,
			'is_active' => (bool)$this->resource->is_active,
			'name' => $this->resource->name,
			'short_name' => $this->resource->short_name,
			'allow_decimal' => (bool)$this->resource->allow_decimal,
			'base_unit_id' => $this->resource->base_unit_id,
			'base_unit_multiplier' => $this->resource->base_unit_multiplier !== null ? (float)$this->resource->base_unit_multiplier : null,
			'created_at' => $this->resource->created_at?->toISOString(),
			'updated_at' => $this->resource->updated_at?->toISOString(),
			'deleted_at' => $this->resource->deleted_at?->toISOString(),
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
			'unit' => $this->resource
		];
	}
}
