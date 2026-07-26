<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriberResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'email' => $this->resource->email,
            'name' => $this->resource->name,
            'source' => $this->resource->source,
            'locale' => $this->resource->locale,
            'status' => $this->resource->status,
            'unsubscribed_at' => $this->resource->unsubscribed_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
