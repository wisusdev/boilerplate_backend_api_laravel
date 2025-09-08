<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the currencies.
     *
     * @throws AuthorizationException
     */
    public function index(): JsonResource
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::query()
            ->with(['businesses', 'purchase_businesses'])
            ->allowedFilters(['country', 'country_code', 'currency', 'code', 'symbol'])
            ->allowedSorts(['id', 'country', 'country_code', 'currency', 'code', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return CurrencyResource::collection($currencies);
    }

    /**
     * Store a newly created currency in storage.
     *
     * @throws AuthorizationException
     */
    public function store(CurrencyRequest $request): CurrencyResource
    {
        $this->authorize('create', Currency::class);

        $data = $request->validated();
        $currencyData = $data['data']['attributes'];

        $currency = DB::transaction(function () use ($currencyData) {
            // Normalizar los códigos a mayúsculas
            $currencyData['code'] = strtoupper($currencyData['code']);
            $currencyData['country_code'] = strtoupper($currencyData['country_code']);

            return Currency::create($currencyData);
        });

        return CurrencyResource::make($currency->load(['businesses', 'purchase_businesses']));
    }

    /**
     * Display the specified currency.
     *
     * @throws AuthorizationException
     */
    public function show(Currency $currency): CurrencyResource
    {
        $this->authorize('view', $currency);

        $currency = Currency::where('id', $currency->id)
            ->sparseFieldset()
            ->firstOrFail();

        return CurrencyResource::make($currency);
    }

    /**
     * Update the specified currency in storage.
     *
     * @throws AuthorizationException
     */
    public function update(CurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $this->authorize('update', $currency);

        $data = $request->validated();
        $currencyData = $data['data']['attributes'];

        DB::transaction(function () use ($currency, $currencyData) {
            // Normalizar los códigos a mayúsculas si se proporcionan
            if (isset($currencyData['code'])) {
                $currencyData['code'] = strtoupper($currencyData['code']);
            }

            if (isset($currencyData['country_code'])) {
                $currencyData['country_code'] = strtoupper($currencyData['country_code']);
            }

            $currency->update($currencyData);
        });

        return CurrencyResource::make($currency->load(['businesses', 'purchase_businesses']));
    }

    /**
     * Remove the specified currency from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Currency $currency): JsonResponse
    {
        $this->authorize('delete', $currency);

        // Verificar que no tenga negocios asociados
        if ($currency->businesses()->exists()) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '422',
                        'title' => 'No se puede eliminar',
                        'detail' => 'La moneda está siendo utilizada como moneda principal por uno o más negocios.',
                    ]
                ]
            ], 422);
        }

        // Verificar que no sea usada como moneda de compra
        if ($currency->purchase_businesses()->exists()) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '422',
                        'title' => 'No se puede eliminar',
                        'detail' => 'La moneda está siendo utilizada como moneda de compra por uno o más negocios.',
                    ]
                ]
            ], 422);
        }

        $currency->delete();

        return response()->json([], 204);
    }

    /**
     * Display statistics for the specified currency.
     *
     * @throws AuthorizationException
     */
    public function statistics(Currency $currency): JsonResponse
    {
        $this->authorize('viewStatistics', $currency);

        $businessCount = $currency->businesses()->count();
        $purchaseBusinessCount = $currency->purchase_businesses()->count();
        $totalUsage = $businessCount + $purchaseBusinessCount;

        // Obtener ejemplos de negocios que usan esta moneda
        $businessExamples = $currency->businesses()
            ->select('id', 'name')
            ->limit(5)
            ->get();

        $purchaseBusinessExamples = $currency->purchase_businesses()
            ->select('id', 'name')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'type' => 'currency-statistics',
                'id' => (string) $currency->id,
                'attributes' => [
                    'currency_info' => [
                        'country' => $currency->country,
                        'country_code' => $currency->country_code,
                        'currency' => $currency->currency,
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                    ],
                    'usage_summary' => [
                        'as_main_currency' => $businessCount,
                        'as_purchase_currency' => $purchaseBusinessCount,
                        'total_usage' => $totalUsage,
                        'is_in_use' => $totalUsage > 0,
                    ],
                    'format_info' => [
                        'thousand_separator' => $currency->thousand_separator,
                        'decimal_separator' => $currency->decimal_separator,
                        'format_example' => $this->formatCurrencyExample($currency, 1234.56),
                    ],
                    'business_examples' => [
                        'main_currency' => $businessExamples->map(fn($business) => [
                            'id' => $business->id,
                            'name' => $business->name,
                            'usage_type' => 'main_currency'
                        ]),
                        'purchase_currency' => $purchaseBusinessExamples->map(fn($business) => [
                            'id' => $business->id,
                            'name' => $business->name,
                            'usage_type' => 'purchase_currency'
                        ]),
                    ],
                    'created_at' => $currency->created_at?->toISOString(),
                    'updated_at' => $currency->updated_at?->toISOString(),
                ]
            ]
        ]);
    }

    /**
     * Get a list of businesses using this currency.
     *
     * @throws AuthorizationException
     */
    public function businesses(Currency $currency): JsonResponse
    {
        $this->authorize('view', $currency);

        $mainCurrencyBusinesses = $currency->businesses()
            ->select('id', 'name', 'currency_id')
            ->get()
            ->map(fn($business) => [
                'id' => $business->id,
                'name' => $business->name,
                'usage_type' => 'main_currency'
            ]);

        $purchaseCurrencyBusinesses = $currency->purchase_businesses()
            ->select('id', 'name', 'purchase_currency_id')
            ->get()
            ->map(fn($business) => [
                'id' => $business->id,
                'name' => $business->name,
                'usage_type' => 'purchase_currency'
            ]);

        $allBusinesses = $mainCurrencyBusinesses->merge($purchaseCurrencyBusinesses);

        return response()->json([
            'data' => [
                'type' => 'currency-businesses',
                'id' => (string) $currency->id,
                'attributes' => [
                    'currency_code' => $currency->code,
                    'businesses' => $allBusinesses,
                    'summary' => [
                        'total_count' => $allBusinesses->count(),
                        'main_currency_count' => $mainCurrencyBusinesses->count(),
                        'purchase_currency_count' => $purchaseCurrencyBusinesses->count(),
                    ]
                ]
            ]
        ]);
    }

    /**
     * Bulk delete currencies.
     *
     * @throws AuthorizationException
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('bulkDelete', Currency::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:currencies,id'
        ]);

        $currencies = Currency::whereIn('id', $request->ids)->get();
        $errors = [];
        $deleted = [];

        foreach ($currencies as $currency) {
            if ($currency->businesses()->exists() || $currency->purchase_businesses()->exists()) {
                $errors[] = [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'message' => 'No se puede eliminar porque está en uso por negocios.'
                ];
            } else {
                $currency->delete();
                $deleted[] = [
                    'id' => $currency->id,
                    'code' => $currency->code
                ];
            }
        }

        return response()->json([
            'data' => [
                'type' => 'bulk-delete-result',
                'attributes' => [
                    'deleted' => $deleted,
                    'errors' => $errors,
                    'deleted_count' => count($deleted),
                    'error_count' => count($errors),
                ]
            ]
        ]);
    }

    /**
     * Format a currency example with the currency's separators and symbol.
     */
    private function formatCurrencyExample(Currency $currency, float $amount): string
    {
        $thousands = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $symbol = $currency->symbol;

        // Formatear número con separadores
        $formattedAmount = number_format($amount, 2, $decimal, $thousands);

        // Agregar símbolo según convención común
        if (in_array($currency->code, ['USD', 'EUR', 'GBP', 'CAD', 'AUD'])) {
            return $symbol . $formattedAmount;
        }

        return $formattedAmount . ' ' . $symbol;
    }
}
