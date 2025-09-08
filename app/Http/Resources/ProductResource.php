<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'products',
            'id' => (string) $this->resource->getRouteKey(),
            'attributes' => [
                'name' => $this->resource->name,
                'business_id' => $this->resource->business_id,
                'type' => $this->resource->type,
                'unit_id' => $this->resource->unit_id,
                'sub_unit_ids' => $this->resource->sub_unit_ids,
                'category_id' => $this->resource->category_id,
                'sub_category_id' => $this->resource->sub_category_id,
                'brand_id' => $this->resource->brand_id,
                'tax' => $this->resource->tax,
                'tax_type' => $this->resource->tax_type,
                'enable_stock' => $this->resource->enable_stock,
                'alert_quantity' => $this->resource->alert_quantity,
                'sku' => $this->resource->sku,
                'barcode_type' => $this->resource->barcode_type,
                'expiry_period' => $this->resource->expiry_period,
                'expiry_period_type' => $this->resource->expiry_period_type,
                'enable_sr_no' => $this->resource->enable_sr_no,
                'weight' => $this->resource->weight,
                'product_description' => $this->resource->product_description,
                'image' => $this->resource->image ? url('storage/' . $this->resource->image) : null,
                'applicable_tax' => $this->resource->applicable_tax,
                'selling_price' => $this->resource->selling_price,
                'selling_price_tax_type' => $this->resource->selling_price_tax_type,
                
                // Campos personalizados
                'product_custom_field1' => $this->resource->product_custom_field1,
                'product_custom_field2' => $this->resource->product_custom_field2,
                'product_custom_field3' => $this->resource->product_custom_field3,
                'product_custom_field4' => $this->resource->product_custom_field4,
                'product_custom_field5' => $this->resource->product_custom_field5,
                'product_custom_field6' => $this->resource->product_custom_field6,
                'product_custom_field7' => $this->resource->product_custom_field7,
                'product_custom_field8' => $this->resource->product_custom_field8,
                'product_custom_field9' => $this->resource->product_custom_field9,
                'product_custom_field10' => $this->resource->product_custom_field10,
                'product_custom_field11' => $this->resource->product_custom_field11,
                'product_custom_field12' => $this->resource->product_custom_field12,
                'product_custom_field13' => $this->resource->product_custom_field13,
                'product_custom_field14' => $this->resource->product_custom_field14,
                'product_custom_field15' => $this->resource->product_custom_field15,
                'product_custom_field16' => $this->resource->product_custom_field16,
                'product_custom_field17' => $this->resource->product_custom_field17,
                'product_custom_field18' => $this->resource->product_custom_field18,
                'product_custom_field19' => $this->resource->product_custom_field19,
                'product_custom_field20' => $this->resource->product_custom_field20,
                
                'warranty_id' => $this->resource->warranty_id,
                'is_inactive' => $this->resource->is_inactive,
                'not_for_selling' => $this->resource->not_for_selling,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ],
            'relationships' => [
                'business' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('business') && $this->resource->business,
                        fn() => [
                            'type' => 'businesses',
                            'id' => (string) $this->resource->business->id,
                        ]
                    ),
                ],
                'category' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('category') && $this->resource->category,
                        fn() => [
                            'type' => 'categories',
                            'id' => (string) $this->resource->category->id,
                        ]
                    ),
                ],
                'sub_category' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('sub_category') && $this->resource->sub_category,
                        fn() => [
                            'type' => 'categories',
                            'id' => (string) $this->resource->sub_category->id,
                        ]
                    ),
                ],
                'brand' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('brand') && $this->resource->brand,
                        fn() => [
                            'type' => 'brands',
                            'id' => (string) $this->resource->brand->id,
                        ]
                    ),
                ],
                'unit' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('unit') && $this->resource->unit,
                        fn() => [
                            'type' => 'units',
                            'id' => (string) $this->resource->unit->id,
                        ]
                    ),
                ],
                'tax_rate' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('tax_rate') && $this->resource->tax_rate,
                        fn() => [
                            'type' => 'tax-rates',
                            'id' => (string) $this->resource->tax_rate->id,
                        ]
                    ),
                ],
                'variations' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('variations'),
                        fn() => $this->resource->variations->map(fn($variation) => [
                            'type' => 'variations',
                            'id' => (string) $variation->id,
                            'attributes' => [
                                'name' => $variation->name,
                                'sub_sku' => $variation->sub_sku,
                                'default_purchase_price' => $variation->default_purchase_price,
                                'dpp_inc_tax' => $variation->dpp_inc_tax,
                                'profit_percent' => $variation->profit_percent,
                                'default_sell_price' => $variation->default_sell_price,
                                'sell_price_inc_tax' => $variation->sell_price_inc_tax,
                                'created_at' => $variation->created_at,
                                'updated_at' => $variation->updated_at,
                            ]
                        ])
                    ),
                ],
                'product_variations' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('product_variations'),
                        fn() => $this->resource->product_variations->map(fn($productVariation) => [
                            'type' => 'product-variations',
                            'id' => (string) $productVariation->id,
                        ])
                    ),
                ],
            ],
        ];
    }
}
