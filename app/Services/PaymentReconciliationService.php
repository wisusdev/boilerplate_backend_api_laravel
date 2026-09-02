<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cruza el extracto que exporta el portal del banco contra lo que tenemos
 * registrado, para encontrar dinero que entró y no confirmamos.
 *
 * De solo lectura a propósito: esto NO mueve dinero. Produce un informe; quien
 * decide confirmar sigue pasando por `PaymentLinkService::confirm()`, el único
 * sitio autorizado a dar un cobro por bueno. Repartir esa autoridad sería volver
 * a abrir el hueco que la Fase 1 cerró.
 *
 * @see PAGO-ENLACE-BAC.md §4.D.3
 */
class PaymentReconciliationService
{
    /** Tolerancia al comparar importes del extracto contra el importe esperado. */
    private const EPSILON = 0.01;

    /** Días de margen para el emparejamiento por importe+fecha cuando no hay referencia en el texto. */
    private const VENTANA_DIAS = 3;

    /**
     * @param  array<int, array{fecha: Carbon, descripcion: string, monto: float}>  $filas
     * @return array{
     *   charged_not_confirmed: array<int, array<string, mixed>>,
     *   confirmed_not_in_statement: array<int, array<string, mixed>>,
     *   ambiguous_rows: array<int, array<string, mixed>>,
     *   unmatched_rows: array<int, array<string, mixed>>,
     *   unmatched_rows_total: int,
     *   date_from: ?string,
     *   date_to: ?string,
     *   rows_read: int,
     * }
     */
    public function reconcile(array $filas): array
    {
        if ($filas === []) {
            return [
                'charged_not_confirmed' => [], 'confirmed_not_in_statement' => [],
                'ambiguous_rows' => [], 'unmatched_rows' => [], 'unmatched_rows_total' => 0,
                'date_from' => null, 'date_to' => null, 'rows_read' => 0,
            ];
        }

        $fechas = array_map(fn ($f) => $f['fecha'], $filas);
        $desde = min($fechas);
        $hasta = max($fechas);

        $links = PaymentLink::query()
            ->with(['payment.payable.bookable', 'payment.payable.user'])
            ->get();

        $cargadoNoConfirmado = [];
        $ambiguas = [];
        $sinCoincidencia = [];
        $referenciasEncontradas = [];

        foreach ($filas as $fila) {
            $coincidencia = $this->emparejarPorReferencia($fila, $links);

            if ($coincidencia === null) {
                $candidatas = $this->emparejarPorImporteYFecha($fila, $links);

                if (count($candidatas) > 1) {
                    $ambiguas[] = $this->filaAmbigua($fila, $candidatas);

                    continue;
                }

                $coincidencia = $candidatas[0] ?? null;
            }

            if ($coincidencia === null) {
                $sinCoincidencia[] = $this->filaSinResolver($fila);

                continue;
            }

            $referenciasEncontradas[$coincidencia->id] = true;

            $pago = $coincidencia->payment;
            $cobrado = $pago && $pago->status === 'paid' && ! $pago->isVoided();

            if (! $cobrado) {
                $cargadoNoConfirmado[] = $this->filaEmparejada($fila, $coincidencia);
            }
        }

        $confirmadoAusente = Payment::query()
            ->with(['payable.bookable', 'payable.user', 'link'])
            ->where('gateway', 'bac_link')
            ->where('status', 'paid')
            ->whereNull('voided_at')
            ->whereBetween('paid_at', [$desde->copy()->subDay(), $hasta->copy()->addDay()])
            ->get()
            ->reject(fn (Payment $pago) => $pago->link && isset($referenciasEncontradas[$pago->link->id]))
            ->map(fn (Payment $pago) => $this->pagoSinRastro($pago))
            ->values()
            ->all();

        $totalSinCoincidencia = count($sinCoincidencia);
        $limite = 50;

        return [
            'charged_not_confirmed' => $cargadoNoConfirmado,
            'confirmed_not_in_statement' => $confirmadoAusente,
            'ambiguous_rows' => $ambiguas,
            // Capado a propósito: filas del extracto sin relación con ningún
            // enlace suelen ser actividad ajena a Cusgo (otros cobros del mismo
            // terminal). Se avisa cuántas se omiten para no fingir que se listó
            // todo el extracto.
            'unmatched_rows' => array_slice($sinCoincidencia, 0, $limite),
            'unmatched_rows_total' => $totalSinCoincidencia,
            'date_from' => $desde->toDateString(),
            'date_to' => $hasta->toDateString(),
            'rows_read' => count($filas),
        ];
    }

    /**
     * La referencia (`CG-000123-A7K4`) es la que el agente escribe a mano en la
     * descripción del enlace en el portal del banco: es la coincidencia más
     * confiable porque no depende de que el importe cuadre exacto.
     */
    private function emparejarPorReferencia(array $fila, Collection $links): ?PaymentLink
    {
        $texto = mb_strtoupper($fila['descripcion']);

        if ($texto === '') {
            return null;
        }

        foreach ($links as $link) {
            if ($link->reference !== '' && str_contains($texto, mb_strtoupper($link->reference))) {
                return $link;
            }
        }

        return null;
    }

    /**
     * Respaldo cuando el banco no conserva la referencia completa: mismo importe
     * dentro de una ventana razonable desde que se emitió el enlace.
     *
     * @return array<int, PaymentLink>
     */
    private function emparejarPorImporteYFecha(array $fila, Collection $links): array
    {
        return $links->filter(function (PaymentLink $link) use ($fila) {
            $pago = $link->payment;
            if (! $pago || abs((float) $pago->amount - $fila['monto']) > self::EPSILON) {
                return false;
            }

            $desde = $link->created_at->copy()->subDay();
            $hasta = $link->created_at->copy()->addDays(30 + self::VENTANA_DIAS);

            return $fila['fecha']->betweenIncluded($desde, $hasta);
        })->values()->all();
    }

    private function filaEmparejada(array $fila, PaymentLink $link): array
    {
        $booking = $link->booking();

        return [
            'reference' => $link->reference,
            'link_status' => $link->status,
            'payment_status' => $link->payment?->status,
            'expected_amount' => $link->payment?->amount,
            'statement_amount' => $fila['monto'],
            'statement_date' => $fila['fecha']->toDateString(),
            'statement_description' => $fila['descripcion'],
            'booking_id' => $booking?->id,
            'booking_title' => $booking?->bookable?->title,
            'customer_name' => $booking?->user?->name,
            'customer_email' => $booking?->user?->email,
        ];
    }

    private function pagoSinRastro(Payment $pago): array
    {
        $booking = $pago->payable;

        return [
            'reference' => $pago->link?->reference,
            'amount' => $pago->amount,
            'currency_code' => $pago->currency_code,
            'paid_at' => optional($pago->paid_at)->toDateTimeString(),
            'transaction_reference' => $pago->transaction_reference,
            'booking_id' => $booking?->id,
            'booking_title' => $booking?->bookable?->title,
            'customer_name' => $booking?->user?->name,
            'customer_email' => $booking?->user?->email,
        ];
    }

    private function filaAmbigua(array $fila, array $candidatas): array
    {
        return [
            'statement_amount' => $fila['monto'],
            'statement_date' => $fila['fecha']->toDateString(),
            'statement_description' => $fila['descripcion'],
            'candidate_references' => array_map(fn (PaymentLink $l) => $l->reference, $candidatas),
        ];
    }

    private function filaSinResolver(array $fila): array
    {
        return [
            'statement_amount' => $fila['monto'],
            'statement_date' => $fila['fecha']->toDateString(),
            'statement_description' => $fila['descripcion'],
        ];
    }
}
