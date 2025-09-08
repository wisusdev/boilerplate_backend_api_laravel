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
