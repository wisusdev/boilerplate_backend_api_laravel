<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\Tour;
use Illuminate\Support\Collection;

/**
 * Calcula la rentabilidad por tour: ingresos (derivados de las reservas
 * confirmadas) − gastos = margen. Alimenta el dashboard de finanzas.
 */
class ProfitabilityService
{
    /**
     * Resumen completo: totales, ranking por tour y datos de gráficos.
     */
    public function summary(): array
    {
        $incomeByTour   = $this->incomeByTour();          // [tour_id => income]
        $expensesByTour = $this->expensesByTour();        // [tour_id => expenses]
        $generalExpenses = (float) Expense::whereNull('tour_id')->sum('amount');

        $rows = Tour::query()
            ->orderBy('title')
            ->get(['id', 'title', 'location'])
            ->map(fn (Tour $tour) => $this->row(
                $tour->id,
                $tour->title,
                $tour->location,
                (float) ($incomeByTour[$tour->id] ?? 0),
                (float) ($expensesByTour[$tour->id] ?? 0),
            ))
            ->all();

        // Bucket de gastos generales (no imputados a un tour).
        if ($generalExpenses > 0) {
            $rows[] = $this->row(null, 'Gastos generales', 'Gastos no específicos de un tour', 0.0, $generalExpenses);
        }

        // Ordenado por margen, de mejor a peor.
        usort($rows, fn ($a, $b) => $b['margin'] <=> $a['margin']);

        $totalIncome   = (float) collect($incomeByTour)->sum();
        $totalExpenses = (float) Expense::sum('amount');

        return [
            'totals' => [
                'income'   => round($totalIncome, 2),
                'expenses' => round($totalExpenses, 2),
                'margin'   => round($totalIncome - $totalExpenses, 2),
            ],
            'tours' => $rows,
            'charts' => [
                // Ingresos vs. gastos por tour (solo con actividad).
                'income_vs_expenses' => array_values(array_filter(
                    $rows,
                    fn ($r) => $r['income'] > 0 || $r['expenses'] > 0,
                )),
                'expenses_by_category' => $this->expensesByCategory(),
            ],
        ];
    }

    /**
     * Ingreso por tour = suma de total_price de las reservas de tour confirmadas.
     *
     * @return array<int, float>
     */
    private function incomeByTour(): array
    {
        return Booking::query()
            ->where('bookable_type', Tour::class)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->groupBy('bookable_id')
            ->selectRaw('bookable_id, SUM(total_price) as income')
            ->pluck('income', 'bookable_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Gastos por tour (excluye los generales sin tour).
     *
     * @return array<int, float>
     */
    private function expensesByTour(): array
    {
        return Expense::query()
            ->whereNotNull('tour_id')
            ->groupBy('tour_id')
            ->selectRaw('tour_id, SUM(amount) as total')
            ->pluck('total', 'tour_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Total de gastos por categoría, de mayor a menor.
     */
    private function expensesByCategory(): Collection
    {
        return Expense::query()
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.icon')
            ->selectRaw('expense_categories.name as name, expense_categories.icon as icon, SUM(expenses.amount) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->name,
                'icon'  => $r->icon,
                'total' => round((float) $r->total, 2),
            ]);
    }

    /**
     * Construye una fila del ranking de rentabilidad.
     */
    private function row(?int $tourId, string $name, ?string $subtitle, float $income, float $expenses): array
    {
        $margin = $income - $expenses;

        // % del ingreso que se va en gastos.
        $expenseRatio = $income > 0
            ? (int) round($expenses / $income * 100)
            : ($expenses > 0 ? 100 : 0);

        return [
            'tour_id'       => $tourId,
            'name'          => $name,
            'subtitle'      => $subtitle,
            'income'        => round($income, 2),
            'expenses'      => round($expenses, 2),
            'margin'        => round($margin, 2),
            'expense_ratio' => $expenseRatio,
            'label'         => $income > 0 ? 'rentable' : 'sin_ingresos',
        ];
    }
}
