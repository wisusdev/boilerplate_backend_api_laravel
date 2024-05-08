<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'devices',
            'id' => (string) $this->resource->id,
            'attributes' => [
                'login_at' => $this->resource->login_at,
                'browser' => $this->resource->browser,
                'os' => $this->resource->os,
                'ip' => $this->resource->ip,
                'country' => $this->resource->country,
            ],
        ];
    }
}
