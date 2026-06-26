<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio de Facturación Electrónica — El Salvador (Ministerio de Hacienda)
 *
 * Implementa el flujo DTE para:
 *   - Tipo 03: Factura Consumidor Final
 *
 * Endpoints MH:
 *   Test:       https://apitest.dtes.mh.gob.sv
 *   Producción: https://api.dtes.mh.gob.sv
 *
 * Flujo:
 *   1. Autenticarse en /seguridad/auth  → bearer token
 *   2. Construir el JSON del DTE
 *   3. Firmar con llave privada del .p12
 *   4. Enviar a /fesv/recepciondte
 *   5. Procesar respuesta y actualizar Invoice
 */
class DteService
{
    private const API_TEST = 'https://apitest.dtes.mh.gob.sv';
    private const API_PROD = 'https://api.dtes.mh.gob.sv';

    // Ambientes MH
    private const ENV_TEST = '00';
    private const ENV_PROD = '01';

    // Código para Factura Consumidor Final
    private const TIPO_FACTURA_CF = '03';

    // ─── Config ──────────────────────────────────────────────────────────────

    private function config(): array
    {
        $row = Setting::where('key', 'dte')->first();
        return json_decode($row?->value ?? '{}', true) ?? [];
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->config()['dte_enabled'] ?? false);
    }

    private function baseUrl(): string
    {
        $cfg = $this->config();
        return ($cfg['dte_environment'] ?? 'test') === 'production' ? self::API_PROD : self::API_TEST;
    }

    private function mhEnvironment(): string
    {
        $cfg = $this->config();
        return ($cfg['dte_environment'] ?? 'test') === 'production' ? self::ENV_PROD : self::ENV_TEST;
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    /**
     * Obtiene el token bearer del MH.
     */
    private function authenticate(): string
    {
        $cfg = $this->config();

        $response = Http::timeout(15)->post("{$this->baseUrl()}/seguridad/auth", [
            'user' => $cfg['dte_mh_user'] ?? '',
            'pwd'  => $cfg['dte_mh_password'] ?? '',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Error autenticando con MH: {$response->status()} — {$response->body()}");
        }

        $body = $response->json('body');
        $token = $body['token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('MH no devolvió token de autenticación.');
        }

        return $token;
    }

    // ─── Signing ─────────────────────────────────────────────────────────────

    /**
     * Firma el JSON del DTE con la llave privada del certificado .p12.
     * Devuelve el DTE con la firma digital añadida.
     */
    private function sign(array $dteJson): string
    {
        $cfg = $this->config();

        $certPath     = $cfg['dte_cert_path'] ?? '';
        $certPassword = $cfg['dte_cert_password'] ?? '';

        if (! $certPath || ! Storage::disk('local')->exists($certPath)) {
            throw new \RuntimeException("Certificado DTE no encontrado en: {$certPath}");
        }

        $certContent = Storage::disk('local')->get($certPath);

        if (! openssl_pkcs12_read($certContent, $certs, $certPassword)) {
            throw new \RuntimeException('No se pudo leer el certificado .p12. Verifique la contraseña.');
        }

        $documentJson = json_encode($dteJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // SHA256 hash del documento
        $hash = hash('sha256', $documentJson);

        // Firma con llave privada
        openssl_sign($hash, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signature);

        // Certificado público en base64
        $certBase64 = base64_encode($certs['cert']);

        // Estructura firmada que espera el MH
        return json_encode([
            'nit'         => $cfg['dte_nit'] ?? '',
            'activo'      => true,
            'passwordPri' => $certPassword,
            'dtes'        => [
                array_merge($dteJson, [
                    'firmaElectronica' => $signatureBase64,
                    'certificado'      => $certBase64,
                ]),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ─── DTE JSON builder (Tipo 03 — Factura Consumidor Final) ────────────────

    private function buildNumeroControl(string $seriePoint, int $sequence): string
    {
        $cfg = $this->config();
        $codEstablec   = str_pad($cfg['dte_cod_establec']    ?? 'M001', 4, '0', STR_PAD_LEFT);
        $codPuntoVenta = str_pad($cfg['dte_cod_punto_venta'] ?? 'P001', 4, '0', STR_PAD_LEFT);
        $seq           = str_pad((string) $sequence, 15, '0', STR_PAD_LEFT);

        return "DTE-{$seriePoint}-{$codEstablec}{$codPuntoVenta}-{$seq}";
    }

    private function calcularIva(float $total): array
    {
        // El IVA en El Salvador es 13% sobre la base imponible.
        // Precio con IVA incluido: base * 1.13 = total
        $base = round($total / 1.13, 2);
        $iva  = round($total - $base, 2);

        // Ajuste de redondeo
        if (($base + $iva) !== $total) {
            $iva = round($total - $base, 2);
        }

        return ['base' => $base, 'iva' => $iva, 'total' => $total];
    }

    private function numeroALetras(float $amount): string
    {
        $entero   = (int) $amount;
        $decimals = round(($amount - $entero) * 100);

        $ones = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
                 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
                 'DIECIOCHO', 'DIECINUEVE'];
        $tens = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $hundreds = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                     'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $convertGroup = function (int $n) use ($ones, $tens, $hundreds): string {
            if ($n === 0) return '';
            if ($n === 100) return 'CIEN';
            $result = '';
            if ($n >= 100) {
                $result .= $hundreds[(int) ($n / 100)] . ' ';
                $n %= 100;
            }
            if ($n < 20) {
                $result .= $ones[$n];
            } else {
                $result .= $tens[(int) ($n / 10)];
                if ($n % 10 > 0) $result .= ' Y ' . $ones[$n % 10];
            }
            return trim($result);
        };

        $words = '';
        if ($entero >= 1000) {
            $miles = (int) ($entero / 1000);
            $words .= ($miles === 1 ? 'MIL' : $convertGroup($miles) . ' MIL');
            $entero %= 1000;
            if ($entero > 0) $words .= ' ';
        }
        $words .= $convertGroup($entero);

        return trim("{$words} DÓLARES CON {$decimals}/100");
    }

    public function buildFacturaCF(Invoice $invoice, Booking $booking): array
    {
        $cfg  = $this->config();
        $now  = Carbon::now();
        $uuid = (string) Str::uuid();

        // Sequence number for this invoice
        $sequence = $invoice->id;

        $ivaData = $this->calcularIva((float) $invoice->amount);

        // Descripción del servicio según tipo de booking
        $descripcion = $booking->booking_type === Booking::TYPE_TRANSPORT
            ? 'Servicio de transporte privado — ' . ($booking->bookable?->title ?? 'Vehículo')
            : 'Tour — ' . ($booking->bookable?->title ?? 'Experiencia turística');

        return [
            'identificacion' => [
                'version'           => 1,
                'ambiente'          => $this->mhEnvironment(),
                'tipoDte'           => self::TIPO_FACTURA_CF,
                'numeroControl'     => $this->buildNumeroControl(self::TIPO_FACTURA_CF, $sequence),
                'codigoGeneracion'  => strtoupper($uuid),
                'tipoModelo'        => 1,    // Modelo de facturación diferida
                'tipoOperacion'     => 1,    // Transmisión normal
                'tipoContingencia'  => null,
                'motivoContigencia' => null,
                'fecEmi'            => $now->toDateString(),
                'horEmi'            => $now->format('H:i:s'),
                'tipoMoneda'        => 'USD',
            ],
            'documentoRelacionado' => null,
            'emisor' => [
                'nit'               => $cfg['dte_nit'] ?? '',
                'nrc'               => $cfg['dte_nrc'] ?? '',
                'nombre'            => $cfg['dte_nombre'] ?? '',
                'codActividad'      => $cfg['dte_cod_actividad'] ?? '',
                'descActividad'     => $cfg['dte_desc_actividad'] ?? '',
                'nombreComercial'   => $cfg['dte_nombre_comercial'] ?? null,
                'tipoEstablecimiento' => '02',   // Casa Matriz
                'direccion' => [
                    'departamento'  => $cfg['dte_departamento'] ?? '06',
                    'municipio'     => $cfg['dte_municipio'] ?? '23',
                    'complemento'   => $cfg['dte_direccion'] ?? '',
                ],
                'telefono'          => $cfg['dte_telefono'] ?? '',
                'correo'            => $cfg['dte_correo'] ?? '',
                'codEstablecMH'     => null,
                'codEstablec'       => $cfg['dte_cod_establec'] ?? 'M001',
                'codPuntoVentaMH'   => null,
                'codPuntoVenta'     => $cfg['dte_cod_punto_venta'] ?? 'P001',
            ],
            'receptor' => [
                'tipoDocumento' => $invoice->receptor_document ? '13' : null,  // 13 = DUI
                'numDocumento'  => $invoice->receptor_document,
                'nrc'           => null,
                'nombre'        => $invoice->receptor_name ?: 'Consumidor Final',
                'codActividad'  => null,
                'descActividad' => null,
                'direccion'     => null,
                'telefono'      => null,
                'correo'        => $invoice->receptor_email,
            ],
            'ventaTercero'     => null,
            'cuerpoDocumento'  => [
                [
                    'numItem'        => 1,
                    'tipoItem'       => 2,      // 2 = Servicio
                    'numeroDocumento' => null,
                    'cantidad'       => (int) ($booking->party_size ?? 1),
                    'codigo'         => null,
                    'codTributo'     => null,
                    'uniMedida'      => 99,     // 99 = Otras unidades
                    'descripcion'    => $descripcion,
                    'precioUni'      => round($ivaData['base'] / max($booking->party_size ?? 1, 1), 2),
                    'montoDescu'     => 0,
                    'ventaNoSuj'     => 0,
                    'ventaExenta'    => 0,
                    'ventaGravada'   => $ivaData['base'],
                    'tributos'       => ['20'],  // 20 = IVA
                    'psv'            => 0,
                    'noGravado'      => 0,
                ],
            ],
            'resumen' => [
                'totalNoSuj'          => 0,
                'totalExenta'         => 0,
                'totalGravada'        => $ivaData['base'],
                'subTotalVentas'      => $ivaData['base'],
                'descuEnLinea'        => 0,
                'descuPorcentaje'     => 0,
                'totalDescu'          => 0,
                'tributos'            => [
                    [
                        'codigo'      => '20',
                        'descripcion' => 'Impuesto al Valor Agregado 13%',
                        'valor'       => $ivaData['iva'],
                    ],
                ],
                'subTotal'            => $ivaData['base'],
                'ivaPerci1'           => 0,
                'ivaRete1'            => 0,
                'reteRenta'           => 0,
                'montoTotalOperacion' => (float) $invoice->amount,
                'totalLetras'         => $this->numeroALetras((float) $invoice->amount),
                'totalIva'            => $ivaData['iva'],
                'saldoFavor'          => 0,
                'condicionOperacion'  => 1,     // 1 = Contado
                'pagos'               => [
                    [
                        'codigo'     => '01',   // 01 = Billetes y monedas
                        'montoPago'  => (float) $invoice->amount,
                        'referencia' => null,
                        'plazo'      => null,
                        'periodo'    => null,
                    ],
                ],
                'numPagoElectronico'  => null,
            ],
            'extension' => null,
            'apendice'  => null,
        ];
    }

    // ─── Send to MH ──────────────────────────────────────────────────────────

    private function send(string $signedPayload, string $token, string $tipoDte): array
    {
        $response = Http::timeout(30)
            ->withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl()}/fesv/recepciondte", [
                'ambiente'  => $this->mhEnvironment(),
                'idEnvio'   => 1,
                'version'   => 1,
                'tipoDte'   => $tipoDte,
                'documento' => $signedPayload,
            ]);

        return [
            'status'       => $response->status(),
            'body'         => $response->json() ?? [],
            'raw'          => $response->body(),
            'successful'   => $response->successful(),
        ];
    }

    // ─── Main public method ───────────────────────────────────────────────────

    /**
     * Genera y envía el DTE para una factura.
     * Actualiza la factura con el resultado de MH.
     */
    public function processDte(Invoice $invoice): Invoice
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('La facturación electrónica no está habilitada en la configuración.');
        }

        if (! $invoice->canGenerateDte()) {
            throw new \RuntimeException("El DTE no puede generarse en el estado actual: {$invoice->dte_status}");
        }

        $invoice->loadMissing(['booking.bookable']);
        $booking = $invoice->booking;

        if (! $booking) {
            throw new \RuntimeException('La factura no tiene un booking asociado.');
        }

        $invoice->update(['dte_status' => Invoice::DTE_GENERATING]);

        try {
            // 1. Build DTE JSON
            $dteJson = $this->buildFacturaCF($invoice, $booking);

            // 2. Authenticate with MH
            $token = $this->authenticate();

            // 3. Sign document
            $signedPayload = $this->sign($dteJson);

            // 4. Send to MH
            $result = $this->send($signedPayload, $token, self::TIPO_FACTURA_CF);

            // 5. Process response
            $mhBody   = $result['body'];
            $accepted = $result['successful'] && ($mhBody['estado'] ?? '') === 'PROCESADO';
            $sello    = $mhBody['selloRecibido'] ?? null;

            $updates = [
                'dte_json'            => $dteJson,
                'mh_response'         => $mhBody,
                'dte_type'            => self::TIPO_FACTURA_CF,
                'dte_generation_code' => $dteJson['identificacion']['codigoGeneracion'],
                'dte_number'          => $dteJson['identificacion']['numeroControl'],
                'dte_environment'     => $this->mhEnvironment(),
                'dte_submitted_at'    => now(),
            ];

            if ($accepted) {
                $updates['dte_status']      = Invoice::DTE_ACCEPTED;
                $updates['dte_seal']        = $sello;
                $updates['status']          = 'issued';
                $updates['dte_accepted_at'] = now();
            } else {
                $updates['dte_status'] = Invoice::DTE_REJECTED;
                Log::warning('DTE rechazado por MH', ['invoice_id' => $invoice->id, 'response' => $mhBody]);
            }

            $invoice->update($updates);

        } catch (\Throwable $e) {
            Log::error('Error generando DTE', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            $invoice->update([
                'dte_status'  => Invoice::DTE_ERROR,
                'mh_response' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }

        return $invoice->fresh();
    }

    /**
     * Genera el DTE JSON sin enviarlo (útil para preview o firma manual).
     */
    public function previewDte(Invoice $invoice): array
    {
        $invoice->loadMissing(['booking.bookable']);

        if (! $invoice->booking) {
            throw new \RuntimeException('La factura no tiene booking asociado.');
        }

        return $this->buildFacturaCF($invoice, $invoice->booking);
    }

    /**
     * Sube el certificado .p12 al storage privado y actualiza la config.
     */
    public function uploadCertificate(string $tempPath, string $password): string
    {
        // Validate certificate before saving
        $content = file_get_contents($tempPath);
        if (! openssl_pkcs12_read($content, $certs, $password)) {
            throw new \RuntimeException('El archivo .p12 no es válido o la contraseña es incorrecta.');
        }

        $storagePath = 'dte/certificate.p12';
        Storage::disk('local')->put($storagePath, $content);

        return $storagePath;
    }
}
