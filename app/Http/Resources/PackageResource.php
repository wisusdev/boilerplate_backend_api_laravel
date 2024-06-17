<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'packages',
            'id' => (string) $this->resource->getRouteKey(),
            'attributes' => [
                'name' => $this->resource->name,
                'description' => $this->resource->description,
                'max_users' => $this->resource->max_users,
                'interval' => $this->resource->interval,
                'interval_count' => $this->resource->interval_count,
                'price' => $this->resource->price,
                'trial_days' => $this->resource->trial_days,
                'active' => $this->resource->active,
                'created_by' => $this->resource->created_by,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ],
        ];
    }
}
