<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    /**
     * Listado filtrable por tour, categoría, guía y rango de fechas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        // Sin 'expenses:view-all' (p. ej. un 'guia') el listado se restringe a los
        // gastos atribuidos al propio usuario.
        $seesAll = $user?->can('expenses:view-all') ?? false;

        $expenses = Expense::query()
            ->with(['tour', 'category', 'guide'])
            ->unless($seesAll, fn ($q) => $q->where('guide_id', $user?->id))
            ->when($request->filled('tour_id'), fn ($q) => $q->where('tour_id', $request->integer('tour_id')))
            ->when($request->filled('expense_category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('expense_category_id')))
            ->when($seesAll && $request->filled('guide_id'), fn ($q) => $q->where('guide_id', $request->input('guide_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('spent_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('spent_at', '<=', $request->date('date_to')))
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->jsonPaginate();

        return ExpenseResource::collection($expenses);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $user = $request->user();
        $attributes = $request->validated()['data']['attributes'];
        $attributes['user_id'] = $user?->id;
        // Sin 'expenses:view-all' (p. ej. un 'guia') el gasto se atribuye a sí mismo:
        // se ignora cualquier guide_id enviado y se fuerza el del usuario autenticado.
        if (! ($user?->can('expenses:view-all') ?? false)) {
            $attributes['guide_id'] = $user?->id;
        }
        // Si no se envía fecha, se usa hoy (como el instalador de gastos original).
        $attributes['spent_at'] = $attributes['spent_at'] ?? now()->toDateString();

        $expense = Expense::create($attributes);

        return ExpenseResource::make($expense->load(['tour', 'category', 'guide']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated()['data']['attributes']);

        return ExpenseResource::make($expense->fresh()->load(['tour', 'category', 'guide']));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(null, 204);
    }

    /**
     * Sube (o reemplaza) la foto del recibo del gasto.
     */
    public function uploadReceipt(Request $request, Expense $expense): ExpenseResource
    {
        // Sin 'expenses:view-all' (p. ej. un 'guia') solo puede adjuntar recibos a
        // sus propios gastos.
        $user = $request->user();
        abort_unless(
            ($user?->can('expenses:view-all') ?? false) || $expense->guide_id === $user?->id,
            403
        );

        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $expense->addMediaFromRequest('image')->toMediaCollection('receipt');

        return ExpenseResource::make($expense->fresh()->load(['tour', 'category', 'guide']));
    }
}
