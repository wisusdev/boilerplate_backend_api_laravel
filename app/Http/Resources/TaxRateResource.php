<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateResource extends JsonResource
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
			'business_id' => $this->resource->business_id,
			'created_by' => $this->resource->created_by,
			'name' => $this->resource->name,
			'amount' => (float)$this->resource->amount,
			'is_tax_group' => (bool)$this->resource->is_tax_group,
			'for_tax_group' => (bool)$this->resource->for_tax_group,
			'created_at' => $this->resource->created_at?->toISOString(),
			'updated_at' => $this->resource->updated_at?->toISOString(),
			'deleted_at' => $this->resource->deleted_at?->toISOString()
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
			'tax_rate' => $this->resource
		];
	}
}
