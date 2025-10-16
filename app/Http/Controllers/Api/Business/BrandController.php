<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

class BrandController extends Controller
{
    /**
     * Display a listing of brands for a specific business.
     *
     * @throws AuthorizationException
     */
    public function index(Business $business): JsonResource
    {
        $this->authorize('viewAny', [Brand::class, $business]);

        $brands = Brand::query()
            ->where('business_id', $business->id)
            ->allowedIncludes(['business', 'creator', 'products'])
            ->allowedFilters(['name', 'business_id'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return BrandResource::collection($brands);
    }

    /**
     * Store a newly created brand in storage.
     *
     * @throws AuthorizationException|Throwable
     */
    public function store(BrandRequest $request, Business $business): BrandResource
    {
        $this->authorize('create', [Brand::class, $business]);

        $data = $request->validated();
        $brandData = $data['data']['attributes'];

        $brand = DB::transaction(function () use ($brandData) {
            return Brand::create($brandData);
        });

        return BrandResource::make($brand->load(['business', 'creator', 'products']));
    }

    /**
     * Display the specified brand.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business, Brand $brand): BrandResource
    {
        $this->authorize('view', $brand);

        $brandData = Brand::where('id', $brand->id)
            ->allowedIncludes(['business'])
            ->sparseFieldset(['business_id'])
            ->firstOrFail();

        return BrandResource::make($brandData);
    }

    /**
     * Update the specified brand in storage.
     *
     * @throws AuthorizationException|Throwable
     */
    public function update(BrandRequest $request, Business $business, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $data = $request->validated();
        $brandData = $data['data']['attributes'];

        DB::transaction(function () use ($brand, $brandData) {
            $brand->update($brandData);
        });

        return BrandResource::make($brand);
    }

    /**
     * Remove the specified brand from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Business $business, Brand $brand): Response|JsonResponse
    {
        $this->authorize('delete', $brand);

        // Verificar que no tenga productos asociados
        if ($brand->products()->exists()) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '422',
                        'title' => 'No se puede eliminar',
                        'detail' => 'La marca tiene productos asociados y no puede ser eliminada.',
                    ]
                ]
            ], 422);
        }

        $brand->delete();

        return response()->noContent();
    }

    /**
     * Get brand statistics.
     *
     * @throws AuthorizationException
     */
    public function statistics(Business $business, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $stats = [
            'products_count' => $brand->products()->count(),
            'active_products_count' => $brand->products()->where('is_inactive', false)->count(),
            'inactive_products_count' => $brand->products()->where('is_inactive', true)->count(),
            'categories_count' => $brand->products()->distinct('category_id')->count('category_id'),
            'total_value' => $brand->products()
                ->join('variations', 'products.id', '=', 'variations.product_id')
                ->join('variation_location_details', 'variations.id', '=', 'variation_location_details.variation_id')
                ->selectRaw('SUM(variation_location_details.qty_available * variations.default_sell_price) as total_value')
                ->value('total_value') ?? 0,
            'brand_name' => $brand->name,
        ];

        return response()->json([
            'data' => [
                'type' => 'brand-statistics',
                'id' => (string) $brand->id,
                'attributes' => $stats,
            ]
        ]);
    }

    /**
     * Get products by brand.
     *
     * @throws AuthorizationException
     */
    public function products(Business $business, Brand $brand): JsonResource
    {
        $this->authorize('view', $brand);

        $products = $brand->products()
            ->with(['business', 'category', 'unit', 'variations'])
            ->allowedFilters(['name', 'sku', 'type', 'category_id'])
            ->allowedSorts(['id', 'name', 'sku', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return \App\Http\Resources\ProductResource::collection($products);
    }

    /**
     * Change brand status (active/inactive).
     */
    public function changeStatus(Request $request, Business $business, Brand $brand): JsonResponse
    {
        $this->authorize('update', $brand);

        $data = $request->input('data.attributes', []);
        $isActive = $data['is_active'] ?? !$brand->is_active;

        $brand->update(['is_active' => $isActive]);

        return response()->json([
            'data' => [
                'type' => 'brands',
                'id' => (string) $brand->id,
                'attributes' => [
                    'is_active' => $brand->is_active,
                ],
            ]
        ]);
    }

    /**
     * Restore a soft-deleted brand.
     *
     * @throws AuthorizationException
     */
    public function restore(Business $business, Brand $brand): JsonResponse
    {
        $this->authorize('restore', $brand);

        $brand->restore();

        return response()->json([
            'data' => [
                'type' => 'brands',
                'id' => (string) $brand->id,
                'attributes' => [
                    'deleted_at' => null,
                ],
            ]
        ]);
    }

    /**
     * Bulk delete brands.
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('viewAny', [Brand::class, $business]);

        $data = $request->input('data.attributes', []);
        $brandIds = $data['ids'] ?? [];
        $deletedCount = 0;
        $errors = [];

        foreach ($brandIds as $brandId) {
            try {
                $brand = Brand::where('business_id', $business->id)->findOrFail($brandId);

                // Authorize deletion for each brand individually
                $this->authorize('delete', $brand);

                // Check if brand has products
                if ($brand->products()->exists()) {
                    $errors[] = "Brand '{$brand->name}' has products and cannot be deleted";
                    continue;
                }

                $brand->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete brand ID {$brandId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'data' => [
                'type' => 'bulk-delete-result',
                'attributes' => [
                    'deleted_count' => $deletedCount,
                    'total_requested' => count($brandIds),
                    'errors' => $errors,
                ],
            ]
        ]);
    }
}
