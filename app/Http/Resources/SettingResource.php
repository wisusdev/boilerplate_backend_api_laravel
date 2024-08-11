<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
			'type' => $this->resource->key,
			'id' => (string) $this->resource->getRouteKey(),
			'attributes' => json_decode($this->resource->value, true)
		];

		if($this->resource->key === 'app') {
			$data['relationships']['timezones'] = \DateTimeZone::listIdentifiers();
		}

		return $data;
    }
}
