<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de invalidación de un DTE sellado (esquema invalidacion v3). Solo con
 * el sello del MH el DTE deja de tener validez.
 */
class DteInvalidacion extends Model
{
    public const PENDING = 'pending';

    public const TRANSMITTED = 'transmitted';

    public const REJECTED = 'rejected';

    /** CAT-024 */
    public const TIPO_ERROR = 1;

    public const TIPO_RESCINDIR = 2;

    public const TIPO_OTRO = 3;

    protected $table = 'dte_invalidaciones';

    protected $fillable = [
        'dte_document_id',
        'tipo_anulacion',
        'motivo',
        'codigo_generacion',
        'codigo_generacion_r',
        'solicita_nombre',
        'estado',
        'json_content',
        'firma_electronica',
        'sello_recibido',
        'mh_response',
        'ultimo_error',
        'intentos',
        'transmitido_at',
        'created_by',
    ];

    protected $casts = [
        'tipo_anulacion' => 'integer',
        'intentos' => 'integer',
        'mh_response' => 'array',
        'transmitido_at' => 'datetime',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DteDocument::class, 'dte_document_id');
    }
}
