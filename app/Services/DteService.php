<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Dte\DteConfig;
use App\Services\Dte\DteException;
use App\Services\Dte\DteSigner;
use App\Services\Dte\FacturaBuilder;
use App\Services\Dte\MhClient;
use App\Services\Dte\MhResult;
use App\Services\Dte\MhUnavailableException;
use App\Support\Dte\SvCatalogs;
use App\Traits\EncryptsCredentials;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Facturación electrónica — El Salvador (Ministerio de Hacienda).
 *
 * Emite la Factura de consumidor final (01, esquema v2). Un DTE solo tiene
 * validez con el sello de recepción del MH, así que el diseño gira en torno a
 * no perder un sello y no duplicar un documento:
 *
 *  1. Se valida el emisor y se carga la llave ANTES de numerar: un documento
 *     que el MH rechazaría por datos incompletos no gasta un correlativo.
 *  2. Se numera con bloqueo (correlativo por tipo y ambiente), se construye y
 *     se firma (JWS RS512) dentro de una transacción que bloquea la factura.
 *  3. Se envía. Solo cuenta el sello; un rechazo se guarda con su motivo; sin
 *     respuesta, el documento firmado se conserva y el siguiente intento
 *     reenvía ese mismo documento, preguntando antes al MH si ya lo tiene.
 */
class DteService
{
    use EncryptsCredentials;

    /** Un envío en curso no se pisa; pasado este margen se da por interrumpido. */
    private const STALE_GENERATING_MINUTES = 2;

    /** Pausa antes del reintento inmediato. Los tests la ponen en 0. */
    public static int $retryDelayMs = 2000;

    public function __construct(private readonly DteSigner $signer) {}

    /**
     * Genera (o reenvía) y transmite el DTE de una factura.
     *
     * @throws DteException
     */
    public function processDte(Invoice $invoice): Invoice
    {
        $config = DteConfig::load();

        if (! $config->enabled()) {
            throw new DteException('La facturación electrónica no está habilitada en la configuración.');
        }
        if (! $this->canTransmit($invoice)) {
            throw new DteException("El DTE no puede generarse en el estado actual: {$invoice->dte_status}");
        }
        $errors = [...$config->errors(), ...$config->transmissionErrors()];
        if ($errors) {
            throw DteException::invalid($errors);
        }
        if ((float) $invoice->amount <= 0) {
            throw new DteException('La factura no tiene importe.');
        }

        try {
            $key = $this->signer->loadKey($config);
        } catch (\RuntimeException $e) {
            throw new DteException($e->getMessage());
        }

        [$invoice, $resend] = $this->prepare($invoice, $config, $key);

        return $this->transmit($invoice, new MhClient($config), $resend);
    }

    /** Documento que se enviaría, sin numerarlo ni enviarlo (para revisión previa). */
    public function previewDte(Invoice $invoice): array
    {
        $config = DteConfig::load();

        if ($invoice->dte_jws && $invoice->dte_json) {
            return $invoice->dte_json;
        }

        $siguiente = (int) DB::table('dte_sequences')
            ->where('ambiente', $config->ambiente())
            ->where('tipo_dte', FacturaBuilder::TIPO_DTE)
            ->value('last_number') + 1;

        return (new FacturaBuilder)->build(
            $invoice,
            $config,
            $this->numeroControl($config, $siguiente),
            strtoupper((string) Str::uuid()),
            Carbon::now(),
        );
    }

    /**
     * Valida y guarda el certificado de firma (cifrado) y su contraseña.
     */
    public function uploadCertificate(string $tempPath, string $password): string
    {
        $content = file_get_contents($tempPath);

        // Lanza si el archivo no es un certificado válido o la contraseña no coincide.
        $this->signer->parseKey($content, $password);

        Storage::disk('local')->put(DteSigner::CERT_PATH, Crypt::encryptString($content));
        Storage::disk('local')->delete('dte/certificate.p12');

        $row = Setting::firstOrNew(['key' => 'dte']);
        $dte = json_decode($row->value ?? '{}', true) ?? [];
        $dte['dte_cert_path'] = DteSigner::CERT_PATH;
        $dte['dte_cert_password'] = $this->encryptCredential($password);
        $row->value = json_encode($dte);
        $row->save();

        return DteSigner::CERT_PATH;
    }

    private function canTransmit(Invoice $invoice): bool
    {
        if ($invoice->canGenerateDte()) {
            return true;
        }

        // Un envío que quedó a medias (el proceso murió) se puede retomar.
        return $invoice->dte_status === Invoice::DTE_GENERATING
            && $invoice->dte_jws
            && $invoice->updated_at?->lt(now()->subMinutes(self::STALE_GENERATING_MINUTES));
    }

    /**
     * Reutiliza el documento ya firmado si el anterior intento no obtuvo
     * respuesta; si no hay ninguno, o el MH lo rechazó, numera y firma uno nuevo.
     *
     * @return array{0: Invoice, 1: bool} la factura y si es un reenvío
     */
    private function prepare(Invoice $invoice, DteConfig $config, \OpenSSLAsymmetricKey $key): array
    {
        return DB::transaction(function () use ($invoice, $config, $key) {
            // Serializa dos emisiones simultáneas de la misma factura (doble
            // clic, la confirmación automática y el admin a la vez).
            $locked = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canTransmit($locked)) {
                throw new DteException("El DTE no puede generarse en el estado actual: {$locked->dte_status}");
            }

            $resend = $locked->dte_status !== Invoice::DTE_REJECTED
                && $locked->dte_jws
                && $locked->dte_generation_code
                && $locked->dte_environment === $config->ambiente();

            if ($resend) {
                $locked->update(['dte_status' => Invoice::DTE_GENERATING]);

                return [$locked, true];
            }

            $numero = $this->nextNumber($config->ambiente(), FacturaBuilder::TIPO_DTE);
            $codigo = strtoupper((string) Str::uuid());
            $documento = (new FacturaBuilder)->build(
                $locked, $config, $this->numeroControl($config, $numero), $codigo, Carbon::now(),
            );
            $json = json_encode($documento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $locked->update([
                'dte_status' => Invoice::DTE_GENERATING,
                'dte_type' => FacturaBuilder::TIPO_DTE,
                'dte_number' => $documento['identificacion']['numeroControl'],
                'dte_generation_code' => $codigo,
                'dte_environment' => $config->ambiente(),
                'dte_json' => $documento,
                'dte_jws' => $this->signer->sign($json, $key),
                'dte_seal' => null,
                'mh_response' => null,
                'dte_submitted_at' => null,
                'dte_accepted_at' => null,
            ]);

            return [$locked, false];
        });
    }

    private function transmit(Invoice $invoice, MhClient $mh, bool $resend): Invoice
    {
        try {
            // Un documento ya enviado pudo llegar aunque no volviera la
            // respuesta. Reenviarlo a ciegas podría recibir un rechazo por
            // duplicado de un DTE que sí tiene sello: primero se pregunta.
            if ($resend) {
                $consulta = $mh->consulta($invoice->dte_type, $invoice->dte_generation_code);
                if ($consulta?->accepted()) {
                    return $this->markAccepted($invoice, $consulta);
                }
            }

            $result = $this->send($invoice, $mh);
        } catch (MhUnavailableException|\RuntimeException $e) {
            return $this->markError($invoice, $e->getMessage());
        }

        if ($result->accepted()) {
            return $this->markAccepted($invoice, $result);
        }

        if ($result->rejected()) {
            $invoice->update([
                'dte_status' => Invoice::DTE_REJECTED,
                'mh_response' => $result->body,
                'dte_submitted_at' => now(),
            ]);
            Log::warning('DTE rechazado por el MH', ['invoice_id' => $invoice->id, 'response' => $result->body]);

            throw new DteException('El MH rechazó el DTE: '.($result->message() ?: 'sin detalle'));
        }

        // Ni sello ni rechazo: no es un recibo. El documento se conserva para reenviarlo.
        return $this->markError($invoice, 'Respuesta del MH sin sello de recepción.', $result->body);
    }

    /** Envía con un reintento inmediato si el MH no responde. */
    private function send(Invoice $invoice, MhClient $mh): MhResult
    {
        for ($intento = 1; ; $intento++) {
            try {
                return $mh->recepcion(
                    $invoice->dte_type,
                    FacturaBuilder::VERSION,
                    $invoice->dte_generation_code,
                    $invoice->dte_jws,
                    $invoice->id,
                );
            } catch (MhUnavailableException $e) {
                if ($intento >= 2) {
                    throw $e;
                }
                usleep(self::$retryDelayMs * 1000);
            } finally {
                $invoice->forceFill(['dte_submitted_at' => now()])->save();
            }
        }
    }

    private function markAccepted(Invoice $invoice, MhResult $result): Invoice
    {
        $invoice->update([
            'dte_status' => Invoice::DTE_ACCEPTED,
            'dte_seal' => $result->sello,
            'mh_response' => $result->body,
            'status' => 'issued',
            'dte_accepted_at' => now(),
        ]);

        return $invoice->fresh();
    }

    /** @param  array<string, mixed>|null  $body */
    private function markError(Invoice $invoice, string $message, ?array $body = null): never
    {
        $invoice->update([
            'dte_status' => Invoice::DTE_ERROR,
            'mh_response' => $body ?? ['error' => $message],
        ]);
        Log::error('DTE sin respuesta del MH', ['invoice_id' => $invoice->id, 'error' => $message]);

        throw new DteException("No se obtuvo el sello del MH: {$message} El documento se reenviará tal cual en el próximo intento.");
    }

    /** Correlativo por tipo y ambiente, con bloqueo de fila en la transacción en curso. */
    private function nextNumber(string $ambiente, string $tipoDte): int
    {
        DB::table('dte_sequences')->insertOrIgnore([
            'ambiente' => $ambiente, 'tipo_dte' => $tipoDte, 'last_number' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('dte_sequences')
            ->where('ambiente', $ambiente)->where('tipo_dte', $tipoDte)
            ->lockForUpdate()->first();

        $next = (int) $row->last_number + 1;
        DB::table('dte_sequences')->where('id', $row->id)->update(['last_number' => $next, 'updated_at' => now()]);

        return $next;
    }

    /** DTE-01-{M|S|B|P}EEEPNNN-{15 dígitos}: 31 caracteres. */
    private function numeroControl(DteConfig $config, int $numero): string
    {
        return sprintf(
            'DTE-%s-%s%sP%s-%015d',
            FacturaBuilder::TIPO_DTE,
            SvCatalogs::establecimientoLetter($config->tipoEstablecimiento()),
            $config->codEstable(),
            $config->codPuntoVenta(),
            $numero,
        );
    }
}
