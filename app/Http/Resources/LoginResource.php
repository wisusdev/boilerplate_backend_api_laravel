<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'users',
            'id' => (string) $this->resource->user->getRouteKey(),
            'attributes' => [
                'user' => [
                    'first_name' => $this->resource->user->first_name,
                    'last_name' => $this->resource->user->last_name,
                    'username' => $this->resource->user->username,
                    'email' => $this->resource->user->email,
                    'avatar' => $this->resource->user->avatar ? asset('storage' . $this->resource->user->avatar) : null,
                    'language' => $this->resource->user->language,
                ],
                'token' => $this->resource->token,
                'token_type' => $this->resource->token_type,
                'expires_at' => $this->resource->expires_at,
            ]
        ];
    }
}
