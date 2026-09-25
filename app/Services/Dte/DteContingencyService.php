<?php

namespace App\Services\Dte;

use App\Models\DteContingencia;
use App\Models\DteDocument;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Contingencia (Manual Funcional v2, VIII).
 *
 * Cuando el MH no responde tras el reintento inmediato, se sigue facturando en
 * modelo diferido: el DTE lleva tipoModelo 2, tipoOperacion 2 y el tipo de
 * contingencia (CAT-005), se firma y vale para entregarse sin sello. Superada
 * la causa:
 *
 *  1. se concilian los documentos que sí se habían enviado en línea (si el MH
 *     los tiene, esa versión es la válida);
 *  2. evento de contingencia con los códigos de generación del resto, en 24 h
 *     desde que cesó la causa;
 *  3. con el evento sellado, el lote de esos mismos DTE firmados, en 72 h;
 *  4. se consulta el lote y cada DTE queda sellado o rechazado.
 *
 * Los DTE del lote no pueden cambiar tipoModelo, tipoOperacion,
 * tipoContingencia ni motivoContin: el MH los compara con el evento. Por eso
 * motivoContin va null en ambos (solo es obligatorio con el tipo 5).
 */
class DteContingencyService
{
    /** Un evento reporta hasta 1000 documentos; el resto va en otro del mismo periodo. */
    private const MAX_DOCS_POR_EVENTO = 1000;

    public function __construct(private readonly DteSigner $signer) {}

    /** La contingencia abierta del ambiente, si la hay. */
    public function active(string $ambiente): ?DteContingencia
    {
        return DteContingencia::where('ambiente', $ambiente)
            ->where('estado', DteContingencia::OPEN)
            ->latest('id')->first();
    }

    /**
     * Abre (o reutiliza) la contingencia del ambiente. El tipo 1, "no
     * disponibilidad de sistema del MH", es lo único que la plataforma puede
     * afirmar: corre en la nube, no conoce fallos de internet o energía del
     * comercio.
     */
    public function open(string $ambiente, string $motivo): DteContingencia
    {
        return DB::transaction(function () use ($ambiente, $motivo) {
            $abierta = DteContingencia::where('ambiente', $ambiente)
                ->where('estado', DteContingencia::OPEN)
                ->lockForUpdate()->latest('id')->first();
            if ($abierta) {
                return $abierta;
            }

            Log::warning('DTE: contingencia abierta', ['ambiente' => $ambiente, 'motivo' => $motivo]);

            return DteContingencia::create([
                'ambiente' => $ambiente,
                'tipo_contingencia' => DteContingencia::TIPO_MH_NO_DISPONIBLE,
                'motivo' => mb_substr($motivo, 0, 500),
                'estado' => DteContingencia::OPEN,
                'inicio' => now(),
            ]);
        });
    }

    /**
     * Marca un documento recién construido como generado en contingencia.
     *
     * @param  array<string, mixed>  $documento
     * @return array<string, mixed>
     */
    public static function apply(array $documento, DteContingencia $c): array
    {
        $documento['identificacion']['tipoModelo'] = 2;     // CAT-003: diferido
        $documento['identificacion']['tipoOperacion'] = 2;  // CAT-004: contingencia
        $documento['identificacion']['tipoContingencia'] = $c->tipo_contingencia;
        $documento['identificacion']['motivoContin'] = self::motivoContin($c);

        return $documento;
    }

    /**
     * Pasa a contingencia un documento que el MH no contestó: mismo código de
     * generación y número de control, modelo diferido, firmado de nuevo. Se
     * guarda la versión enviada en línea por si sí llegó.
     */
    public function moveToContingency(DteDocument $doc, DteConfig $config, string $causa): DteContingencia
    {
        $c = $this->open($doc->ambiente, $causa);
        $key = $this->signer->loadKey($config);

        $json = json_encode(
            self::apply(json_decode($doc->json_content, true, flags: JSON_THROW_ON_ERROR), $c),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($doc, $c, $json, $key, $causa) {
            $doc->update([
                'estado' => DteDocument::CONTINGENCY,
                'contingencia_id' => $c->id,
                'json_en_linea' => $doc->json_en_linea ?? ($doc->intentos > 0 ? $doc->json_content : null),
                'firma_en_linea' => $doc->firma_en_linea ?? ($doc->intentos > 0 ? $doc->firma_electronica : null),
                'json_content' => $json,
                'firma_electronica' => $this->signer->sign($json, $key),
                'ultimo_error' => $causa,
            ]);
            $doc->invoice()->update([
                'dte_status' => Invoice::DTE_CONTINGENCY,
                'mh_response' => json_encode(['error' => $causa]),
            ]);
        });

        // El manual pide entregar el documento al generarlo, aún sin sello.
        DteDelivery::autoDeliver($doc->fresh());

        return $c;
    }

    /** Avanza todas las contingencias sin terminar tanto como el MH permita ahora. */
    public function processAll(): void
    {
        DteContingencia::whereIn('estado', [
            DteContingencia::OPEN, DteContingencia::CLOSED, DteContingencia::EVENT_SENT, DteContingencia::LOTE_SENT,
        ])->orderBy('id')->get()->each(function (DteContingencia $c) {
            try {
                $this->process($c);
            } catch (\Throwable $e) {
                Log::warning('DTE: contingencia sin avanzar', ['contingencia_id' => $c->id, 'error' => $e->getMessage()]);
            }
        });
    }

    /**
     * Avanza una contingencia tanto como el MH permita ahora.
     *
     * @throws DteException
     */
    public function process(DteContingencia $c): DteContingencia
    {
        $config = DteConfig::load();
        // La contingencia pertenece al ambiente en que ocurrió.
        $mh = new MhClient($config, $c->ambiente);

        try {
            if ($c->estado === DteContingencia::OPEN && ! $this->mhIsBack($c, $mh)) {
                return $c;
            }
            if ($c->estado === DteContingencia::CLOSED) {
                $this->sendEvent($c, $config, $mh);
            }
            if ($c->estado === DteContingencia::EVENT_SENT) {
                $this->sendLote($c, $mh);
            }
            if ($c->estado === DteContingencia::LOTE_SENT) {
                $this->pollLote($c, $mh);
            }
        } catch (MhUnavailableException $e) {
            $c->update(['ultimo_error' => $e->getMessage()]);
        }

        return $c->fresh();
    }

    /**
     * Tras corregir lo que motivó el rechazo del evento (normalmente los datos
     * del responsable), se rearma con un código de generación nuevo.
     */
    public function rearm(DteContingencia $c): DteContingencia
    {
        if ($c->estado !== DteContingencia::EVENT_REJECTED) {
            throw new DteException('Solo se rearma un evento de contingencia rechazado.');
        }
        $c->update([
            'estado' => DteContingencia::CLOSED,
            'codigo_generacion' => null, 'json_content' => null, 'firma_electronica' => null,
        ]);

        return $this->process($c);
    }

    /** @return array<string, mixed> el evento (esquema contingencia v4) */
    public function buildEvent(DteContingencia $c, DteConfig $config, iterable $docs, string $codigo, CarbonInterface $now): array
    {
        $sv = fn (CarbonInterface $t) => $t->copy()->setTimezone('America/El_Salvador');
        $now = $sv($now);
        $inicio = $sv($c->inicio);
        $fin = $sv($c->fin ?? $now);

        $detalle = [];
        foreach ($docs as $d) {
            $detalle[] = ['noItem' => count($detalle) + 1, 'tipoDoc' => $d->tipo_dte, 'codigoGeneracion' => $d->codigo_generacion];
        }

        return [
            'identificacion' => [
                'version' => 4,
                'ambiente' => $c->ambiente,
                'codigoGeneracion' => $codigo,
                'fTransmision' => $now->format('Y-m-d'),
                'hTransmision' => $now->format('H:i:s'),
            ],
            'emisor' => [
                'nit' => $config->nit(),
                'nombre' => mb_substr($config->nombre(), 0, 250),
                'nombreResponsable' => mb_substr($config->responsableNombre(), 0, 100),
                'tipoDocResponsable' => $config->responsableTipoDoc(),
                'numeroDocResponsable' => mb_substr($config->responsableNumDoc(), 0, 25),
                'tipoEstablecimiento' => $config->tipoEstablecimiento(),
                'codEstableMH' => $config->codEstableMH(),
                'codPuntoVentaMH' => $config->codPuntoVentaMH(),
                'telefono' => mb_substr($config->telefono(), 0, 30),
                'correo' => mb_substr($config->correo(), 0, 100),
            ],
            'detalleDTE' => $detalle,
            'motivo' => [
                'fInicio' => $inicio->format('Y-m-d'),
                'fFin' => $fin->format('Y-m-d'),
                'hInicio' => $inicio->format('H:i:s'),
                'hFin' => $fin->format('H:i:s'),
                'tipoContingencia' => $c->tipo_contingencia,
                'motivoContingencia' => self::motivoContin($c),
            ],
        ];
    }

    /**
     * ¿Volvió el MH? Se consulta uno de los documentos: cualquier respuesta que
     * no sea una caída cierra la contingencia.
     */
    private function mhIsBack(DteContingencia $c, MhClient $mh): bool
    {
        $doc = $c->documentos()->where('estado', DteDocument::CONTINGENCY)->first();
        if (! $doc) {
            // No se emitió nada en ella: no hay nada que reportar.
            $c->update(['estado' => DteContingencia::DONE, 'fin' => now()]);

            return false;
        }

        $mh->consulta($doc->tipo_dte, $doc->codigo_generacion); // lanza si sigue caído
        $c->update(['estado' => DteContingencia::CLOSED, 'fin' => now(), 'ultimo_error' => null]);

        return true;
    }

    private function sendEvent(DteContingencia $c, DteConfig $config, MhClient $mh): void
    {
        $this->reconcileOnline($c, $mh);

        $docs = $c->documentos()->where('estado', DteDocument::CONTINGENCY)->orderBy('id')->get();
        if ($docs->isEmpty()) {
            $c->update(['estado' => DteContingencia::DONE]);

            return;
        }
        if ($docs->count() > self::MAX_DOCS_POR_EVENTO) {
            $resto = DteContingencia::create([
                ...$c->only(['ambiente', 'tipo_contingencia', 'motivo', 'inicio', 'fin']),
                'estado' => DteContingencia::CLOSED,
            ]);
            DteDocument::whereIn('id', $docs->slice(self::MAX_DOCS_POR_EVENTO)->pluck('id'))
                ->update(['contingencia_id' => $resto->id]);
            $docs = $docs->take(self::MAX_DOCS_POR_EVENTO);
        }

        if (! $c->firma_electronica) {
            if ($errores = $config->errors()) {
                $c->update(['ultimo_error' => 'Faltan datos del emisor: '.implode(' ', $errores)]);

                return;
            }
            $codigo = strtoupper((string) Str::uuid());
            $json = json_encode(
                $this->buildEvent($c, $config, $docs, $codigo, Carbon::now()),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $c->update([
                'codigo_generacion' => $codigo,
                'json_content' => $json,
                'firma_electronica' => $this->signer->sign($json, $this->signer->loadKey($config)),
            ]);
        }

        $c->increment('intentos');
        $body = $mh->contingencia($c->firma_electronica);
        $mensaje = trim(($body['mensaje'] ?? '').' '.implode('; ', array_filter((array) ($body['observaciones'] ?? []))));

        if (strtoupper((string) ($body['estado'] ?? '')) === 'RECHAZADO') {
            $c->update(['estado' => DteContingencia::EVENT_REJECTED, 'mh_response' => $body, 'ultimo_error' => $mensaje ?: 'Evento rechazado.']);

            return;
        }
        if (($body['selloRecibido'] ?? '') !== '') {
            $c->update([
                'estado' => DteContingencia::EVENT_SENT,
                'sello_recibido' => $body['selloRecibido'],
                'evento_sellado_at' => now(),
                'mh_response' => $body,
                'ultimo_error' => null,
            ]);

            return;
        }
        $c->update(['mh_response' => $body, 'ultimo_error' => 'Respuesta al evento sin sello: '.($mensaje ?: 'sin detalle')]);
    }

    /**
     * Un documento enviado en línea y luego pasado a contingencia pudo llegar.
     * Si el MH lo tiene, la versión en línea es la válida: se restaura con su
     * sello y sale de la contingencia.
     */
    private function reconcileOnline(DteContingencia $c, MhClient $mh): void
    {
        $c->documentos()->where('estado', DteDocument::CONTINGENCY)->whereNotNull('json_en_linea')->get()
            ->each(function (DteDocument $d) use ($mh) {
                $r = $mh->consulta($d->tipo_dte, $d->codigo_generacion);
                if ($r?->accepted()) {
                    DteDocumentStates::transmitted($d, $r->sello, $r->body, [
                        'json_content' => $d->json_en_linea,
                        'firma_electronica' => $d->firma_en_linea,
                        'contingencia_id' => null,
                    ]);
                }
            });
    }

    private function sendLote(DteContingencia $c, MhClient $mh): void
    {
        $firmas = $c->documentos()->where('estado', DteDocument::CONTINGENCY)->orderBy('id')->pluck('firma_electronica');
        if ($firmas->isEmpty()) {
            $c->update(['estado' => DteContingencia::DONE]);

            return;
        }

        $c->increment('intentos');
        $body = $mh->lote($firmas->all());
        if (($body['codigoLote'] ?? '') === '') {
            $c->update(['mh_response' => $body, 'ultimo_error' => 'Lote no recibido: '.trim(($body['descripcionMsg'] ?? '').' '.implode('; ', (array) ($body['observaciones'] ?? [])))]);

            return;
        }
        $c->update([
            'estado' => DteContingencia::LOTE_SENT,
            'codigo_lote' => $body['codigoLote'],
            'lote_enviado_at' => now(),
            'mh_response' => $body,
            'ultimo_error' => null,
        ]);
    }

    private function pollLote(DteContingencia $c, MhClient $mh): void
    {
        $body = $mh->consultaLote($c->codigo_lote);

        foreach ((array) ($body['procesados'] ?? []) as $r) {
            $res = MhResult::fromResponse((array) $r);
            $d = $c->documentos()->where('codigo_generacion', $r['codigoGeneracion'] ?? '')->where('estado', DteDocument::CONTINGENCY)->first();
            if ($d && $res->accepted()) {
                DteDocumentStates::transmitted($d, $res->sello, $res->body);
            }
        }
        foreach ((array) ($body['rechazados'] ?? []) as $r) {
            $res = MhResult::fromResponse((array) $r);
            $d = $c->documentos()->where('codigo_generacion', $r['codigoGeneracion'] ?? '')->where('estado', DteDocument::CONTINGENCY)->first();
            if ($d) {
                DteDocumentStates::rejected($d, $res->message() ?: 'Rechazado en el lote.', $res->body);
            }
        }

        if (! $c->documentos()->where('estado', DteDocument::CONTINGENCY)->exists()) {
            $c->update(['estado' => DteContingencia::DONE, 'ultimo_error' => null]);
        }
    }

    /** Solo es obligatorio con el tipo 5 ("otro"); en los demás va null en el DTE y en el evento. */
    private static function motivoContin(DteContingencia $c): ?string
    {
        return $c->tipo_contingencia === 5 && $c->motivo ? mb_substr($c->motivo, 0, 500) : null;
    }
}
