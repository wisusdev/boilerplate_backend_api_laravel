<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
		$value = json_decode($this->resource->value, true);

		$app = [
			'name' => $value['name'] ?? null,
			'url_api' => $value['url_api'] ?? null,
			'url_frontend' => $value['url_frontend'] ?? null,
			'description' => $value['description'] ?? null,
			'email' => $value['email'] ?? null,
			'phone' => $value['phone'] ?? null,
			'address' => $value['address'] ?? null,
			'timezone' => $value['timezone'] ?? null,
		];

		if ($this->resource->key === 'app') {
			$app['logo'] = $value['logo'] ? asset('storage' . $value['logo']) : null;
			$app['favicon'] = $value['favicon'] ? asset('storage' . $value['favicon']) : null;
		}

        $data = [
			'type' => $this->resource->key,
			'id' => (string) $this->resource->getRouteKey(),
			'attributes' => match($this->resource->key) {
				'app' => $app,
				default => $value,
			},
		];

		if($this->resource->key === 'app') {
			$data['relationships']['timezones'] = \DateTimeZone::listIdentifiers();
		}

		return $data;
    }
}
