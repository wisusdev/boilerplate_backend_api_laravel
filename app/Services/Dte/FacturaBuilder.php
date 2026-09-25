<?php

namespace App\Services\Dte;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Dte\SvCatalogs;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Factura de consumidor final (tipo 01, esquema fe-f-v2.json).
 *
 * La Factura declara los precios CON IVA incluido y el IVA por línea
 * (ivaItem = ventaGravada × 13/113) y en totalIva. El tributo 20 no aplica a
 * la Factura (CAT-015): tributos va en null. Es el CCF (03) el que declara
 * precios sin IVA y el IVA como tributo 20.
 *
 * Los importes de vamosPues son lo que el cliente paga, IVA incluido.
 */
class FacturaBuilder
{
    public const TIPO_DTE = '01';

    public const VERSION = 2;

    private const IVA_RATE = 0.13;

    /** @return array<string, mixed> */
    public function build(Invoice $invoice, DteConfig $config, string $numeroControl, string $codigoGeneracion, CarbonInterface $now): array
    {
        $invoice->loadMissing(['items', 'booking.bookable', 'booking.payments']);
        $now = $now->copy()->setTimezone('America/El_Salvador');

        $cuerpo = [];
        $totalGravadaCents = 0;
        $totalIva = 0.0;

        foreach ($this->lines($invoice) as $line) {
            $grossCents = (int) round($line['total'] * 100);
            $gross = $grossCents / 100;
            $iva = round($gross * self::IVA_RATE / (1 + self::IVA_RATE), 8);

            $cuerpo[] = [
                'numItem' => count($cuerpo) + 1,
                'tipoItem' => 2,          // CAT-011: servicio
                'numeroDocumento' => null,
                'codigo' => null,
                'codTributo' => null,
                'descripcion' => mb_substr($line['description'], 0, 1000),
                'cantidad' => $line['quantity'],
                'uniMedida' => 99,        // CAT-014: otra (un servicio)
                'precioUni' => round($gross / $line['quantity'], 8),
                'montoDescu' => 0,
                'ventaNoSuj' => 0,
                'ventaExenta' => 0,
                'ventaGravada' => $gross,
                'tributos' => null,
                'psv' => 0,
                'noGravado' => 0,
                'ivaItem' => $iva,
            ];

            $totalGravadaCents += $grossCents;
            $totalIva += $iva;
        }

        $totalGravada = $totalGravadaCents / 100;
        [$condicion, $pagos] = $this->pagos($invoice, $totalGravadaCents);

        return [
            'identificacion' => [
                'version' => self::VERSION,
                'ambiente' => $config->ambiente(),
                'tipoDte' => self::TIPO_DTE,
                'numeroControl' => $numeroControl,
                'codigoGeneracion' => $codigoGeneracion,
                'tipoModelo' => 1,        // CAT-003: modelo previo
                'tipoOperacion' => 1,     // CAT-004: transmisión normal
                'tipoContingencia' => null,
                'motivoContin' => null,
                'fecEmi' => $now->format('Y-m-d'),
                'horEmi' => $now->format('H:i:s'),
                'tipoMoneda' => 'USD',
            ],
            'documentoRelacionado' => null,
            'emisor' => $this->emisor($config),
            'receptor' => $this->receptor($invoice),
            'otrosDocumentos' => null,
            'ventaTercero' => null,
            'cuerpoDocumento' => $cuerpo,
            'resumen' => [
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
                'observaciones' => self::optional($invoice->notes, 1, 3000),
            ],
            'apendice' => [[
                'campo' => 'factura',
                'etiqueta' => 'Factura interna',
                'valor' => $invoice->number,
            ]],
        ];
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

    /** @return array<string, mixed> */
    private function emisor(DteConfig $config): array
    {
        return [
            'nit' => $config->nit(),
            'nrc' => $config->nrc(),
            'nombre' => mb_substr($config->nombre(), 0, 250),
            'codActividad' => $config->codActividad(),
            'descActividad' => mb_substr($config->descActividad(), 0, 150),
            'nombreComercial' => self::optional($config->nombreComercial(), 1, 150),
            'direccion' => [
                'departamento' => $config->departamento(),
                'municipio' => $config->municipio(),
                'distrito' => $config->distrito(),
                'complemento' => mb_substr($config->direccion(), 0, 200),
            ],
            'telefono' => mb_substr($config->telefono(), 0, 30),
            'correo' => mb_substr($config->correo(), 0, 100),
            'codEstable' => SvCatalogs::establecimientoLetter($config->tipoEstablecimiento()).$config->codEstable(),
            'codPuntoVenta' => 'P'.$config->codPuntoVenta(),
        ];
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
            'nombre' => self::optional($nombre, 1, 250),
            'codActividad' => null,
            'descActividad' => null,
            'direccion' => null,
            'telefono' => null,
            'correo' => self::optional($invoice->receptor_email, 6, 100),
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

    /** null si está vacío o no llega al mínimo del esquema; recortado al máximo. */
    private static function optional(?string $value, int $min, int $max): ?string
    {
        $value = trim((string) $value);

        return mb_strlen($value) < $min ? null : mb_substr($value, 0, $max);
    }
}
