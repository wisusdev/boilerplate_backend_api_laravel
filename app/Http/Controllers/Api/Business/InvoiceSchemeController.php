<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceSchemeRequest;
use App\Http\Resources\InvoiceSchemeResource;
use App\Models\Business;
use App\Models\InvoiceScheme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceSchemeController extends Controller
{
    /**
     * Display a listing of the invoice schemes for a business.
     * @throws AuthorizationException
     */
    public function index(Business $business, Request $request): AnonymousResourceCollection
    {
        $this->authorize('index', [InvoiceScheme::class, $business]);

        $invoiceSchemes = InvoiceScheme::query()
            ->where('business_id', $business->id)
            ->allowedIncludes(['business'])
            ->allowedFilters(['name', 'scheme_type', 'number_type', 'prefix', 'is_default'])
            ->allowedSorts(['id', 'name', 'prefix', 'scheme_type', 'is_default'])
            ->sparseFieldset(['business_id'])
            ->jsonPaginate();

        return InvoiceSchemeResource::collection($invoiceSchemes);
    }

    /**
     * Store a newly created invoice scheme.
     */
    public function store(InvoiceSchemeRequest $request, Business $business): InvoiceSchemeResource
    {
        $this->authorize('create', [InvoiceScheme::class, $business]);

        $validated = $request->validated();

        // Set business_id from route
        $validated['data']['attributes']['business_id'] = $business->id;

        // If this is set as default, unset other defaults
        if ($validated['data']['attributes']['is_default'] ?? false) {
            $business->invoice_schemes()->update(['is_default' => false]);
        }

        $invoiceScheme = InvoiceScheme::create($validated['data']['attributes']);

        return InvoiceSchemeResource::make($invoiceScheme);
    }

    /**
     * Display the specified invoice scheme.
     */
    public function show(Business $business, InvoiceScheme $invoiceScheme): InvoiceSchemeResource
    {
        $this->authorize('view', $invoiceScheme);

        $invoiceScheme = InvoiceScheme::where('id', $invoiceScheme->id)
            ->allowedIncludes(['business'])
            ->sparseFieldset(['business_id'])
            ->firstOrFail();

        return InvoiceSchemeResource::make($invoiceScheme);
    }

    /**
     * Update the specified invoice scheme.
     * @throws AuthorizationException
     */
    public function update(InvoiceSchemeRequest $request, Business $business, InvoiceScheme $invoiceScheme): InvoiceSchemeResource
    {
        $this->authorize('update', $invoiceScheme);

        $validated = $request->validated();

        // If this is set as default, unset other defaults
        if ($validated['data']['attributes']['is_default'] ?? false) {
            $business->invoice_schemes()->where('id', '!=', $invoiceScheme->id)->update(['is_default' => false]);
        }

        $invoiceScheme->update($validated['data']['attributes']);

        return InvoiceSchemeResource::make($invoiceScheme);
    }

    /**
     * Remove the specified invoice scheme.
     * @throws AuthorizationException
     */
    public function destroy(Business $business, InvoiceScheme $invoiceScheme): Response | JsonResponse
    {
        $this->authorize('delete', $invoiceScheme);

        // Check if a scheme is being used by business locations
        if ($invoiceScheme->business_locations()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el esquema de facturación porque está siendo utilizado por ubicaciones de negocio.',
                'errors' => [
                    'invoice_scheme' => ['Este esquema está siendo utilizado y no puede eliminarse.']
                ]
            ], 422);
        }

        $invoiceScheme->delete();

        return response()->noContent();
    }

    /**
     * Get statistics for the specified invoice scheme.
     */
    public function statistics(Business $business, InvoiceScheme $invoiceScheme): JsonResponse
    {
        $this->authorize('viewStatistics', $invoiceScheme);

        $stats = [
            'total_invoices' => $invoiceScheme->invoice_count,
            'business_locations_count' => $invoiceScheme->business_locations()->count(),
            'next_invoice_number' => $this->getNextInvoiceNumber($invoiceScheme),
            'scheme_info' => [
                'name' => $invoiceScheme->name,
                'type' => $invoiceScheme->scheme_type,
                'prefix' => $invoiceScheme->prefix,
                'is_default' => $invoiceScheme->is_default,
            ]
        ];

        return response()->json([
            'data' => [
                'type' => 'invoice_scheme_statistics',
                'attributes' => $stats
            ]
        ]);
    }

    /**
     * Change the status (default) of the invoice scheme.
     */
    public function changeStatus(Request $request, Business $business, InvoiceScheme $invoiceScheme): InvoiceSchemeResource
    {
        $this->authorize('changeStatus', $invoiceScheme);

        $request->validate([
            'data.type' => 'required|string',
            'data.id' => 'required|string',
            'data.attributes.is_default' => 'required|boolean'
        ]);

        $isDefault = $request->input('data.attributes.is_default');

        // If setting as default, unset other defaults
        if ($isDefault) {
            $business->invoice_schemes()
                ->where('id', '!=', $invoiceScheme->id)
                ->update(['is_default' => false]);
        }

        $invoiceScheme->update(['is_default' => $isDefault]);

        return InvoiceSchemeResource::make($invoiceScheme);
    }

    /**
     * Bulk delete invoice schemes.
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('bulkDelete', [InvoiceScheme::class, $business]);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:invoice_schemes,id'
        ]);

        $invoiceSchemes = $business->invoice_schemes()
            ->whereIn('id', $request->input('ids'))
            ->get();

        // Check if any scheme is being used
        foreach ($invoiceSchemes as $scheme) {
            if ($scheme->business_locations()->exists()) {
                return response()->json([
                    'message' => 'Algunos esquemas no pueden eliminarse porque están siendo utilizados.',
                    'errors' => [
                        'invoice_schemes' => ['Uno o más esquemas están siendo utilizados y no pueden eliminarse.']
                    ]
                ], 422);
            }
        }

        $deletedCount = $business->invoice_schemes()
            ->whereIn('id', $request->input('ids'))
            ->delete();

        return response()->json([
            'message' => "Se eliminaron {$deletedCount} esquemas de facturación correctamente."
        ]);
    }

    /**
     * Get the next invoice number for a scheme.
     */
    private function getNextInvoiceNumber(InvoiceScheme $invoiceScheme): string
    {
        $prefix = $invoiceScheme->prefix ?? '';
        $nextNumber = ($invoiceScheme->start_number ?? 1) + $invoiceScheme->invoice_count;

        if ($invoiceScheme->total_digits) {
            $nextNumber = str_pad($nextNumber, $invoiceScheme->total_digits, '0', STR_PAD_LEFT);
        }

        return $prefix . $nextNumber;
    }
}
