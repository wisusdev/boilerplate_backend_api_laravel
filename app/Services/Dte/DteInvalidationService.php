<?php

namespace App\Services\Dte;

use App\Models\Contracts\DteOwner;
use App\Models\DteDocument;
use App\Models\DteInvalidacion;
use App\Models\Invoice;
use App\Support\Dte\SvCatalogs;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Evento de invalidación (Manual Funcional v2, VII; esquema invalidacion v3).
 *
 * Un DTE con sello solo deja de valer con un evento de invalidación sellado
 * por el MH. Tipos (CAT-024):
 *
 *   1 error en la información · 2 rescindir la operación · 3 otro
 *
 * Con 1 o 3, primero se emite y sella el documento que reemplaza al
 * invalidado (en vamosPues, otra factura con los datos correctos) y el evento
 * lleva su código de generación. Con 2 (se deshizo la venta), null.
 *
 * La invalidación no se hace en contingencia: sin respuesta del MH queda
 * pendiente y `dte:retry` reenvía el mismo evento.
 */
class DteInvalidationService
{
    /** Plazo para invalidar una Factura desde su transmisión. */
    private const PLAZO_FACTURA_MESES = 3;

    private const SEND_LOCK_SECONDS = 60;

    public function __construct(private readonly DteSigner $signer) {}

    /**
     * Invalida el DTE vigente de una factura o de una nota.
     *
     * @param  array{tipo_anulacion: int, motivo?: ?string, codigo_generacion_r?: ?string,
     *               responsable_nombre?: ?string, responsable_tipo_doc?: ?string, responsable_num_doc?: ?string,
     *               solicita_nombre: string, solicita_tipo_doc: string, solicita_num_doc: string}  $input
     *
     * @throws DtePendingException el evento quedó sin respuesta del MH (se reenvía solo)
     * @throws DteException regla incumplida o rechazo del MH
     */
    public function invalidate(DteOwner $owner, array $input, ?string $userId = null): DteInvalidacion
    {
        $doc = $owner->dteDocuments()
            ->whereIn('estado', [DteDocument::TRANSMITTED, DteDocument::INVALIDATED])
            ->latest('id')->first();
        if (! $doc) {
            throw new DteException('Solo se invalida un DTE con sello de recepción.');
        }

        // Ya presentada y esperando al MH: se reenvía esa misma.
        $previa = $doc->invalidaciones()
            ->whereIn('estado', [DteInvalidacion::PENDING, DteInvalidacion::TRANSMITTED])
            ->latest('id')->first();
        if ($previa?->estado === DteInvalidacion::TRANSMITTED) {
            throw new DteException('El DTE ya está invalidado.');
        }
        $config = DteConfig::load();
        if ($previa) {
            return $this->send($previa, $config);
        }

        $input = $this->check($doc, $config, $input);
        try {
            $key = $this->signer->loadKey($config);
        } catch (\RuntimeException $e) {
            throw new DteException($e->getMessage());
        }

        $codigo = strtoupper((string) Str::uuid());
        $json = json_encode(
            $this->buildEvent($doc, $config, $input, $codigo, Carbon::now()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $invalidacion = DB::transaction(function () use ($doc, $input, $codigo, $json, $key, $userId) {
            // Dos invalidaciones simultáneas del mismo DTE: solo pasa una.
            DteDocument::whereKey($doc->id)->lockForUpdate()->first();
            if ($doc->invalidaciones()->whereIn('estado', [DteInvalidacion::PENDING, DteInvalidacion::TRANSMITTED])->exists()) {
                throw new DteException('Ya hay una invalidación en curso para este DTE.');
            }

            return $doc->invalidaciones()->create([
                'tipo_anulacion' => $input['tipo_anulacion'],
                'motivo' => $input['motivo'],
                'codigo_generacion' => $codigo,
                'codigo_generacion_r' => $input['codigo_generacion_r'],
                'solicita_nombre' => $input['solicita_nombre'],
                'estado' => DteInvalidacion::PENDING,
                'json_content' => $json,
                'firma_electronica' => $this->signer->sign($json, $key),
                'created_by' => $userId,
            ]);
        });

        return $this->send($invalidacion, $config);
    }

    /** Reenvía una invalidación sin respuesta (el comando `dte:retry`). */
    public function retry(DteInvalidacion $invalidacion): DteInvalidacion
    {
        return $invalidacion->estado === DteInvalidacion::PENDING
            ? $this->send($invalidacion, DteConfig::load())
            : $invalidacion;
    }

    /**
     * Documentos que pueden reemplazar al de este dueño: otros DTE del mismo
     * tipo, con sello.
     */
    public function replacementCandidates(DteOwner $owner)
    {
        $propios = $owner->dteDocuments()->pluck('id');
        $tipo = $owner->dteDocuments()->where('estado', DteDocument::TRANSMITTED)->latest('id')->value('tipo_dte');

        return DteDocument::with(['invoice:id,receptor_name,amount', 'creditNote:id,number,total', 'purchaseDocument:id,number,total'])
            ->where('estado', DteDocument::TRANSMITTED)
            ->where('tipo_dte', $tipo ?? FacturaBuilder::TIPO_DTE)
            ->whereNotIn('id', $propios)
            ->latest('id')->limit(50)->get();
    }

    /** Una nota de crédito se invalida siempre sin documento de reemplazo. */
    private static function sinReemplazo(DteDocument $doc): bool
    {
        return $doc->tipo_dte === '05';
    }

    /**
     * Reglas que se validan antes de firmar.
     *
     * @return array<string, mixed> la entrada normalizada
     */
    private function check(DteDocument $doc, DteConfig $config, array $input): array
    {
        if ($doc->estado !== DteDocument::TRANSMITTED || ! $doc->sello_recibido) {
            throw new DteException('Solo se invalida un DTE con sello de recepción.');
        }

        $tipo = (int) ($input['tipo_anulacion'] ?? 0);
        $motivo = trim((string) ($input['motivo'] ?? ''));
        if (! in_array($tipo, [DteInvalidacion::TIPO_ERROR, DteInvalidacion::TIPO_RESCINDIR, DteInvalidacion::TIPO_OTRO], true)) {
            throw new DteException('Tipo de invalidación: 1 (error en la información), 2 (rescindir la operación) o 3 (otro).');
        }
        if ($tipo === DteInvalidacion::TIPO_OTRO && $motivo === '') {
            throw new DteException('Con el tipo 3 (otro) hay que indicar el motivo.');
        }

        // Factura: hasta 3 meses desde su transmisión. Los plazos del CCF los
        // aplica el propio MH, que no sella un evento fuera de plazo.
        if ($doc->tipo_dte === FacturaBuilder::TIPO_DTE
            && ($doc->transmitido_at ?? $doc->created_at)->copy()->addMonths(self::PLAZO_FACTURA_MESES)->isPast()) {
            throw new DteException('Venció el plazo para invalidar la factura (3 meses desde su transmisión).');
        }

        // Un CCF con notas vigentes no se invalida: primero se invalidan ellas.
        if ($doc->tipo_dte === FacturaBuilder::TIPO_CCF && $doc->invoice
            && $doc->invoice->creditNotes()->whereIn('dte_status', ['accepted', 'pending', 'contingency'])->exists()) {
            throw new DteException('El CCF tiene notas de crédito o débito vigentes: primero hay que invalidarlas.');
        }

        // Documento de reemplazo: con 1 o 3, otro DTE del mismo tipo ya sellado
        // (salvo en la nota de crédito, que nunca lleva).
        $reemplazo = null;
        if ($tipo !== DteInvalidacion::TIPO_RESCINDIR && ! self::sinReemplazo($doc)) {
            $codigoR = strtoupper(trim((string) ($input['codigo_generacion_r'] ?? '')));
            if ($codigoR === '') {
                throw new DteException("Con el tipo {$tipo} primero se emite el documento que reemplaza a este y se indica su código de generación.");
            }
            $r = DteDocument::where('codigo_generacion', $codigoR)->first();
            if (! $r || $r->id === $doc->id || $r->tipo_dte !== $doc->tipo_dte || $r->estado !== DteDocument::TRANSMITTED) {
                throw new DteException('El DTE de reemplazo debe ser otro documento del mismo tipo, con sello de recepción.');
            }
            $reemplazo = $r->codigo_generacion;
        }

        // Quien realiza el evento: por defecto, el responsable del establecimiento.
        $responsable = trim((string) ($input['responsable_nombre'] ?? '')) !== ''
            ? [$input['responsable_nombre'], $input['responsable_tipo_doc'] ?? '', $input['responsable_num_doc'] ?? '']
            : [$config->responsableNombre(), $config->responsableTipoDoc(), $config->responsableNumDoc()];
        $solicita = [$input['solicita_nombre'] ?? '', $input['solicita_tipo_doc'] ?? '', $input['solicita_num_doc'] ?? ''];

        foreach (['quien realiza la invalidación' => $responsable, 'quien solicita la invalidación' => $solicita] as $quien => [$nombre, $tipoDoc, $numDoc]) {
            if (trim((string) $nombre) === '' || ! SvCatalogs::validTipoDocumento($tipoDoc) || trim((string) $numDoc) === '') {
                throw new DteException("Faltan el nombre, el tipo (CAT-022) o el número de documento de {$quien}.");
            }
        }

        return [
            'tipo_anulacion' => $tipo,
            'motivo' => $motivo !== '' ? mb_substr($motivo, 0, 200) : null,
            'codigo_generacion_r' => $reemplazo,
            'responsable' => array_map(fn ($v) => trim((string) $v), $responsable),
            'solicita' => array_map(fn ($v) => trim((string) $v), $solicita),
            'solicita_nombre' => mb_substr(trim((string) $solicita[0]), 0, 100),
        ];
    }

    /** @return array<string, mixed> el evento (esquema invalidacion v3) */
    public function buildEvent(DteDocument $doc, DteConfig $config, array $input, string $codigo, CarbonInterface $now): array
    {
        $now = $now->copy()->setTimezone('America/El_Salvador');
        $dte = $doc->document();
        $receptor = $dte['receptor'] ?? [];
        $fecEmiDte = $dte['identificacion']['fecEmi'] ?? $doc->created_at->copy()->setTimezone('America/El_Salvador')->format('Y-m-d');

        // CCF: el receptor se identifica por NIT (tipo 36).
        [$tipoDoc, $numDoc] = isset($receptor['nit'])
            ? ['36', $receptor['nit']]
            : [$receptor['tipoDocumento'] ?? null, $receptor['numDocumento'] ?? null];

        [$rNombre, $rTipo, $rNum] = $input['responsable'];
        [$sNombre, $sTipo, $sNum] = $input['solicita'];

        return [
            'identificacion' => [
                'version' => 3,
                'ambiente' => $doc->ambiente,
                'codigoGeneracion' => $codigo,
                // En la Factura, la fecha del día; en CCF y notas, la del DTE.
                'fecEmi' => $doc->tipo_dte === FacturaBuilder::TIPO_DTE ? $now->format('Y-m-d') : $fecEmiDte,
                'horEmi' => $now->format('H:i:s'),
                'fusion' => null,
            ],
            'emisor' => [
                'nit' => $config->nit(),
                'nombre' => mb_substr($config->nombre(), 0, 250),
                'codEstableMH' => $config->codEstableMH(),
                'codEstable' => $config->codEstableCompleto(),
                'codPuntoVentaMH' => $config->codPuntoVentaMH(),
                'codPuntoVenta' => $config->codPuntoVentaCompleto(),
                'telefono' => mb_substr($config->telefono(), 0, 30),
                'correo' => mb_substr($config->correo(), 0, 100),
            ],
            // El receptor tal como lo declaró el DTE (null donde el DTE tenía null).
            'documento' => [
                'tipoDte' => $doc->tipo_dte,
                'codigoGeneracion' => $doc->codigo_generacion,
                'selloRecibido' => $doc->sello_recibido,
                'numeroControl' => $doc->numero_control,
                'fecEmi' => $fecEmiDte,
                'codigoGeneracionR' => $input['codigo_generacion_r'],
                'tipoDocumento' => $tipoDoc,
                'numDocumento' => $numDoc,
                'nombre' => $receptor['nombre'] ?? null,
                'telefono' => $receptor['telefono'] ?? null,
                'correo' => $receptor['correo'] ?? null,
            ],
            'motivo' => [
                'tipoAnulacion' => $input['tipo_anulacion'],
                'motivoAnulacion' => $input['motivo'],
                'nombreResponsable' => mb_substr($rNombre, 0, 100),
                'tipDocResponsable' => $rTipo,
                'numDocResponsable' => mb_substr($rNum, 0, 20),
                'nombreSolicita' => mb_substr($sNombre, 0, 100),
                'tipDocSolicita' => $sTipo,
                'numDocSolicita' => mb_substr($sNum, 0, 20),
            ],
        ];
    }

    private function send(DteInvalidacion $inv, DteConfig $config): DteInvalidacion
    {
        $lock = Cache::lock("dte:invalidate:{$inv->id}", self::SEND_LOCK_SECONDS);
        if (! $lock->get()) {
            throw new DtePendingException('La invalidación se está enviando en este momento. Vuelve a consultar en unos segundos.');
        }

        try {
            $inv->refresh();
            if ($inv->estado !== DteInvalidacion::PENDING) {
                return $inv;
            }

            $doc = $inv->documento;
            $inv->increment('intentos');

            try {
                $r = (new MhClient($config, $doc->ambiente))->anular($inv->firma_electronica, $inv->id);
            } catch (\RuntimeException $e) {
                $inv->update(['ultimo_error' => $e->getMessage()]);

                throw new DtePendingException("La invalidación quedó pendiente: {$e->getMessage()} Se reenviará automáticamente.");
            }

            if ($r->accepted()) {
                DB::transaction(function () use ($inv, $doc, $r) {
                    $inv->update([
                        'estado' => DteInvalidacion::TRANSMITTED,
                        'sello_recibido' => $r->sello,
                        'mh_response' => $r->body,
                        'ultimo_error' => null,
                        'transmitido_at' => now(),
                    ]);
                    $doc->update(['estado' => DteDocument::INVALIDATED]);
                    $doc->owner()->applyDteSummary([
                        'dte_status' => Invoice::DTE_INVALIDATED,
                        'status' => 'cancelled',
                    ]);
                });

                return $inv->fresh();
            }

            if ($r->rejected()) {
                $mensaje = $r->message() ?: 'sin detalle';
                $inv->update(['estado' => DteInvalidacion::REJECTED, 'mh_response' => $r->body, 'ultimo_error' => $mensaje]);
                Log::warning('Invalidación rechazada por el MH', ['dte_invalidacion_id' => $inv->id, 'response' => $r->body]);

                throw new DteException("El MH rechazó la invalidación: {$mensaje}");
            }

            $inv->update(['mh_response' => $r->body, 'ultimo_error' => 'Respuesta del MH sin sello de recepción.']);

            throw new DtePendingException('La invalidación quedó pendiente: el MH respondió sin sello. Se reenviará automáticamente.');
        } finally {
            $lock->release();
        }
    }
}
