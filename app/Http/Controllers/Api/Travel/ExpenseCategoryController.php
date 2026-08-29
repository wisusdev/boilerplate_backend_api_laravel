<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = ExpenseCategory::query()
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->jsonPaginate();

        return ExpenseCategoryResource::collection($categories);
    }

    public function store(ExpenseCategoryRequest $request): JsonResponse
    {
        $category = ExpenseCategory::create($request->validated()['data']['attributes']);

        return ExpenseCategoryResource::make($category->fresh())
            ->response()
            ->setStatusCode(201);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): ExpenseCategoryResource
    {
        $expenseCategory->update($request->validated()['data']['attributes']);

        return ExpenseCategoryResource::make($expenseCategory->fresh());
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        // La FK es RESTRICT: antes era CASCADE y borrar la categoría se llevaba
        // por delante los gastos (y dejaba sus recibos huérfanos en disco).
        $used = $expenseCategory->expenses()->count();

        if ($used > 0) {
            return response()->json([
                'errors' => [[
                    'status' => '409',
                    'title' => 'expenseCategory.inUse',
                    'detail' => "No se puede eliminar: tiene {$used} gasto(s) asociado(s). Desactívala en su lugar.",
                ]],
            ], 409);
        }

        $expenseCategory->delete();

        return response()->json(null, 204);
    }
}
