<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'profiles',
            'id' => (string) $this->resource->getRouteKey(),
            'attributes' => [
                'first_name' => $this->resource->first_name,
                'last_name' => $this->resource->last_name,
                'username' => $this->resource->username,
                'email' => $this->resource->email,
                'avatar' => $this->resource->avatar ? asset('storage' . $this->resource->avatar) : null,
                'language' => $this->resource->language,
            ]
        ];
    }
}
