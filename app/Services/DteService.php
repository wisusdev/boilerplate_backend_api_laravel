<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\DteDocument;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Dte\DteConfig;
use App\Services\Dte\DteContingencyService;
use App\Services\Dte\DteDelivery;
use App\Services\Dte\DteDocumentStates;
use App\Services\Dte\DteException;
use App\Services\Dte\DteParts;
use App\Services\Dte\DtePendingException;
use App\Services\Dte\DteSigner;
use App\Services\Dte\FacturaBuilder;
use App\Services\Dte\MhClient;
use App\Services\Dte\MhResult;
use App\Services\Dte\MhUnavailableException;
use App\Services\Dte\NotaBuilder;
use App\Support\Dte\SvCatalogs;
use App\Traits\EncryptsCredentials;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
 *  3. Se envía. Solo cuenta el sello; un rechazo se guarda con su motivo. Si
 *     el MH no responde (red, timeout, 5xx) ni al reintento inmediato, el
 *     documento pasa a contingencia (DteContingencyService) y los siguientes se
 *     emiten directamente en ella. Una respuesta sin sello deja el documento
 *     `pending`: `dte:retry` lo reenvía tal cual, preguntando antes al MH si ya
 *     lo tiene.
 */
class DteService
{
    use EncryptsCredentials;

    /** Pausa antes del reintento inmediato. Los tests la ponen en 0. */
    public static int $retryDelayMs = 2000;

    /** Nadie más envía el mismo documento mientras dura un envío. */
    private const SEND_LOCK_SECONDS = 60;

    public function __construct(
        private readonly DteSigner $signer,
        private readonly DteContingencyService $contingencies,
    ) {}

    /**
     * Emite (o reintenta) el DTE de una factura y lo transmite.
     *
     * @throws DtePendingException firmado pero aún sin sello (se reintenta solo)
     * @throws DteException no se pudo emitir (datos incompletos, rechazo del MH…)
     */
    public function processDte(Invoice $invoice): Invoice
    {
        $config = $this->enabledConfig();

        if (! $invoice->canGenerateDte()) {
            throw new DteException("El DTE no puede generarse en el estado actual: {$invoice->dte_status}");
        }
        $tipo = self::tipoDe($invoice);
        $errors = [
            ...$config->errors(),
            ...$config->transmissionErrors(),
            // Un CCF con el receptor incompleto sería rechazado: no gasta número.
            ...($tipo === FacturaBuilder::TIPO_CCF ? FacturaBuilder::receptorCcfErrors($invoice) : []),
        ];
        if ($errors) {
            throw DteException::invalid($errors);
        }
        if ((float) $invoice->amount <= 0) {
            throw new DteException('La factura no tiene importe.');
        }

        $this->issueAndTransmit($invoice, $tipo, $config, fn (Invoice $locked, string $numeroControl, string $codigo) => (new FacturaBuilder)->build(
            $locked, $config, $numeroControl, $codigo, Carbon::now(), $tipo,
        ));

        return $invoice->fresh();
    }

    /**
     * Emite (o reintenta) el DTE de una nota de crédito (05) o débito (06).
     *
     * @throws DtePendingException
     * @throws DteException
     */
    public function processCreditNote(CreditNote $note): CreditNote
    {
        $config = $this->enabledConfig();
        if ($errors = [...$config->errors(), ...$config->transmissionErrors()]) {
            throw DteException::invalid($errors);
        }

        $ccf = $note->invoice->dteDocuments()->where('estado', DteDocument::TRANSMITTED)->latest('id')->first();
        if (! $ccf || $ccf->tipo_dte !== FacturaBuilder::TIPO_CCF) {
            throw new DteException('La nota solo ajusta un comprobante de crédito fiscal con sello de recepción.');
        }

        $this->issueAndTransmit($note, $note->tipoDte(), $config, fn (CreditNote $locked, string $numeroControl, string $codigo) => (new NotaBuilder)->build(
            $locked, $ccf, $config, $numeroControl, $codigo, Carbon::now(),
        ));

        return $note->fresh();
    }

    private function enabledConfig(): DteConfig
    {
        $config = DteConfig::load();
        if (! $config->enabled()) {
            throw new DteException('La facturación electrónica no está habilitada en la configuración.');
        }

        return $config;
    }

    /**
     * Carga la llave (antes de numerar: un documento que no se puede firmar no
     * gasta correlativo), emite o reutiliza el documento y lo transmite.
     *
     * @param  callable(Model, string, string): array<string, mixed>  $build
     */
    private function issueAndTransmit(Invoice|CreditNote $owner, string $tipo, DteConfig $config, callable $build): void
    {
        try {
            $key = $this->signer->loadKey($config);
        } catch (\RuntimeException $e) {
            throw new DteException($e->getMessage());
        }

        $doc = $this->issue($owner, $tipo, $config, $key, $build);
        if ($doc->estado === DteDocument::CONTINGENCY) {
            DteDelivery::autoDeliver($doc);

            throw new DtePendingException('El MH no está disponible: el DTE se emitió en contingencia. Es válido para entregarse y se transmitirá en lote cuando el MH vuelva.');
        }
        $this->transmit($doc, $config);
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
            ->where('estado', '!=', DteDocument::REJECTED)
            ->latest('id')->first();
        if ($vigente) {
            return $vigente->document();
        }

        $config = DteConfig::load();
        $siguiente = (int) DB::table('dte_sequences')
            ->where('ambiente', $config->ambiente())
            ->where('tipo_dte', self::tipoDe($invoice))
            ->value('last_number') + 1;

        return (new FacturaBuilder)->build(
            $invoice,
            $config,
            self::numeroControl($config, self::tipoDe($invoice), $siguiente),
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
     * El documento vigente del dueño (factura o nota), o uno nuevo numerado y
     * firmado.
     *
     * @param  callable(Model, string, string): array<string, mixed>  $build
     */
    private function issue(Invoice|CreditNote $owner, string $tipo, DteConfig $config, \OpenSSLAsymmetricKey $key, callable $build): DteDocument
    {
        return DB::transaction(function () use ($owner, $tipo, $config, $key, $build) {
            // Serializa dos emisiones simultáneas del mismo documento (doble
            // clic, la confirmación automática y el admin a la vez): ambas
            // verían "sin DTE" y declararían dos documentos para una venta.
            $locked = $owner::whereKey($owner->getKey())->lockForUpdate()->firstOrFail();

            $vigente = $locked->dteDocuments()
                ->whereIn('estado', [DteDocument::PENDING, DteDocument::TRANSMITTED, DteDocument::CONTINGENCY, DteDocument::INVALIDATED])
                ->latest('id')->first();

            if (in_array($vigente?->estado, [DteDocument::TRANSMITTED, DteDocument::INVALIDATED], true)) {
                throw new DteException('El documento ya tiene un DTE con sello.');
            }
            if ($vigente) {
                return $vigente;
            }

            // Con el MH caído no se le espera: se emite directamente en
            // contingencia y el cliente se lleva un documento válido.
            $contingencia = $this->contingencies->active($config->ambiente());

            $numero = self::nextNumber($config->ambiente(), $tipo);
            $codigo = strtoupper((string) Str::uuid());
            $documento = $build($locked, self::numeroControl($config, $tipo, $numero), $codigo);
            if ($contingencia) {
                $documento = DteContingencyService::apply($documento, $contingencia);
            }
            // Se firma el JSON exacto que se guarda y se envía.
            $json = json_encode($documento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $doc = $locked->dteDocuments()->create([
                'tipo_dte' => $tipo,
                'ambiente' => $config->ambiente(),
                'version' => DteParts::VERSIONES[$tipo],
                'numero_control' => $documento['identificacion']['numeroControl'],
                'codigo_generacion' => $codigo,
                'estado' => $contingencia ? DteDocument::CONTINGENCY : DteDocument::PENDING,
                'contingencia_id' => $contingencia?->id,
                'json_content' => $json,
                'firma_electronica' => $this->signer->sign($json, $key),
            ]);

            $locked->applyDteSummary([
                'dte_status' => $contingencia ? Invoice::DTE_CONTINGENCY : Invoice::DTE_PENDING,
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
                        DteDocumentStates::transmitted($doc, $consulta->sello, $consulta->body);

                        return;
                    }
                }

                $result = $this->send($doc, $mh);
            } catch (MhUnavailableException $e) {
                if ($e->status === null || $e->status >= 500) {
                    $this->toContingency($doc, $config, $e->getMessage());
                }
                $this->markPending($doc, $e->getMessage());
            } catch (\RuntimeException $e) {
                $this->markPending($doc, $e->getMessage());
            }

            if ($result->accepted()) {
                DteDocumentStates::transmitted($doc, $result->sello, $result->body);

                return;
            }
            if ($result->rejected()) {
                $mensaje = $result->message() ?: 'sin detalle';
                DteDocumentStates::rejected($doc, $mensaje, $result->body);

                throw new DteException("El MH rechazó el DTE: {$mensaje}");
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
            $doc->owner()->applyDteSummary(['dte_submitted_at' => now()]);

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

    /**
     * El MH no contesta (red, timeout, 5xx) ni tras el reintento inmediato: se
     * sigue facturando en contingencia.
     */
    private function toContingency(DteDocument $doc, DteConfig $config, string $causa): never
    {
        try {
            $this->contingencies->moveToContingency($doc, $config, $causa);
        } catch (\Throwable $e) {
            $this->markPending($doc, "{$causa} (no se pudo pasar a contingencia: {$e->getMessage()})");
        }

        throw new DtePendingException("El MH no responde ({$causa}). El DTE se emitió en contingencia: es válido para entregarse y se transmitirá en lote cuando el MH vuelva.");
    }

    /** @param  array<string, mixed>|null  $body */
    private function markPending(DteDocument $doc, string $error, ?array $body = null): never
    {
        DteDocumentStates::pending($doc, $error, $body);

        throw new DtePendingException("El DTE quedó pendiente de sello: {$error} Se reenviará automáticamente.");
    }

    /** Correlativo por tipo y ambiente, con bloqueo de fila en la transacción en curso. */
    public static function nextNumber(string $ambiente, string $tipoDte): int
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

    /** Tipo de documento de venta de la factura: 01 Factura (por defecto) o 03 CCF. */
    public static function tipoDe(Invoice $invoice): string
    {
        return $invoice->dte_type === FacturaBuilder::TIPO_CCF ? FacturaBuilder::TIPO_CCF : FacturaBuilder::TIPO_DTE;
    }

    /** DTE-TT-{M|S|B|P}EEEPNNN-{15 dígitos}: 31 caracteres. */
    public static function numeroControl(DteConfig $config, string $tipo, int $numero): string
    {
        return sprintf(
            'DTE-%s-%s%sP%s-%015d',
            $tipo,
            SvCatalogs::establecimientoLetter($config->tipoEstablecimiento()),
            $config->codEstable(),
            $config->codPuntoVenta(),
            $numero,
        );
    }
}
