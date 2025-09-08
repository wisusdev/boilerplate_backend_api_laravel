<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class BusinessController extends Controller
{
    /**
     * Display a listing of the businesses.
     *
     * @throws AuthorizationException
     */
    public function index(): JsonResource
    {
        $this->authorize('viewAny', Business::class);

        $businesses = Business::query()
            ->allowedIncludes(['currency', 'owner', 'business_locations'])
            ->allowedFilters(['name', 'tax_number_1', 'is_active', 'currency_id'])
            ->allowedSorts(['id', 'currency_id', 'name', 'start_date', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return BusinessResource::collection($businesses);
    }

    /**
     * Store a newly created business in storage.
     *
     * @throws AuthorizationException
     */
    public function store(BusinessRequest $request): BusinessResource
    {
        $this->authorize('create', Business::class);

        $data = $request->validated();

        $businessData = $data['data']['attributes'];
        $businessData['owner_id'] = Auth::id();

        $business = DB::transaction(function () use ($businessData) {
            $business = Business::create($businessData);

            // Crear un esquema de facturación por defecto
            $invoiceScheme = $business->invoice_schemes()->create([
                'name' => 'Esquema Por Defecto',
                'scheme_type' => 'blank',
                'number_type' => 'sequential',
                'prefix' => 'INV',
                'start_number' => 1,
                'total_digits' => 6,
                'is_default' => true,
            ]);

            // Crear un diseño de factura por defecto
            $invoiceLayout = $business->invoice_layouts()->create([
                'name' => 'Diseño Por Defecto',
                'is_default' => true,
            ]);

            // Crear una ubicación de negocio por defecto
            $business->business_locations()->create([
                'name' => 'Ubicación Principal',
                'location_id' => 'LOC-001',
                'is_active' => true,
                'invoice_scheme_id' => $invoiceScheme->id,
                'invoice_layout_id' => $invoiceLayout->id,
                'country' => 'México',
                'state' => 'Ciudad de México',
                'city' => 'Ciudad de México',
                'zip_code' => '00000',
            ]);

            return $business;
        });

        return BusinessResource::make($business->load(['currency', 'owner', 'business_locations']));
    }

    /**
     * Display the specified business.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business): BusinessResource
    {
        $this->authorize('view', $business);

        $business = Business::where('id', $business->id)
            ->sparseFieldset()
            ->firstOrFail();

        return BusinessResource::make($business);
    }

    /**
     * Update the specified business in storage.
     *
     * @throws AuthorizationException
     */
    public function update(BusinessRequest $request, Business $business): BusinessResource
    {
        $this->authorize('update', $business);

        $data = $request->validated();
        $businessData = $data['data']['attributes'];

        DB::transaction(function () use ($business, $businessData) {
            $business->update($businessData);
        });

        return BusinessResource::make($business);
    }

    /**
     * Remove the specified business from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Business $business): Response
    {
        $this->authorize('delete', $business);

        DB::transaction(function () use ($business) {
            $business->delete();
        });

        return response()->noContent();
    }

    /**
     * Toggle business status (active/inactive).
     *
     * @throws AuthorizationException
     */
    public function toggleStatus(Business $business): BusinessResource
    {
        $this->authorize('update', $business);

        $business->update(['is_active' => !$business->is_active]);

        return BusinessResource::make($business->fresh());
    }

    /**
     * Get business statistics.
     *
     * @throws AuthorizationException
     */
    public function statistics(Business $business): JsonResponse
    {
        $this->authorize('view', $business);

        $stats = [
            'total_products' => $business->products()->count(),
            'total_categories' => $business->categories()->count(),
            'total_customers' => $business->contacts()->where('type', 'customer')->count(),
            'total_suppliers' => $business->contacts()->where('type', 'supplier')->count(),
            'total_locations' => $business->business_locations()->count(),
            'active_locations' => $business->business_locations()->where('is_active', true)->count(),
        ];

        return response()->json([
            'data' => [
                'type' => 'business_statistics',
                'id' => (string) $business->id,
                'attributes' => $stats
            ]
        ])->header('Content-Type', 'application/vnd.api+json');
    }
}
