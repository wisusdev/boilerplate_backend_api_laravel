<?php

namespace App\Models;

use App\Models\Contracts\DteOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Emitido en modelo diferido: válido para entregar, se transmite en el lote de su contingencia. */
    public const CONTINGENCY = 'contingency';

    /** Anulado con un evento de invalidación sellado. */
    public const INVALIDATED = 'invalidated';

    protected $fillable = [
        'invoice_id',
        'credit_note_id',
        'purchase_document_id',
        'contingencia_id',
        'tipo_dte',
        'ambiente',
        'version',
        'numero_control',
        'codigo_generacion',
        'estado',
        'json_content',
        'firma_electronica',
        'json_en_linea',
        'firma_en_linea',
        'sello_recibido',
        'fh_procesamiento',
        'mh_response',
        'ultimo_error',
        'intentos',
        'transmitido_at',
        'entregado_at',
        'entregado_a',
        'entregado_con_sello',
    ];

    protected $casts = [
        'version' => 'integer',
        'intentos' => 'integer',
        'mh_response' => 'array',
        'transmitido_at' => 'datetime',
        'entregado_at' => 'datetime',
        'entregado_con_sello' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function purchaseDocument(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    /** A quién pertenece el DTE: una factura, una nota o un documento de compra. */
    public function owner(): DteOwner
    {
        return $this->invoice ?? $this->creditNote ?? $this->purchaseDocument;
    }

    public function contingencia(): BelongsTo
    {
        return $this->belongsTo(DteContingencia::class, 'contingencia_id');
    }

    public function invalidaciones(): HasMany
    {
        return $this->hasMany(DteInvalidacion::class);
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        return json_decode($this->json_content, true) ?? [];
    }
}
