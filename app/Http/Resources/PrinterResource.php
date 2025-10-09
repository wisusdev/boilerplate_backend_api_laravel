<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PrinterResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'business_id' => $this->resource->business_id,
            'name' => $this->resource->name,
            'connection_type' => $this->resource->connection_type,
            'capability_profile' => $this->resource->capability_profile,
            'char_per_line' => $this->resource->char_per_line,
            'ip_address' => $this->resource->ip_address,
            'port' => $this->resource->port,
            'path' => $this->resource->path,
            'created_by' => $this->resource->created_by,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
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
            BusinessResource::make($this->resource->business)
        ];
    }

	protected function getRouteParameters(): array
	{
		return [
			'business' => $this->resource->business,
			'printer' => $this->resource
		];
	}
}
