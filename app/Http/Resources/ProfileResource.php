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
                'avatar' => $this->resource->avatar ? asset('storage'.$this->resource->avatar) : null,
                'language' => $this->resource->language,
                'phone' => $this->resource->phone,
                'phone_secondary' => $this->resource->phone_secondary,
                'email_verified_at' => $this->resource->email_verified_at,
                // Los mismos que da el login: el panel los refresca al cargar para
                // que un permiso nuevo (o retirado) no espere a volver a entrar.
                'roles' => $this->resource->getRoleNames()->values(),
                'permissions' => $this->resource->getAllPermissions()->pluck('name')->values(),
            ],
        ];
    }
}
