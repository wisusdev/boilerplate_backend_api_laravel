<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'users',
            'id' => (string) $this->resource->getRouteKey(),
            'attributes' => [
                'username' => $this->resource->username,
                'first_name' => $this->resource->first_name,
                'last_name' => $this->resource->last_name,
                'email' => $this->resource->email,
                'email_verified_at' => $this->resource->email_verified_at,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ]
        ];
    }
}
