<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Services\ProfitabilityService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly ProfitabilityService $profitability) {}

    /**
     * Dashboard de rentabilidad: totales, ranking por tour y datos de gráficos.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'finance-summary',
                'attributes' => $this->profitability->summary(),
            ],
        ]);
    }
}
