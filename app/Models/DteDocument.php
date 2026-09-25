<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un DTE emitido: el JSON exacto que se firmó, el JWS que se envía y su estado
 * frente al Ministerio de Hacienda. Solo con sello tiene validez tributaria.
 */
class DteDocument extends Model
{
    /** Firmado, sin sello: sin respuesta del MH o envío interrumpido. */
    public const PENDING = 'pending';

    /** Con sello de recepción: es un DTE válido. */
    public const TRANSMITTED = 'transmitted';

    /** El MH lo rechazó; nunca tuvo validez. Se emite uno nuevo. */
    public const REJECTED = 'rejected';

    protected $fillable = [
        'invoice_id',
        'tipo_dte',
        'ambiente',
        'version',
        'numero_control',
        'codigo_generacion',
        'estado',
        'json_content',
        'firma_electronica',
        'sello_recibido',
        'fh_procesamiento',
        'mh_response',
        'ultimo_error',
        'intentos',
        'transmitido_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'intentos' => 'integer',
        'mh_response' => 'array',
        'transmitido_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        return json_decode($this->json_content, true) ?? [];
    }
}
