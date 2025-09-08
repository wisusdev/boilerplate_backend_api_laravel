<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'categories',
            'id' => (string) $this->resource->id,
            'attributes' => [
                'name' => $this->resource->name,
                'business_id' => $this->resource->business_id,
                'short_code' => $this->resource->short_code,
                'parent_id' => $this->resource->parent_id,
                'category_type' => $this->resource->category_type,
                'description' => $this->resource->description,
                'slug' => $this->resource->slug,
                'created_by' => $this->resource->created_by,
                'deleted_at' => $this->resource->deleted_at?->toISOString(),
                'created_at' => $this->resource->created_at?->toISOString(),
                'updated_at' => $this->resource->updated_at?->toISOString(),
                
                // Campos calculados
                'products_count' => $this->when(
                    $this->resource->relationLoaded('products'),
                    fn() => $this->resource->products->count()
                ),
                'subcategories_count' => $this->when(
                    $this->resource->relationLoaded('subcategories'),
                    fn() => $this->resource->subcategories->count()
                ),
                'has_products' => $this->when(
                    $this->resource->relationLoaded('products'),
                    fn() => $this->resource->products->isNotEmpty()
                ),
                'has_subcategories' => $this->when(
                    $this->resource->relationLoaded('subcategories'),
                    fn() => $this->resource->subcategories->isNotEmpty()
                ),
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
                'parent' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('parent') && $this->resource->parent,
                        fn() => [
                            'type' => 'categories',
                            'id' => (string) $this->resource->parent->id,
                        ]
                    ),
                ],
                'subcategories' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('subcategories'),
                        fn() => $this->resource->subcategories->map(fn($subcategory) => [
                            'type' => 'categories',
                            'id' => (string) $subcategory->id,
                        ])
                    ),
                ],
                'products' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('products'),
                        fn() => $this->resource->products->map(fn($product) => [
                            'type' => 'products',
                            'id' => (string) $product->id,
                        ])
                    ),
                ],
                'creator' => [
                    'data' => $this->when(
                        $this->resource->relationLoaded('creator') && $this->resource->creator,
                        fn() => [
                            'type' => 'users',
                            'id' => (string) $this->resource->creator->id,
                        ]
                    ),
                ],
            ],
            'meta' => [
                'category_hierarchy' => $this->when(
                    $this->resource->relationLoaded('parent'),
                    fn() => $this->buildCategoryHierarchy()
                ),
            ],
        ];
    }

    /**
     * Build category hierarchy for breadcrumbs.
     */
    private function buildCategoryHierarchy(): array
    {
        $hierarchy = [];
        $current = $this->resource;

        while ($current) {
            array_unshift($hierarchy, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            
            $current = $current->parent;
        }

        return $hierarchy;
    }
}
