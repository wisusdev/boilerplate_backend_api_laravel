<?php

namespace App\Services\Dte;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Dte\SvCatalogs;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Documentos de venta de una factura: Factura de consumidor final (01,
 * fe-f-v2.json) y Comprobante de Crédito Fiscal (03, fe-ccf-v4.json).
 *
 * La diferencia central:
 *  - la Factura declara los precios CON IVA incluido y el IVA por línea
 *    (ivaItem = ventaGravada × 13/113) y en totalIva; el tributo 20 no aplica
 *    (CAT-015): tributos va en null;
 *  - el CCF declara los precios SIN IVA (cobrado / 1.13) y el IVA como
 *    tributo 20 en el resumen; además lleva retención o percepción del 1 %.
 *
 * Los importes de vamosPues son lo que el cliente paga, IVA incluido.
 */
class FacturaBuilder
{
    public const TIPO_DTE = '01';

    public const TIPO_CCF = '03';

    public const VERSION = 2;

    private const IVA_RATE = DteParts::IVA_RATE;

    /** @return array<string, mixed> */
    public function build(Invoice $invoice, DteConfig $config, string $numeroControl, string $codigoGeneracion, CarbonInterface $now, ?string $tipo = null): array
    {
        $invoice->loadMissing(['items', 'booking.bookable', 'booking.payments']);
        $tipo ??= $invoice->dte_type ?: self::TIPO_DTE;
        $ccf = $tipo === self::TIPO_CCF;

        $cuerpo = [];
        $totalGravadaCents = 0;
        $totalIva = 0.0;

        foreach ($this->lines($invoice) as $line) {
            $grossCents = DteParts::cents($line['total']);
            $item = [
                'numItem' => count($cuerpo) + 1,
                'tipoItem' => 2,          // CAT-011: servicio
                'numeroDocumento' => null,
                'codigo' => null,
                'codTributo' => null,
                'descripcion' => mb_substr($line['description'], 0, 1000),
                'cantidad' => $line['quantity'],
                'uniMedida' => 99,        // CAT-014: otra (un servicio)
                'precioUni' => 0,
                'montoDescu' => 0,
                'ventaNoSuj' => 0,
                'ventaExenta' => 0,
                'ventaGravada' => 0,
                'tributos' => null,
                'psv' => 0,
                'noGravado' => 0,
            ];

            if ($ccf) {
                // Sin IVA: lo cobrado entre 1.13.
                $baseCents = (int) round($grossCents / (1 + self::IVA_RATE));
                $item['precioUni'] = round($baseCents / 100 / $line['quantity'], 8);
                $item['ventaGravada'] = $baseCents / 100;
                $item['tributos'] = ['20'];
                $totalGravadaCents += $baseCents;
            } else {
                $gross = $grossCents / 100;
                $iva = round($gross * self::IVA_RATE / (1 + self::IVA_RATE), 8);
                $item['precioUni'] = round($gross / $line['quantity'], 8);
                $item['ventaGravada'] = $gross;
                $item['ivaItem'] = $iva;
                $totalGravadaCents += $grossCents;
                $totalIva += $iva;
            }

            $cuerpo[] = $item;
        }

        $totalGravada = $totalGravadaCents / 100;

        if ($ccf) {
            $ivaCents = (int) round($totalGravadaCents * self::IVA_RATE);
            [$reteCents, $perciCents] = IvaAjuste::calcular($config, (bool) $invoice->receptor_agente_retencion, $totalGravadaCents);
            $montoCents = $totalGravadaCents + $ivaCents;
            $pagarCents = $montoCents - $reteCents + $perciCents;
            [$condicion, $pagos] = $this->pagos($invoice, $pagarCents);

            $resumen = [
                'totalNoSuj' => 0,
                'totalExenta' => 0,
                'totalGravada' => $totalGravada,
                'subTotalVentas' => $totalGravada,
                'descuNoSuj' => 0,
                'descuExenta' => 0,
                'descuGravada' => 0,
                'porcentajeDescuento' => 0,
                'totalDescu' => 0,
                'tributos' => $totalGravadaCents > 0
                    ? [['codigo' => '20', 'descripcion' => 'Impuesto al Valor Agregado 13%', 'valor' => $ivaCents / 100]]
                    : null,
                'subTotal' => $totalGravada,
                'ivaPerci' => $perciCents / 100,
                'ivaRete' => $reteCents / 100,
                'montoTotalOperacion' => $montoCents / 100,
                'totalNoGravado' => 0,
                'totalPagar' => $pagarCents / 100,
                'totalLetras' => AmountToWords::convert($pagarCents),
                'saldoFavor' => 0,
                'condicionOperacion' => $condicion,
                'pagos' => $pagos,
                'numPagoElectronico' => null,
                'observaciones' => DteParts::optional($invoice->notes, 1, 3000),
            ];
        } else {
            [$condicion, $pagos] = $this->pagos($invoice, $totalGravadaCents);
            $resumen = [
                'totalNoSuj' => 0,
                'totalExenta' => 0,
                'totalGravada' => $totalGravada,
                'subTotalVentas' => $totalGravada,
                'descuNoSuj' => 0,
                'descuExenta' => 0,
                'descuGravada' => 0,
                'porcentajeDescuento' => 0,
                'totalDescu' => 0,
                'tributos' => null,
                'subTotal' => $totalGravada,
                'ivaRete' => 0,
                'montoTotalOperacion' => $totalGravada,
                'totalNoGravado' => 0,
                'totalPagar' => $totalGravada,
                'totalLetras' => AmountToWords::convert($totalGravadaCents),
                'totalIva' => round($totalIva, 2),
                'saldoFavor' => 0,
                'condicionOperacion' => $condicion,
                'pagos' => $pagos,
                'numPagoElectronico' => null,
                'observaciones' => DteParts::optional($invoice->notes, 1, 3000),
            ];
        }

        return [
            'identificacion' => DteParts::identificacion($tipo, $config, $numeroControl, $codigoGeneracion, $now),
            'documentoRelacionado' => null,
            'emisor' => DteParts::emisor($config),
            'receptor' => $ccf ? self::receptorCcf($invoice) : $this->receptor($invoice),
            'otrosDocumentos' => null,
            'ventaTercero' => null,
            'cuerpoDocumento' => $cuerpo,
            'resumen' => $resumen,
            'apendice' => [[
                'campo' => 'factura',
                'etiqueta' => 'Factura interna',
                'valor' => $invoice->number,
            ]],
        ];
    }

    /**
     * Receptor del CCF: un contribuyente identificado por completo, con su
     * actividad y dirección en códigos de catálogo.
     *
     * @return array<string, mixed>
     */
    public static function receptorCcf(Invoice $invoice): array
    {
        return [
            'nit' => DteConfig::digits($invoice->receptor_document),
            'nrc' => DteParts::optional(DteConfig::digits($invoice->receptor_nrc), 2, 8),
            'nombre' => mb_substr(trim((string) $invoice->receptor_name), 0, 250),
            'codActividad' => trim((string) $invoice->receptor_cod_actividad),
            'descActividad' => mb_substr((string) SvCatalogs::actividad($invoice->receptor_cod_actividad), 0, 150),
            'nombreComercial' => DteParts::optional($invoice->receptor_nombre_comercial, 1, 150),
            'direccion' => DteParts::direccion(
                $invoice->receptor_departamento, $invoice->receptor_municipio,
                $invoice->receptor_distrito, $invoice->receptor_direccion,
            ),
            'telefono' => DteParts::optional($invoice->receptor_telefono, 8, 30),
            'correo' => DteParts::optional($invoice->receptor_email, 6, 100),
        ];
    }

    /**
     * Lo que falta o está mal en el receptor de un CCF. Se valida al guardar la
     * factura y otra vez antes de numerar: el MH rechazaría el documento.
     *
     * @return list<string>
     */
    public static function receptorCcfErrors(Invoice $invoice): array
    {
        $errors = [];
        if (! preg_match('/^([0-9]{14}|[0-9]{9})$/', DteConfig::digits($invoice->receptor_document))) {
            $errors[] = 'El NIT del cliente debe tener 9 o 14 dígitos.';
        }
        if (! preg_match('/^[0-9]{2,8}$/', DteConfig::digits($invoice->receptor_nrc))) {
            $errors[] = 'El NRC del cliente debe tener entre 2 y 8 dígitos.';
        }
        if (trim((string) $invoice->receptor_name) === '') {
            $errors[] = 'Falta el nombre o razón social del cliente.';
        }
        if (SvCatalogs::actividad($invoice->receptor_cod_actividad) === null) {
            $errors[] = 'La actividad económica del cliente no está en el catálogo CAT-019.';
        }

        return [...$errors, ...DteParts::direccionErrors(
            'del cliente', $invoice->receptor_departamento, $invoice->receptor_municipio,
            $invoice->receptor_distrito, $invoice->receptor_direccion,
        )];
    }

    /**
     * Conceptos de la factura. Una factura de reserva no tiene líneas propias:
     * se declara la reserva como un único servicio por su importe.
     *
     * @return list<array{description: string, quantity: int, total: float}>
     */
    private function lines(Invoice $invoice): array
    {
        if ($invoice->items->isNotEmpty()) {
            return $invoice->items
                ->filter(fn ($item) => (float) $item->total > 0)
                ->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity' => max((int) $item->quantity, 1),
                    'total' => (float) $item->total,
                ])->values()->all();
        }

        $booking = $invoice->booking;
        $titulo = $booking?->bookable?->title;
        $descripcion = $booking?->booking_type === Booking::TYPE_TRANSPORT
            ? 'Servicio de transporte privado — '.($titulo ?? 'Vehículo')
            : 'Tour — '.($titulo ?? 'Experiencia turística');
        if ($booking?->party_size > 1) {
            $descripcion .= " ({$booking->party_size} personas)";
        }

        return [['description' => $descripcion, 'quantity' => 1, 'total' => (float) $invoice->amount]];
    }

    /**
     * Consumidor final: las nueve claves siempre presentes y lo desconocido en
     * null. "Consumidor Final" no es un nombre. No se exige documento: la DGII
     * prohíbe pedirlo en ventas menores de $25,000; si el cliente lo dio, va.
     *
     * @return array<string, mixed>
     */
    private function receptor(Invoice $invoice): array
    {
        $nombre = trim((string) $invoice->receptor_name);
        if (strcasecmp($nombre, 'Consumidor Final') === 0) {
            $nombre = '';
        }

        [$tipoDoc, $numDoc] = self::documento($invoice->receptor_document_type, $invoice->receptor_document);

        return [
            'tipoDocumento' => $tipoDoc,
            'numDocumento' => $numDoc,
            'nrc' => null,
            'nombre' => DteParts::optional($nombre, 1, 250),
            'codActividad' => null,
            'descActividad' => null,
            'direccion' => null,
            'telefono' => null,
            'correo' => DteParts::optional($invoice->receptor_email, 6, 100),
        ];
    }

    /**
     * Tipo (CAT-022) y número de documento del receptor. Sin tipo explícito se
     * deduce de la forma: 9 dígitos es un DUI, 14 un NIT; lo demás, "Otro".
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function documento(?string $tipo, ?string $numero): array
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return [null, null];
        }

        $digits = DteConfig::digits($numero);
        if (! SvCatalogs::validTipoDocumento($tipo)) {
            $tipo = match (strlen($digits)) {
                9 => '13',
                14 => '36',
                default => '37',
            };
        }

        $numero = match ($tipo) {
            '13' => strlen($digits) === 9 ? substr($digits, 0, 8).'-'.substr($digits, 8) : $numero,
            '36' => $digits,
            default => $numero,
        };

        return [$tipo, mb_substr($numero, 0, 20)];
    }

    /**
     * Condición de la operación y formas de pago (CAT-017), tomadas de los
     * cobros reales de la reserva. Los montos suman exactamente totalPagar.
     *
     * @return array{0: int, 1: list<array<string, mixed>>}
     */
    private function pagos(Invoice $invoice, int $totalCents): array
    {
        /** @var Collection<int, Payment> $cobros */
        $cobros = ($invoice->booking?->payments ?? collect())
            ->filter(fn (Payment $p) => $p->status === 'paid' && ! $p->isVoided() && (float) $p->amount > 0)
            ->sortBy('id')
            ->values();

        if ($cobros->isEmpty()) {
            return [1, [self::pago('99', $totalCents, 'Otro')]];
        }

        $pagos = [];
        $restante = $totalCents;
        foreach ($cobros as $i => $cobro) {
            $cents = $i === $cobros->count() - 1
                ? $restante
                : min((int) round((float) $cobro->amount * 100), $restante);
            if ($cents <= 0) {
                continue;
            }
            [$codigo, $referencia] = self::formaPago($cobro->gateway, $cobro->method);
            $pagos[] = self::pago($codigo, $cents, $referencia);
            $restante -= $cents;
        }

        return [1, $pagos ?: [self::pago('99', $totalCents, 'Otro')]];
    }

    /**
     * Pasarela y método de la plataforma → CAT-017. "Tarjeta" sin más no dice
     * si es de débito o de crédito, así que va como 99 nombrando el medio.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function formaPago(?string $gateway, ?string $method): array
    {
        if (in_array($gateway, ['wompi', 'bac_link'], true)) {
            return ['99', 'Pago en línea'];
        }

        return match (mb_strtolower(trim((string) $method))) {
            'cash', 'efectivo' => ['01', null],
            'debit_card', 'debito', 'débito' => ['02', null],
            'credit_card', 'credito', 'crédito' => ['03', null],
            'transfer', 'transferencia', 'bank_transfer' => ['05', null],
            'card', 'tarjeta' => ['99', 'Tarjeta'],
            default => ['99', 'Otro'],
        };
    }

    /** @return array<string, mixed> */
    private static function pago(string $codigo, int $cents, ?string $referencia): array
    {
        return [
            'codigo' => $codigo,
            'montoPago' => $cents / 100,
            'referencia' => $referencia,
            'plazo' => null,
            'periodo' => null,
        ];
    }
}
