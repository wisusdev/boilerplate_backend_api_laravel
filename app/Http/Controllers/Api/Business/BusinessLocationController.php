<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessLocationRequest;
use App\Http\Resources\BusinessLocationResource;
use App\Models\Business;
use App\Models\BusinessLocation;
use App\Utils\GeneralUtils;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class BusinessLocationController extends Controller
{
    /**
     * Display a listing of business locations for a specific business.
     *
     * @throws AuthorizationException
     */
    public function index(Business $business): JsonResource
    {
        $this->authorize('viewAny', [BusinessLocation::class, $business]);

        $locations = BusinessLocation::query()
            ->where('business_id', $business->id)
            ->allowedIncludes(['business'])
            ->allowedFilters(['name', 'is_active', 'business_id'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return BusinessLocationResource::collection($locations);
    }

    /**
     * Store a newly created business location in storage.
     *
     * @throws AuthorizationException
     */
    public function store(BusinessLocationRequest $request, Business $business): BusinessLocationResource
    {
        $this->authorize('create', [BusinessLocation::class, $business]);

        $businessId = $business->id;

        $data = $request->validated();
        $locationData = $data['data']['attributes'];
        $locationData['business_id'] = $businessId;

        $refCount = app(GeneralUtils::class)->setAndGetReferenceCount('business_location', $businessId);
        $locationData['location_id'] = app(GeneralUtils::class)->generateReferenceNumber('business_location', $refCount, $businessId, 'LOC-');

        $location = DB::transaction(function () use ($locationData) {
            return BusinessLocation::create($locationData);
        });

        return BusinessLocationResource::make($location);
    }

    /**
     * Display the specified business location.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business, BusinessLocation $business_location): BusinessLocationResource
    {
        $this->authorize('view', [$business, $business_location]);

        $businessLocation = BusinessLocation::where('id', $business_location->id)
            ->allowedIncludes(['business'])
            ->sparseFieldset(['business_id'])
            ->firstOrFail();

        return BusinessLocationResource::make($businessLocation);
    }

    /**
     * Update the specified business location in storage.
     *
     * @throws AuthorizationException
     */
    public function update(BusinessLocationRequest $request, Business $business, BusinessLocation $business_location): BusinessLocationResource
    {
        $this->authorize('update', $business_location);

        $data = $request->validated();
        $locationData = $data['data']['attributes'];

        DB::transaction(function () use ($business_location, $locationData) {
            $business_location->update($locationData);
        });

        return BusinessLocationResource::make($business_location);
    }

    /**
     * Remove the specified business location from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Business $business, BusinessLocation $business_location): Response|JsonResponse
    {
        $this->authorize('delete', $business_location);

        // Verificar que no tenga variaciones asociadas
        if ($business_location->variation_location_details()->exists()) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '422',
                        'title' => 'No se puede eliminar',
                        'detail' => 'La ubicación tiene productos asociados y no puede ser eliminada.',
                    ]
                ]
            ], 422);
        }

        $business_location->delete();

        return response()->noContent();
    }

    /**
     * Get business location statistics.
     *
     * @throws AuthorizationException
     */
    public function statistics(Business $business, BusinessLocation $location): JsonResponse
    {
        $this->authorize('view', $location);

        $stats = [
            'total_products' => $location->variation_location_details()->distinct('variation_id')->count('variation_id'),
            'total_inventory_value' => $location->variation_location_details()
                ->join('variations', 'variation_location_details.variation_id', '=', 'variations.id')
                ->selectRaw('SUM(variation_location_details.qty_available * variations.default_sell_price) as total_value')
                ->value('total_value') ?? 0,
            'location_name' => $location->name,
        ];

        return response()->json([
            'data' => [
                'type' => 'location-statistics',
                'id' => (string) $location->id,
                'attributes' => $stats,
            ]
        ]);
    }

    /**
     * Change business location status (active/inactive).
     */
    public function changeStatus(Request $request, Business $business, BusinessLocation $location): JsonResponse
    {
        $this->authorize('update', $location);

        $data = $request->input('data.attributes', []);
        $isActive = $data['is_active'] ?? !$location->is_active;

        $location->update(['is_active' => $isActive]);

        return response()->json([
            'data' => [
                'type' => 'business_locations',
                'id' => (string) $location->id,
                'attributes' => [
                    'is_active' => $location->is_active,
                ],
            ]
        ]);
    }

    /**
     * Restore a soft-deleted business location.
     *
     * @throws AuthorizationException
     */
    public function restore(Business $business, BusinessLocation $location): JsonResponse
    {
        $this->authorize('restore', $location);

        $location->restore();

        return response()->json([
            'data' => [
                'type' => 'business_locations',
                'id' => (string) $location->id,
                'attributes' => [
                    'deleted_at' => null,
                ],
            ]
        ]);
    }

    /**
     * Bulk delete business locations.
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('viewAny', [BusinessLocation::class, $business]);

        $data = $request->input('data.attributes', []);
        $locationIds = $data['ids'] ?? [];
        $deletedCount = 0;
        $errors = [];

        foreach ($locationIds as $locationId) {
            try {
                $location = BusinessLocation::where('business_id', $business->id)->findOrFail($locationId);

                // Authorize deletion for each location individually
                $this->authorize('delete', $location);

                // Check if location has products
                if ($location->variation_location_details()->exists()) {
                    $errors[] = "Location '{$location->name}' has products and cannot be deleted";
                    continue;
                }

                $location->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete location ID {$locationId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'data' => [
                'type' => 'bulk-delete-result',
                'attributes' => [
                    'deleted_count' => $deletedCount,
                    'total_requested' => count($locationIds),
                    'errors' => $errors,
                ],
            ]
        ]);
    }
}
