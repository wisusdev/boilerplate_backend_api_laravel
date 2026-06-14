<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'reports',
                'attributes' => $this->reportService->overview(
                    $request->query('start_date'),
                    $request->query('end_date')
                ),
            ],
        ]);
    }
}