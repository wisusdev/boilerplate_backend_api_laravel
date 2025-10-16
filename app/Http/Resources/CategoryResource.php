<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
	use JsonApiResource;

	/**
	 * Transform the resource into an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toJsonApi(): array
	{
		return [
			'name' => $this->resource->name,
			'business_id' => $this->resource->business_id,
			'parent_id' => $this->resource->parent_id,
			'is_active' => (bool)$this->resource->is_active,
			'short_code' => $this->resource->short_code,
			'created_by' => $this->resource->created_by,
			'category_type' => $this->resource->category_type,
			'description' => $this->resource->description,
			'slug' => $this->resource->slug,
			'deleted_at' => $this->resource->deleted_at?->toISOString(),
			'created_at' => $this->resource->created_at?->toISOString(),
			'updated_at' => $this->resource->updated_at?->toISOString(),
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
			$this->whenLoaded('business', BusinessResource::make($this->resource->business)),
		];
	}

	protected function getRouteParameters(): array
	{
		return [
			'business' => $this->resource->business,
			'category' => $this->resource
		];
	}
}
