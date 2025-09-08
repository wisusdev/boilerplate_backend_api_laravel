<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\Unit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class UnitController extends Controller
{
    /**
     * Display a listing of units for a business.
     * @throws AuthorizationException
     */
    public function index(Business $business): JsonResource
    {
        $this->authorize('viewAny', [Unit::class, $business]);

        $units = Unit::query()
            ->where('business_id', $business->id)
            ->allowedFilters(['is_active', 'actual_name', 'short_name', 'allow_decimal', 'base_unit_id', 'base_unit_multiplier'])
            ->allowedSorts(['business_id', 'created_by', 'is_active', 'actual_name', 'short_name', 'allow_decimal', 'base_unit_id', 'base_unit_multiplier'])
            ->sparseFieldset()
            ->jsonPaginate();

        return UnitResource::collection($units);
    }

    /**
     * Store a newly created unit.
     */
    public function store(UnitRequest $request, Business $business): UnitResource
    {
        Gate::authorize('create', [Unit::class, $business]);

        $validatedData = $request->validated();
        $attributes = $validatedData['data']['attributes'];

        $unit = Unit::create($attributes);

        $unit->load(['business', 'creator']);

        return new UnitResource($unit);
    }

    /**
     * Display the specified unit.
     */
    public function show(Business $business, Unit $unit): UnitResource
    {
        Gate::authorize('view', $unit);

        $unit->load(['business', 'creator']);

        // Incluir count de productos si se solicita
        if (request()->filled('include') && str_contains(request()->input('include'), 'products_count')) {
            $unit->loadCount('products');
        }

        return new UnitResource($unit);
    }

    /**
     * Update the specified unit.
     */
    public function update(UnitRequest $request, Business $business, Unit $unit): UnitResource
    {
        Gate::authorize('update', $unit);

        $validatedData = $request->validated();
        $attributes = $validatedData['data']['attributes'];

        $unit->update($attributes);

        $unit->load(['business', 'creator']);

        return new UnitResource($unit);
    }

    /**
     * Remove the specified unit from storage.
     * @throws AuthorizationException
     */
    public function destroy(Business $business, Unit $unit): Response
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted unit.
     */
    public function restore(Business $business, int $unitId): UnitResource
    {
        $unit = Unit::withTrashed()->findOrFail($unitId);
        Gate::authorize('restore', $unit);

        $unit->restore();
        $unit->load(['business', 'creator']);

        return new UnitResource($unit);
    }

    /**
     * Get unit statistics.
     */
    public function statistics(Business $business, Unit $unit): JsonResponse
    {
        Gate::authorize('viewStatistics', $unit);

        $productsCount = $unit->products()->count();
        $activeProductsCount = $unit->products()->where('is_inactive', false)->count();
        $totalStock = count($unit->products()->where('is_inactive', false)->get());

        return response()->json([
            'data' => [
                'type' => 'unit-statistics',
                'id' => (string) $unit->id,
                'attributes' => [
                    'products_count' => $productsCount,
                    'active_products_count' => $activeProductsCount,
                    'inactive_products_count' => $productsCount - $activeProductsCount,
                    'total_stock' => $totalStock,
                    'unit_name' => $unit->name,
                    'unit_type' => $unit->type,
                    'is_base_unit' => $unit->base_unit === null,
                ]
            ]
        ]);
    }

    /**
     * Get products by unit.
     */
    public function products(Request $request, Business $business, Unit $unit): AnonymousResourceCollection
    {
        Gate::authorize('manageProducts', $unit);

        $query = $unit->products()
            ->with(['business', 'category', 'brand', 'unit', 'creator']);

        // Filtros
        if ($request->filled('filter.is_active')) {
            $isActive = filter_var($request->input('filter.is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%' . $request->input('filter.name') . '%');
        }

        // Ordenamiento
        $sortBy = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');

        $allowedSorts = ['name', 'price', 'quantity', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Paginación
        $perPage = min((int) $request->input('per_page', 15), 100);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Change unit status (active/inactive).
     * @throws AuthorizationException
     */
    public function changeStatus(Request $request, Business $business, Unit $unit): UnitResource
    {
        $this->authorize('changeStatus', $unit);

        $request->validate([
            'data.attributes.is_active' => 'required|boolean',
        ]);

        $unit->update([
            'is_active' => $request->input('data.attributes.is_active')
        ]);

        $unit->load(['business', 'creator']);

        return new UnitResource($unit);
    }

    /**
     * Bulk delete units.
     * @throws AuthorizationException
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('bulkDelete', [Unit::class, $business]);

        $request->validate([
            'data.attributes.ids' => 'required|array|min:1',
            'data.attributes.ids.*' => 'required|integer|exists:units,id',
        ]);

        $data = $request->input('data.attributes', []);
        $ids = $data['ids'] ?? [];
        $deletedCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $unit = Unit::where('business_id', $business->id)->findOrFail($id);
                $this->authorize('delete', $unit);

                if ($unit->products()->exists()) {
                    $errors[] = "No se puede eliminar la unidad {$unit->actual_name} porque tiene productos asociados.";
                    continue;
                }

                $unit->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Error al eliminar la unidad con ID {$id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'data' => [
                'type' => 'bulk-delete-result',
                'attributes' => [
                    'deleted_count' => $deletedCount,
                    'total_requested' => count($ids),
                    'errors' => $errors,
                ]
            ]
        ]);
    }
}
