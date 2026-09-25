<?php

namespace App\Services;

use App\Models\DteDocument;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Dte\DteConfig;
use App\Services\Dte\DteException;
use App\Services\Dte\DtePendingException;
use App\Services\Dte\DteSigner;
use App\Services\Dte\FacturaBuilder;
use App\Services\Dte\MhClient;
use App\Services\Dte\MhResult;
use App\Services\Dte\MhUnavailableException;
use App\Support\Dte\SvCatalogs;
use App\Traits\EncryptsCredentials;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
 *  2. Dentro de una transacción que bloquea la factura: si ya tiene un DTE
 *     pendiente se reutiliza; si no, se numera, se construye, se firma (JWS
 *     RS512) y se guarda como `pending` en `dte_documents`.
 *  3. Se envía. Solo cuenta el sello; un rechazo se guarda con su motivo; sin
 *     respuesta el documento sigue `pending` y el comando `dte:retry` lo
 *     reenvía tal cual, preguntando antes al MH si ya lo tiene.
 */
class DteService
{
    use EncryptsCredentials;

    /** Pausa antes del reintento inmediato. Los tests la ponen en 0. */
    public static int $retryDelayMs = 2000;

    /** Nadie más envía el mismo documento mientras dura un envío. */
    private const SEND_LOCK_SECONDS = 60;

    public function __construct(private readonly DteSigner $signer) {}

    /**
     * Emite (o reintenta) el DTE de una factura y lo transmite.
     *
     * @throws DtePendingException firmado pero aún sin sello (se reintenta solo)
     * @throws DteException no se pudo emitir (datos incompletos, rechazo del MH…)
     */
    public function processDte(Invoice $invoice): Invoice
    {
        $config = DteConfig::load();

        if (! $config->enabled()) {
            throw new DteException('La facturación electrónica no está habilitada en la configuración.');
        }
        if (! $invoice->canGenerateDte()) {
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

        $doc = $this->issue($invoice, $config, $key);
        $this->transmit($doc, $config);

        return $invoice->fresh();
    }

    /**
     * Reintenta un documento pendiente (el comando `dte:retry`).
     *
     * @throws DteException
     */
    public function retry(DteDocument $doc): DteDocument
    {
        if ($doc->estado !== DteDocument::PENDING) {
            return $doc;
        }

        $config = DteConfig::load();
        if ($errors = $config->transmissionErrors()) {
            throw DteException::invalid($errors);
        }

        $this->transmit($doc, $config);

        return $doc->fresh();
    }

    /** Documento que se enviaría, sin numerarlo ni enviarlo (para revisión previa). */
    public function previewDte(Invoice $invoice): array
    {
        $vigente = $invoice->dteDocuments()
            ->whereIn('estado', [DteDocument::PENDING, DteDocument::TRANSMITTED])
            ->latest('id')->first();
        if ($vigente) {
            return $vigente->document();
        }

        $config = DteConfig::load();
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

    /**
     * El documento vigente de la factura, o uno nuevo numerado y firmado.
     */
    private function issue(Invoice $invoice, DteConfig $config, \OpenSSLAsymmetricKey $key): DteDocument
    {
        return DB::transaction(function () use ($invoice, $config, $key) {
            // Serializa dos emisiones simultáneas de la misma factura (doble
            // clic, la confirmación automática y el admin a la vez): ambas
            // verían "sin DTE" y declararían dos documentos para una venta.
            $locked = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            $vigente = $locked->dteDocuments()
                ->whereIn('estado', [DteDocument::PENDING, DteDocument::TRANSMITTED])
                ->latest('id')->first();

            if ($vigente?->estado === DteDocument::TRANSMITTED) {
                throw new DteException('La factura ya tiene un DTE con sello.');
            }
            if ($vigente) {
                return $vigente;
            }

            $numero = $this->nextNumber($config->ambiente(), FacturaBuilder::TIPO_DTE);
            $codigo = strtoupper((string) Str::uuid());
            $documento = (new FacturaBuilder)->build(
                $locked, $config, $this->numeroControl($config, $numero), $codigo, Carbon::now(),
            );
            // Se firma el JSON exacto que se guarda y se envía.
            $json = json_encode($documento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $doc = $locked->dteDocuments()->create([
                'tipo_dte' => FacturaBuilder::TIPO_DTE,
                'ambiente' => $config->ambiente(),
                'version' => FacturaBuilder::VERSION,
                'numero_control' => $documento['identificacion']['numeroControl'],
                'codigo_generacion' => $codigo,
                'estado' => DteDocument::PENDING,
                'json_content' => $json,
                'firma_electronica' => $this->signer->sign($json, $key),
            ]);

            $locked->update([
                'dte_status' => Invoice::DTE_PENDING,
                'dte_type' => $doc->tipo_dte,
                'dte_number' => $doc->numero_control,
                'dte_generation_code' => $doc->codigo_generacion,
                'dte_environment' => $doc->ambiente,
                'dte_seal' => null,
                'mh_response' => null,
                'dte_submitted_at' => null,
                'dte_accepted_at' => null,
            ]);

            return $doc;
        });
    }

    /**
     * Envía (o reenvía) un documento pendiente y registra el resultado.
     *
     * @throws DtePendingException
     * @throws DteException
     */
    private function transmit(DteDocument $doc, DteConfig $config): void
    {
        // El comando programado y un reintento manual podrían enviar el mismo
        // documento a la vez: el segundo recibiría un rechazo por duplicado.
        $lock = Cache::lock("dte:send:{$doc->id}", self::SEND_LOCK_SECONDS);
        if (! $lock->get()) {
            throw new DtePendingException('El DTE se está enviando en este momento. Vuelve a consultar en unos segundos.');
        }

        try {
            $doc->refresh();
            if ($doc->estado !== DteDocument::PENDING) {
                return;
            }

            $mh = new MhClient($config, $doc->ambiente);

            try {
                // Un documento ya enviado pudo llegar aunque no volviera la
                // respuesta. Reenviarlo a ciegas podría recibir un rechazo por
                // duplicado de un DTE que sí tiene sello: primero se pregunta.
                if ($doc->intentos > 0) {
                    $consulta = $mh->consulta($doc->tipo_dte, $doc->codigo_generacion);
                    if ($consulta?->accepted()) {
                        $this->markTransmitted($doc, $consulta);

                        return;
                    }
                }

                $result = $this->send($doc, $mh);
            } catch (MhUnavailableException|\RuntimeException $e) {
                $this->markPending($doc, $e->getMessage());
            }

            if ($result->accepted()) {
                $this->markTransmitted($doc, $result);

                return;
            }
            if ($result->rejected()) {
                $this->markRejected($doc, $result);
            }

            // Ni sello ni rechazo: no es un recibo. El documento sigue pendiente.
            $this->markPending($doc, 'Respuesta del MH sin sello de recepción.', $result->body);
        } finally {
            $lock->release();
        }
    }

    /** Envía con un reintento inmediato si el MH no responde. */
    private function send(DteDocument $doc, MhClient $mh): MhResult
    {
        for ($intento = 1; ; $intento++) {
            $doc->increment('intentos');
            Invoice::whereKey($doc->invoice_id)->update(['dte_submitted_at' => now()]);

            try {
                return $mh->recepcion(
                    $doc->tipo_dte, $doc->version, $doc->codigo_generacion, $doc->firma_electronica, $doc->id,
                );
            } catch (MhUnavailableException $e) {
                if ($intento >= 2) {
                    throw $e;
                }
                usleep(self::$retryDelayMs * 1000);
            }
        }
    }

    private function markTransmitted(DteDocument $doc, MhResult $result): void
    {
        DB::transaction(function () use ($doc, $result) {
            $doc->update([
                'estado' => DteDocument::TRANSMITTED,
                'sello_recibido' => $result->sello,
                'fh_procesamiento' => $result->body['fhProcesamiento'] ?? null,
                'mh_response' => $result->body,
                'ultimo_error' => null,
                'transmitido_at' => now(),
            ]);
            $doc->invoice()->update([
                'dte_status' => Invoice::DTE_ACCEPTED,
                'dte_seal' => $result->sello,
                'mh_response' => json_encode($result->body),
                'status' => 'issued',
                'dte_accepted_at' => now(),
            ]);
        });
    }

    private function markRejected(DteDocument $doc, MhResult $result): never
    {
        $mensaje = $result->message() ?: 'sin detalle';

        DB::transaction(function () use ($doc, $result, $mensaje) {
            $doc->update([
                'estado' => DteDocument::REJECTED,
                'mh_response' => $result->body,
                'ultimo_error' => $mensaje,
            ]);
            $doc->invoice()->update([
                'dte_status' => Invoice::DTE_REJECTED,
                'mh_response' => json_encode($result->body),
            ]);
        });
        Log::warning('DTE rechazado por el MH', ['dte_document_id' => $doc->id, 'response' => $result->body]);

        throw new DteException("El MH rechazó el DTE: {$mensaje}");
    }

    /** @param  array<string, mixed>|null  $body */
    private function markPending(DteDocument $doc, string $error, ?array $body = null): never
    {
        $doc->update(['ultimo_error' => $error, 'mh_response' => $body]);
        $doc->invoice()->update([
            'dte_status' => Invoice::DTE_PENDING,
            'mh_response' => json_encode($body ?? ['error' => $error]),
        ]);
        Log::warning('DTE pendiente de sello', ['dte_document_id' => $doc->id, 'error' => $error]);

        throw new DtePendingException("El DTE quedó pendiente de sello: {$error} Se reenviará automáticamente.");
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
