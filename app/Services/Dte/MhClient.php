<?php

namespace App\Services\Dte;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente de la API del Sistema de Transmisión del Ministerio de Hacienda.
 *
 * La Guía de Integración que describe estas llamadas no está publicada en
 * factura.gob.sv; los contratos siguen la práctica de integración vigente y
 * deben confirmarse en apitest antes de pasar a producción.
 */
class MhClient
{
    private const API_TEST = 'https://apitest.dtes.mh.gob.sv';

    private const API_PROD = 'https://api.dtes.mh.gob.sv';

    /** El MH da 24 h en producción y 48 h en pruebas; se renueva antes. */
    private const TOKEN_TTL = 23 * 3600;

    /** Hay un cajero (o un cliente) esperando al otro lado. */
    private const TIMEOUT = 10;

    private readonly string $ambiente;

    /**
     * Un documento se transmite siempre al ambiente en que se firmó, aunque
     * después se haya cambiado el ajuste: un DTE de pruebas no va a producción.
     */
    public function __construct(private readonly DteConfig $config, ?string $ambiente = null)
    {
        $this->ambiente = $ambiente ?? $config->ambiente();
    }

    /**
     * POST /fesv/recepciondte con el documento firmado.
     *
     * @throws MhUnavailableException sin respuesta útil (red, timeout, 5xx)
     */
    public function recepcion(string $tipoDte, int $version, string $codigoGeneracion, string $jws, int $idEnvio): MhResult
    {
        return $this->post('/fesv/recepciondte', [
            'ambiente' => $this->ambiente,
            'idEnvio' => $idEnvio,
            'version' => $version,
            'tipoDte' => $tipoDte,
            'documento' => $jws,
            'codigoGeneracion' => $codigoGeneracion,
        ]);
    }

    /**
     * Pregunta al MH si ya tiene un documento. Un 4xx significa "no lo tengo".
     *
     * @throws MhUnavailableException
     */
    public function consulta(string $tipoDte, string $codigoGeneracion): ?MhResult
    {
        try {
            return $this->post('/fesv/recepcion/consultadte/', [
                'nitEmisor' => $this->config->nit(),
                'tdte' => $tipoDte,
                'codigoGeneracion' => $codigoGeneracion,
            ]);
        } catch (MhUnavailableException $e) {
            if ($e->status !== null && $e->status < 500) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * POST /fesv/anulardte con el evento de invalidación firmado (esquema v3).
     *
     * @throws MhUnavailableException
     */
    public function anular(string $jws, int $idEnvio): MhResult
    {
        return $this->post('/fesv/anulardte', [
            'ambiente' => $this->ambiente,
            'idEnvio' => $idEnvio,
            'version' => 3,
            'documento' => $jws,
        ]);
    }

    /**
     * POST /fesv/contingencia con el evento firmado (esquema v4). Responde
     * {estado: RECIBIDO | RECHAZADO, selloRecibido, mensaje, observaciones}.
     *
     * @return array<string, mixed>
     *
     * @throws MhUnavailableException
     */
    public function contingencia(string $jws): array
    {
        return $this->call('POST', '/fesv/contingencia', ['nit' => $this->config->nit(), 'documento' => $jws])['body'];
    }

    /**
     * POST /fesv/recepcionlote/ con los DTE firmados tal como se generaron.
     *
     * @param  list<string>  $documentos
     * @return array<string, mixed> {codigoLote, …}
     *
     * @throws MhUnavailableException
     */
    public function lote(array $documentos): array
    {
        return $this->call('POST', '/fesv/recepcionlote/', [
            'ambiente' => $this->ambiente,
            'idEnvio' => strtoupper((string) Str::uuid()),
            // El campo version del lote no está en los manuales publicados: confirmar en apitest.
            'version' => 2,
            'nitEmisor' => $this->config->nit(),
            'documentos' => $documentos,
        ])['body'];
    }

    /**
     * GET /fesv/recepcion/consultadtelote/{codigoLote}.
     *
     * @return array<string, mixed> {procesados: [...], rechazados: [...]}
     *
     * @throws MhUnavailableException
     */
    public function consultaLote(string $codigoLote): array
    {
        return $this->call('GET', '/fesv/recepcion/consultadtelote/'.rawurlencode($codigoLote))['body'];
    }

    /** @param  array<string, mixed>  $payload */
    private function post(string $path, array $payload): MhResult
    {
        $response = $this->call('POST', $path, $payload);
        $body = $response['body'];

        if (($body['estado'] ?? '') === '' && ($body['selloRecibido'] ?? '') === '') {
            throw new MhUnavailableException(
                'Respuesta del MH no reconocida (HTTP '.$response['status'].'): '.mb_substr($response['raw'], 0, 300),
                $response['status'],
            );
        }

        return MhResult::fromResponse($body);
    }

    /**
     * Llamada autenticada. Un 401/403 invalida el token y se reintenta una vez.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{status: int, body: array<string, mixed>, raw: string}
     *
     * @throws MhUnavailableException sin respuesta útil: red, timeout, 5xx o un cuerpo que no es JSON
     */
    private function call(string $method, string $path, ?array $payload = null, bool $retried = false): array
    {
        $token = $this->token();

        try {
            $request = Http::timeout(self::TIMEOUT)->withHeaders(['Authorization' => $token])->acceptJson();
            $response = $method === 'GET'
                ? $request->get($this->baseUrl().$path)
                : $request->post($this->baseUrl().$path, $payload ?? []);
        } catch (ConnectionException $e) {
            throw new MhUnavailableException('El MH no respondió: '.$e->getMessage());
        }

        // Token vencido o revocado antes de tiempo: se reautentica una vez.
        if (in_array($response->status(), [401, 403], true) && ! $retried) {
            Cache::forget($this->tokenKey());

            return $this->call($method, $path, $payload, true);
        }

        $body = $response->json();
        if ($response->serverError() || ! is_array($body)) {
            throw new MhUnavailableException(
                'Respuesta del MH no reconocida (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 300),
                $response->status(),
            );
        }

        return ['status' => $response->status(), 'body' => $body, 'raw' => $response->body()];
    }

    /** Token "Bearer …", en caché por ambiente y usuario. */
    private function token(): string
    {
        return Cache::remember($this->tokenKey(), self::TOKEN_TTL, function () {
            $password = $this->config->mhPassword();
            if ($password === '') {
                throw new RuntimeException('Falta la contraseña de la API del Ministerio de Hacienda.');
            }

            try {
                $response = Http::timeout(self::TIMEOUT)->asForm()->acceptJson()
                    ->post($this->baseUrl().'/seguridad/auth', [
                        'user' => $this->config->mhUser(),
                        'pwd' => $password,
                    ]);
            } catch (ConnectionException $e) {
                throw new MhUnavailableException('El MH no respondió a la autenticación: '.$e->getMessage());
            }

            if ($response->serverError()) {
                throw new MhUnavailableException('El MH no respondió a la autenticación (HTTP '.$response->status().').', $response->status());
            }

            $token = (string) $response->json('body.token', '');
            if (strcasecmp((string) $response->json('status'), 'OK') !== 0 || $token === '') {
                $detalle = trim($response->json('body.codigo', '').' '.$response->json('body.mensaje', ''));
                throw new RuntimeException('El MH rechazó las credenciales'.($detalle !== '' ? ": {$detalle}" : '.'));
            }

            return str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
        });
    }

    /** Al pasar de pruebas a producción no se reutiliza el token. */
    private function tokenKey(): string
    {
        return 'dte:mh-token:'.$this->ambiente.':'.$this->config->mhUser();
    }

    private function baseUrl(): string
    {
        return $this->ambiente === DteConfig::AMBIENTE_PRODUCCION ? self::API_PROD : self::API_TEST;
    }
}
