<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     *
     * @throws AuthorizationException
     */
    public function index(Business $business): JsonResource
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->where('business_id', $business->id)
            ->with(['business', 'parent', 'subcategories', 'creator'])
            ->allowedFilters(['name', 'category_type', 'parent_id'])
            ->allowedSorts(['id', 'name', 'category_type', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category in storage.
     *
     * @throws AuthorizationException
     */
    public function store(CategoryRequest $request, Business $business): CategoryResource
    {
        $this->authorize('create', Category::class);

        $data = $request->validated();
        $categoryData = $data['data']['attributes'];
        $categoryData['business_id'] = $business->id;

        $category = DB::transaction(function () use ($categoryData) {
            // Generar slug si no se proporciona
            if (!isset($categoryData['slug'])) {
                $categoryData['slug'] = str()->slug($categoryData['name']);
            }

            // Asegurar que el slug sea único
            $categoryData['slug'] = $this->generateUniqueSlug($categoryData['slug'], $categoryData['business_id']);

            return Category::create($categoryData);
        });

        return CategoryResource::make($category->load(['business', 'parent', 'subcategories', 'creator']));
    }

    /**
     * Display the specified category.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business, Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        $category->load([
            'business',
            'parent',
            'subcategories.products',
            'products',
            'creator'
        ]);

        return CategoryResource::make($category);
    }

    /**
     * Update the specified category in storage.
     *
     * @throws AuthorizationException
     */
    public function update(CategoryRequest $request, Business $business, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $data = $request->validated();
        $categoryData = $data['data']['attributes'];

        DB::transaction(function () use ($category, $categoryData) {
            // Actualizar slug si se cambió el nombre
            if (isset($categoryData['name']) && $categoryData['name'] !== $category->name) {
                $slug = isset($categoryData['slug']) ? $categoryData['slug'] : str()->slug($categoryData['name']);
                $categoryData['slug'] = $this->generateUniqueSlug($slug, $category->business_id, $category->id);
            }

            $category->update($categoryData);
        });

        return CategoryResource::make($category->load(['business', 'parent', 'subcategories', 'creator']));
    }

    /**
     * Remove the specified category from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Business $business, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        // Verificar que no tenga productos asociados
        if ($category->products()->exists()) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '422',
                        'title' => 'No se puede eliminar',
                        'detail' => 'La categoría tiene productos asociados y no puede ser eliminada.',
                    ]
                ]
            ], 422);
        }

        // Verificar que no tenga subcategorías
        if ($category->subcategories()->exists()) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '422',
                        'title' => 'No se puede eliminar',
                        'detail' => 'La categoría tiene subcategorías y no puede ser eliminada.',
                    ]
                ]
            ], 422);
        }

        $category->delete();

        return response()->json([], 204);
    }

    /**
     * Get category statistics.
     *
     * @throws AuthorizationException
     */
    public function statistics(Business $business, Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        $stats = [
            'products_count' => $category->products()->count(),
            'subcategories_count' => $category->subcategories()->count(),
            'active_products_count' => $category->products()->where('is_inactive', false)->count(),
            'inactive_products_count' => $category->products()->where('is_inactive', true)->count(),
            'total_products_with_subcategories' => $category->products()->count() +
                $category->subcategories()->withCount('products')->get()->sum('products_count'),
        ];

        return response()->json([
            'data' => [
                'type' => 'category-statistics',
                'id' => (string) $category->id,
                'attributes' => $stats,
            ]
        ]);
    }

    /**
     * Get products by category.
     *
     * @throws AuthorizationException
     */
    public function products(Business $business, Category $category): JsonResource
    {
        $this->authorize('view', $category);

        $products = $category->products()
            ->with(['business', 'brand', 'unit', 'variations'])
            ->allowedFilters(['name', 'sku', 'type', 'brand_id'])
            ->allowedSorts(['id', 'name', 'sku', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return \App\Http\Resources\ProductResource::collection($products);
    }

    /**
     * Change category status.
     *
     * @throws AuthorizationException
     */
    public function changeStatus(Request $request, Business $business, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $data = $request->input('data.attributes', []);
        $isActive = $data['is_active'] ?? !$category->is_active;

        $category->update(['is_active' => $isActive]);

        return response()->json([
            'data' => [
                'type' => 'categories',
                'id' => (string) $category->id,
                'attributes' => [
                    'is_active' => $category->is_active,
                ],
            ]
        ]);
    }

    /**
     * Restore a soft-deleted category.
     *
     * @throws AuthorizationException
     */
    public function restore(Business $business, Category $category): JsonResponse
    {
        $this->authorize('restore', $category);

        $category->restore();

        return response()->json([
            'data' => [
                'type' => 'categories',
                'id' => (string) $category->id,
                'attributes' => [
                    'deleted_at' => null,
                ],
            ]
        ]);
    }

    /**
     * Bulk delete categories.
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $data = $request->input('data.attributes', []);
        $categoryIds = $data['ids'] ?? [];
        $deletedCount = 0;
        $errors = [];

        foreach ($categoryIds as $categoryId) {
            try {
                $category = Category::where('business_id', $business->id)->findOrFail($categoryId);

                // Authorize deletion for each category individually
                $this->authorize('delete', $category);

                // Check if category has products
                if ($category->products()->exists()) {
                    $errors[] = "Category '{$category->name}' has products and cannot be deleted";
                    continue;
                }

                $category->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete category ID {$categoryId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'data' => [
                'type' => 'bulk-delete-result',
                'attributes' => [
                    'deleted_count' => $deletedCount,
                    'total_requested' => count($categoryIds),
                    'errors' => $errors,
                ],
            ]
        ]);
    }

    /**
     * Generate unique slug for category.
     */
    private function generateUniqueSlug(string $slug, int $businessId, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        $query = Category::where('business_id', $businessId)->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;

            $query = Category::where('business_id', $businessId)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
