<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'roles',
            'id' => (string) $this->resource->getRouteKey(),
            'name' => $this->resource->name,
            'permissions' => $this->resource->permissions->map(function ($permission) {
                return [
                    'name' => $permission->name,
                ];
            }),
        ];
    }
}
