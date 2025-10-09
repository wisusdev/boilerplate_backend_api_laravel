<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceLayoutRequest;
use App\Http\Resources\InvoiceLayoutResource;
use App\Models\Business;
use App\Models\InvoiceLayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceLayoutController extends Controller
{
    /**
     * Display a listing of the invoice layouts for a business.
     * @throws AuthorizationException
     */
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [InvoiceLayout::class, $business]);

        $invoiceLayouts = InvoiceLayout::query()
            ->where('business_id', $business->id)
            ->allowedIncludes(['business'])
            ->allowedFilters(['name', 'design', 'is_default', 'show_logo', 'show_business_name'])
            ->allowedSorts(['id', 'name', 'design', 'is_default', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return InvoiceLayoutResource::collection($invoiceLayouts);
    }

    /**
     * Store a newly created invoice layout.
     * @throws AuthorizationException
     */
    public function store(InvoiceLayoutRequest $request, Business $business): InvoiceLayoutResource
    {
        $this->authorize('create', [InvoiceLayout::class, $business]);

        $validated = $request->validated();

        // Set business_id from route
        $validated['data']['attributes']['business_id'] = $business->id;

        // If this is set as default, unset other defaults
        if ($validated['data']['attributes']['is_default'] ?? false) {
            $business->invoice_layouts()->update(['is_default' => false]);
        }

        $invoiceLayout = InvoiceLayout::create($validated['data']['attributes']);

        return InvoiceLayoutResource::make($invoiceLayout);
    }

    /**
     * Display the specified invoice layout.
     */
    public function show(Business $business, InvoiceLayout $invoiceLayout): InvoiceLayoutResource
    {
        $this->authorize('view', $invoiceLayout);

        $invoiceLayout = InvoiceLayout::where('id', $invoiceLayout->id)
            ->allowedIncludes(['business'])
            ->sparseFieldset()
            ->firstOrFail();

        return InvoiceLayoutResource::make($invoiceLayout);
    }

    /**
     * Update the specified invoice layout.
     */
    public function update(InvoiceLayoutRequest $request, Business $business, InvoiceLayout $invoiceLayout): InvoiceLayoutResource
    {
        $this->authorize('update', $invoiceLayout);

        $validated = $request->validated();

        // If this is set as default, unset other defaults
        if ($validated['data']['attributes']['is_default'] ?? false) {
            $business->invoice_layouts()->where('id', '!=', $invoiceLayout->id)->update(['is_default' => false]);
        }

        $invoiceLayout->update($validated['data']['attributes']);

        return InvoiceLayoutResource::make($invoiceLayout);
    }

    /**
     * Remove the specified invoice layout.
     * @throws AuthorizationException
     */
    public function destroy(Business $business, InvoiceLayout $invoiceLayout): Response | JsonResponse
    {
        $this->authorize('delete', $invoiceLayout);

        // Check if the layout is being used by business locations
        if ($invoiceLayout->business_locations()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el diseño de facturación porque está siendo utilizado por ubicaciones de negocio.',
                'errors' => [
                    'invoice_layout' => ['Este diseño está siendo utilizado y no puede eliminarse.']
                ]
            ], 422);
        }

        $invoiceLayout->delete();

        return response()->noContent();
    }

    /**
     * Get statistics for the specified invoice layout.
     */
    public function statistics(Business $business, InvoiceLayout $invoiceLayout): JsonResponse
    {
        $this->authorize('viewStatistics', $invoiceLayout);

        $stats = [
            'business_locations_count' => $invoiceLayout->business_locations()->count(),
            'design_info' => [
                'name' => $invoiceLayout->name,
                'design' => $invoiceLayout->design,
                'is_default' => $invoiceLayout->is_default,
                'show_logo' => $invoiceLayout->show_logo,
                'show_business_name' => $invoiceLayout->show_business_name,
            ]
        ];

        return response()->json([
            'data' => [
                'type' => 'invoice_layout_statistics',
                'attributes' => $stats
            ]
        ]);
    }

    /**
     * Change the status (default) of the invoice layout.
     */
    public function changeStatus(Request $request, Business $business, InvoiceLayout $invoiceLayout): InvoiceLayoutResource
    {
        $this->authorize('changeStatus', $invoiceLayout);

        $request->validate([
            'data.type' => 'required|string',
            'data.id' => 'required|string',
            'data.attributes.is_default' => 'required|boolean'
        ]);

        $isDefault = $request->input('data.attributes.is_default');

        // If setting as default, unset other defaults
        if ($isDefault) {
            $business->invoice_layouts()
                ->where('id', '!=', $invoiceLayout->id)
                ->update(['is_default' => false]);
        }

        $invoiceLayout->update(['is_default' => $isDefault]);

        return InvoiceLayoutResource::make($invoiceLayout);
    }

    /**
     * Bulk delete invoice layouts.
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('bulkDelete', [InvoiceLayout::class, $business]);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:invoice_layouts,id'
        ]);

        $invoiceLayouts = $business->invoice_layouts()
            ->whereIn('id', $request->input('ids'))
            ->get();

        // Check if any layout is being used
        foreach ($invoiceLayouts as $layout) {
            if ($layout->business_locations()->exists()) {
                return response()->json([
                    'message' => 'Algunos diseños no pueden eliminarse porque están siendo utilizados.',
                    'errors' => [
                        'invoice_layouts' => ['Uno o más diseños están siendo utilizados y no pueden eliminarse.']
                    ]
                ], 422);
            }
        }

        $deletedCount = $business->invoice_layouts()
            ->whereIn('id', $request->input('ids'))
            ->delete();

        return response()->json([
            'message' => "Se eliminaron {$deletedCount} diseños de facturación correctamente."
        ]);
    }

    /**
     * Duplicate an invoice layout.
     */
    public function duplicate(Business $business, InvoiceLayout $invoiceLayout): InvoiceLayoutResource
    {
        $this->authorize('duplicate', $invoiceLayout);

        $duplicated = $invoiceLayout->replicate();
        $duplicated->name = $invoiceLayout->name . ' (Copia)';
        $duplicated->is_default = false;
        $duplicated->save();

        return InvoiceLayoutResource::make($duplicated);
    }
}
