<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxRateRequest;
use App\Http\Resources\TaxRateResource;
use App\Models\Business;
use App\Models\TaxRate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TaxRateController extends Controller
{
    /**
     * Display a listing of the tax rates.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request, Business $business): JsonResource
    {
        $this->authorize('index', TaxRate::class);

        $taxRates = TaxRate::query()
            ->where('business_id', $business->id)
	        ->allowedIncludes(['business', 'creator'])
            ->allowedFilters(['name', 'is_tax_group'])
            ->allowedSorts(['id', 'name', 'amount', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return TaxRateResource::collection($taxRates);
    }

	/**
	 * Store a newly created tax rate in storage.
	 *
	 * @throws AuthorizationException
	 * @throws \Throwable
	 */
    public function store(TaxRateRequest $request, Business $business): JsonResource
    {
        $this->authorize('create', TaxRate::class);

        $data = $request->validated();
		$taxRateData = $data['data']['attributes'];
		$taxRateData['business_id'] = $business->id;

		$taxRate = DB::transaction(function () use ($taxRateData) {
			return TaxRate::create($taxRateData);
		});

        return TaxRateResource::make($taxRate);
    }

    /**
     * Display the specified tax rate.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business, TaxRate $taxRate): JsonResource
    {
        $this->authorize('view', [$taxRate]);

		$taxRate = TaxRate::where('id', $taxRate->id)
			->allowedIncludes(['business', 'creator'])
			->sparseFieldset()
			->firstOrFail();

        return TaxRateResource::make($taxRate);
    }

    /**
     * Update the specified tax rate in storage.
     *
     * @throws AuthorizationException
     */
    public function update(TaxRateRequest $request, Business $business, TaxRate $taxRate): JsonResource
    {
        $this->authorize('update', $taxRate);

		$data = $request->validated();
		$taxRateData = $data['data']['attributes'];

		DB::transaction(function () use ($taxRate, $taxRateData) {
			$taxRate->update($taxRateData);
		});

        return TaxRateResource::make($taxRate);
    }

    /**
     * Remove the specified tax rate from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Business $business, TaxRate $taxRate): Response | JsonResponse
    {
        $this->authorize('delete', $taxRate);

        // La tasa está en uso en líneas de venta
		if ($taxRate->transaction_sell_lines()->exists()) {
			return response()->json([
				'errors' => [
					[
						'status' => '422',
						'title' => 'No se puede eliminar',
						'detail' => 'La tasa de impuesto está en uso en líneas de venta y no se puede eliminar',
					]
				]
			], 422);
		}

		// La tasa está en uso en líneas de compra
		if ($taxRate->purchase_lines()->exists()) {
			return response()->json([
				'errors' => [
					[
						'status' => '422',
						'title' => 'No se puede eliminar',
						'detail' => 'La tasa de impuesto está en uso en líneas de compra y no se puede eliminar',
					]
				]
			], 422);
		}

		// La tasa está en uso en negocios
		if ($taxRate->businesses()->exists()) {
			return response()->json([
				'errors' => [
					[
						'status' => '422',
						'title' => 'No se puede eliminar',
						'detail' => 'La tasa de impuesto está en uso en negocios y no se puede eliminar',
					]
				]
			], 422);
		}

        $taxRate->delete();

        return response()->noContent();
    }

    /**
     * Bulk delete tax rates.
     *
     * @throws AuthorizationException
     */
    public function bulkDestroy(Request $request, Business $business): JsonResponse
    {
        // Para bulk delete, no necesitamos autorización específica de taxRate
        // Solo verificamos que el usuario pueda eliminar en este negocio
        $this->authorize('viewAny', [TaxRate::class, $business]);

        $data = $request->input('data.attributes', []);
        $taxRateIds = $data['ids'] ?? [];
        $deletedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($taxRateIds as $taxRateId) {
            try {
                $taxRate = TaxRate::where('business_id', $business->id)->findOrFail($taxRateId);

                // Authorize deletion for each tax rate individually
                $this->authorize('delete', [$taxRate, $business]);

                // Verificar si la tasa está en uso
                if ($taxRate->transaction_sell_lines()->exists() || 
                    $taxRate->purchase_lines()->exists() ||
                    $taxRate->businesses()->exists()) {
                    $errors[] = "Tax rate '{$taxRate->name}' is in use and cannot be deleted";
                    $failedCount++;
                    continue;
                }

                $taxRate->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete tax rate ID {$taxRateId}: " . $e->getMessage();
                $failedCount++;
            }
        }

        return response()->json([
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'total_requested' => count($taxRateIds),
            'errors' => $errors,
        ]);
    }

    /**
     * Get statistics for a tax rate.
     *
     * @throws AuthorizationException
     */
    public function statistics(Business $business, TaxRate $taxRate): JsonResponse
    {
        $this->authorize('view', [$taxRate, $business]);

        $sellLinesCount = $taxRate->transaction_sell_lines()->count();
        $purchaseLinesCount = $taxRate->purchase_lines()->count();
        $businessesUsing = $taxRate->businesses()->count();
        
        // Calcular el total de impuestos recolectados (esto es una aproximación)
        $totalTaxCollected = $taxRate->transaction_sell_lines()
            ->sum(DB::raw('item_tax'));

        return response()->json([
            'sell_lines_count' => $sellLinesCount,
            'purchase_lines_count' => $purchaseLinesCount,
            'businesses_using' => $businessesUsing,
            'total_tax_collected' => (float) $totalTaxCollected
        ]);
    }

    /**
     * Change status of a tax rate.
     *
     * @throws AuthorizationException
     */
    public function changeStatus(Business $business, TaxRate $taxRate): JsonResource
    {
        $this->authorize('update', [$taxRate, $business]);

        if ($taxRate->trashed()) {
            $taxRate->restore();
        } else {
            $taxRate->delete();
        }

        return new TaxRateResource($taxRate->fresh());
    }

    /**
     * Restore a soft deleted tax rate.
     *
     * @throws AuthorizationException
     */
    public function restore(Business $business, TaxRate $taxRate): JsonResource
    {
        $this->authorize('restore', [$taxRate, $business]);

        $taxRate->restore();

        return new TaxRateResource($taxRate);
    }
}
