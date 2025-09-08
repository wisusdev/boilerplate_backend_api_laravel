<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'variations',
            'id' => (string) $this->id,
            'attributes' => [
                'business_id' => $this->business_id,
                'product_id' => $this->product_id,
                'name' => $this->name,
                'slug' => $this->slug,
                'sku' => $this->sku,
                'price' => (float) $this->price,
                'cost_price' => (float) $this->cost_price,
                'quantity' => (float) $this->quantity,
                'min_quantity' => (float) $this->min_quantity,
                'weight' => $this->weight ? (float) $this->weight : null,
                'dimensions' => $this->dimensions,
                'color' => $this->color,
                'size' => $this->size,
                'material' => $this->material,
                'type' => $this->type,
                'description' => $this->description,
                'is_active' => (bool) $this->is_active,
                'sort_order' => (int) $this->sort_order,
                'meta_data' => $this->meta_data ? json_decode($this->meta_data, true) : null,
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
                    }),
                    'links' => [
                        'self' => route('api.businesses.show', $this->business_id),
                    ],
                ],
                'product' => [
                    'data' => $this->when($this->relationLoaded('product'), function () {
                        return [
                            'type' => 'products',
                            'id' => (string) $this->product->id,
                        ];
                    }),
                    'links' => [
                        'self' => route('api.businesses.products.show', [
                            'business' => $this->business_id,
                            'product' => $this->product_id
                        ]),
                        'related' => route('api.businesses.products.variations.index', [
                            'business' => $this->business_id,
                            'product' => $this->product_id
                        ]),
                    ],
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
            'links' => [
                'self' => route('api.businesses.variations.show', [
                    'business' => $this->business_id,
                    'variation' => $this->id
                ]),
            ],
            'meta' => [
                'stock_status' => $this->getStockStatus(),
                'is_low_stock' => $this->quantity <= $this->min_quantity,
                'is_out_of_stock' => $this->quantity <= 0,
                'profit' => $this->getProfit(),
                'profit_margin' => $this->getProfitMargin(),
                'stock_value' => $this->getStockValue(),
                'cost_value' => $this->getCostValue(),
                'physical_attributes' => $this->getPhysicalAttributes(),
                'has_meta_data' => !empty($this->meta_data),
            ],
        ];
    }

    protected function getStockStatus(): string
    {
        if ($this->quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->quantity <= $this->min_quantity) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    protected function getProfit(): float
    {
        return ($this->price - $this->cost_price) * $this->quantity;
    }

    protected function getProfitMargin(): float
    {
        if ($this->price <= 0) {
            return 0;
        }
        return (($this->price - $this->cost_price) / $this->price) * 100;
    }

    protected function getStockValue(): float
    {
        return $this->price * $this->quantity;
    }

    protected function getCostValue(): float
    {
        return $this->cost_price * $this->quantity;
    }

    protected function getPhysicalAttributes(): array
    {
        return array_filter([
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'color' => $this->color,
            'size' => $this->size,
            'material' => $this->material,
        ]);
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
