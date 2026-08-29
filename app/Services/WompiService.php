<?php

namespace App\Services;

use App\Models\Setting;
use App\Traits\EncryptsCredentials;
use App\Traits\ExternalConsumerServices;

/**
 * Integración con Wompi El Salvador (https://api.wompi.sv/swagger/v1/swagger.json).
 *
 * El cobro se hace con **enlace de pago** (`POST /EnlacePago`): Wompi aloja el
 * formulario y el cliente teclea ahí los datos de su tarjeta. El PAN y el CVV no
 * pasan por este servidor en ningún momento, que es lo que saca a la aplicación
 * del alcance de PCI DSS SAQ-D.
 *
 * El estado del pago se consulta siempre contra la API (`GET /EnlacePago/{id}` o
 * `GET /TransaccionCompra/{id}`); nunca se da por bueno lo que diga el navegador.
 */
class WompiService
{
    use EncryptsCredentials;
    use ExternalConsumerServices;

    protected string $base_url = 'https://api.wompi.sv';

    protected string $base_auth_url = 'https://id.wompi.sv';

    protected string $public_key;

    protected string $private_key;

    protected string $audience;

    public function __construct()
    {
        $pgRow = Setting::where('key', 'payment_gateway')->first();
        $pg = $pgRow ? json_decode($pgRow->value, true) : [];

        // Support both new flat keys and legacy nested structure
        $this->public_key = $this->decryptCredential($pg['wompi_public_key'] ?? $pg['payment_methods']['wompi']['key'] ?? config('services.wompi.public_key', ''));
        $this->private_key = $this->decryptCredential($pg['wompi_private_key'] ?? $pg['payment_methods']['wompi']['secret'] ?? config('services.wompi.private_key', ''));
        $this->audience = $this->decryptCredential($pg['wompi_audience'] ?? config('services.wompi.audience', 'wompi_api'));
    }

    public function isConfigured(): bool
    {
        return $this->public_key !== '' && $this->private_key !== '';
    }

    private function getToken(): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Wompi no está configurado: faltan las llaves de la aplicación.');
        }

        $response = $this->makeRequest(
            'POST',
            $this->base_auth_url.'/connect/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->public_key,
                'client_secret' => $this->private_key,
                // El servidor de identidad de Wompi es IdentityServer: espera
                // `scope`. Se mantiene `audience` por compatibilidad.
                'scope' => 'wompi_api',
                'audience' => $this->audience !== '' ? $this->audience : 'wompi_api',
            ],
            [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );

        $data = json_decode($response, true) ?: [];

        if (empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'no se pudo autenticar';
            throw new \RuntimeException('Wompi: '.$error);
        }

        return (string) $data['access_token'];
    }

    /**
     * @param  array<string,mixed>  $body
     * @return array<string,mixed>
     */
    private function call(string $method, string $path, array $body = []): array
    {
        $response = $this->makeRequest(
            $method,
            $this->base_url.$path,
            $body,
            [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.$this->getToken(),
            ],
            true
        );

        return json_decode($response, true) ?: [];
    }

    /**
     * Crea un enlace de pago alojado por Wompi.
     *
     * @param  array{reference:string, amount:float, product:string, description?:string, redirect_url:string, webhook_url?:string, return_url?:string, extra?:array<string,mixed>}  $data
     * @return array{link_id: string, url: string, qr_url: string}
     */
    public function createPaymentLink(array $data): array
    {
        $body = [
            'identificadorEnlaceComercio' => $data['reference'],
            'monto' => round((float) $data['amount'], 2),
            'nombreProducto' => mb_substr($data['product'], 0, 100),
            'formaPago' => [
                'permitirTarjetaCreditoDebido' => true,
                'permitirPagoConPuntoAgricola' => false,
                'permitirPagoEnCuotasAgricola' => false,
                'permitirPagoEnBitcoin' => false,
            ],
            'infoProducto' => [
                'descripcionProducto' => mb_substr($data['description'] ?? $data['product'], 0, 250),
            ],
            'configuracion' => [
                'urlRedirect' => $data['redirect_url'],
                // El importe y la cantidad NO son editables: si lo fueran, el
                // cliente podría pagar menos de lo que cuesta su reserva.
                'esMontoEditable' => false,
                'esCantidadEditable' => false,
                'cantidadPorDefecto' => 1,
                'duracionInterfazIntentoMinutos' => 30,
                'notificarTransaccionCliente' => true,
            ],
            // Un enlace, un cobro: evita que el mismo enlace se reutilice.
            'limitesDeUso' => [
                'cantidadMaximaPagosExitosos' => 1,
            ],
            'vigencia' => [
                'fechaInicio' => now()->toIso8601String(),
                'fechaFin' => now()->addDays(7)->toIso8601String(),
            ],
            'datosAdicionales' => $data['extra'] ?? [],
        ];

        if (! empty($data['webhook_url'])) {
            $body['configuracion']['urlWebhook'] = $data['webhook_url'];
        }

        if (! empty($data['return_url'])) {
            $body['configuracion']['urlRetorno'] = $data['return_url'];
        }

        $response = $this->call('POST', '/EnlacePago', $body);

        $linkId = $response['idEnlace'] ?? null;
        $url = $response['urlEnlace'] ?? $response['urlEnlaceLargo'] ?? null;

        if (! $linkId || ! $url) {
            throw new \RuntimeException('Wompi: no se pudo crear el enlace de pago.');
        }

        return [
            'link_id' => (string) $linkId,
            'url' => (string) $url,
            'qr_url' => (string) ($response['urlQrCodeEnlace'] ?? ''),
        ];
    }

    /**
     * Consulta un enlace de pago y el resultado de su transacción, si ya la hubo.
     *
     * @return array{paid: bool, amount: float, transaction_id: ?string, external_id: ?string, message: ?string}
     */
    public function getPaymentLinkResult(string $linkId): array
    {
        return $this->readTransaction($this->call('GET', '/EnlacePago/'.$linkId)['transaccionCompra'] ?? []);
    }

    /**
     * Consulta una transacción por su identificador.
     *
     * @return array{paid: bool, amount: float, transaction_id: ?string, external_id: ?string, message: ?string}
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->readTransaction($this->call('GET', '/TransaccionCompra/'.$transactionId));
    }

    /**
     * Normaliza la respuesta de una transacción de compra de Wompi.
     *
     * @param  array<string,mixed>  $t
     * @return array{paid: bool, amount: float, transaction_id: ?string, external_id: ?string, message: ?string}
     */
    private function readTransaction(array $t): array
    {
        return [
            'paid' => filter_var($t['esAprobada'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'amount' => (float) ($t['monto'] ?? 0),
            'transaction_id' => isset($t['idTransaccion']) ? (string) $t['idTransaccion'] : null,
            'external_id' => isset($t['idExterno']) ? (string) $t['idExterno'] : null,
            'message' => isset($t['mensaje']) ? (string) $t['mensaje'] : null,
        ];
    }

    public function getRegion(): object
    {
        return (object) $this->call('GET', '/api/Regiones');
    }
}
