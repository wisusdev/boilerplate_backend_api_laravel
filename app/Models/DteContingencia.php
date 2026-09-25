<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Periodo en que el MH no respondió (Manual Funcional v2, VIII).
 *
 * open → closed (el MH volvió) → event_sent (evento sellado) → lote_sent →
 * done. Un evento rechazado queda en event_rejected hasta que se rearma.
 */
class DteContingencia extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const EVENT_SENT = 'event_sent';

    public const EVENT_REJECTED = 'event_rejected';

    public const LOTE_SENT = 'lote_sent';

    public const DONE = 'done';

    /** CAT-005 1: no disponibilidad de sistema del MH. */
    public const TIPO_MH_NO_DISPONIBLE = 1;

    protected $table = 'dte_contingencias';

    protected $fillable = [
        'ambiente',
        'tipo_contingencia',
        'motivo',
        'estado',
        'inicio',
        'fin',
        'codigo_generacion',
        'json_content',
        'firma_electronica',
        'sello_recibido',
        'evento_sellado_at',
        'codigo_lote',
        'lote_enviado_at',
        'mh_response',
        'ultimo_error',
        'intentos',
    ];

    protected $casts = [
        'tipo_contingencia' => 'integer',
        'intentos' => 'integer',
        'inicio' => 'datetime',
        'fin' => 'datetime',
        'evento_sellado_at' => 'datetime',
        'lote_enviado_at' => 'datetime',
        'mh_response' => 'array',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(DteDocument::class, 'contingencia_id');
    }

    /** El evento se transmite en 24 h desde que cesó la causa. */
    public function eventoVenceAt(): ?Carbon
    {
        return $this->fin?->copy()->addHours(24);
    }

    /** El lote se transmite en 72 h desde el sello del evento. */
    public function loteVenceAt(): ?Carbon
    {
        return $this->evento_sellado_at?->copy()->addHours(72);
    }

    public function isFinished(): bool
    {
        return $this->estado === self::DONE;
    }
}
