<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'units',
            'id' => (string) $this->id,
            'attributes' => [
                'business_id' => $this->business_id,
                'created_by' => $this->created_by,
                'is_active' => (bool) $this->is_active,
                'actual_name' => $this->actual_name,
                'short_name' => $this->short_name,
                'allow_decimal' => (bool) $this->allow_decimal,
                'base_unit_id' => $this->base_unit_id,
                'base_unit_multiplier' => $this->base_unit_multiplier !== null ? (float) $this->base_unit_multiplier : null,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
                'deleted_at' => $this->deleted_at?->toISOString(),
            ],
            'relationships' => [
                'business' => [
                    'data' => $this->when($this->relationLoaded('business'), function () {
                        return [
                            'type' => 'businesses',
                            'id' => (string) $this->business->id,
                        ];
                    })
                ],
                'products' => [
                    'data' => $this->when($this->relationLoaded('products'), function () {
                        return $this->products->map(function ($product) {
                            return [
                                'type' => 'products',
                                'id' => (string) $product->id,
                            ];
                        });
                    }),
                ],
                'creator' => [
                    'data' => $this->when($this->relationLoaded('creator'), function () {
                        return $this->creator ? [
                            'type' => 'users',
                            'id' => (string) $this->creator->id,
                        ] : null;
                    }),
                ],
            ],
        ];
    }

    public function with(Request $request): array
    {
        return [
            'jsonapi' => [
                'version' => '1.0',
            ],
        ];
    }

    public static function collection($resource)
    {
        return parent::collection($resource)->additional([
            'jsonapi' => [
                'version' => '1.0',
            ],
        ]);
    }
}
