<?php

namespace App\Models;

use App\Models\Concerns\HasDteDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota de crédito (05) o de débito (06): ajusta a la baja o al alza un CCF ya
 * sellado. Sus importes van sin IVA, como el CCF.
 */
class CreditNote extends Model
{
    use HasDteDocuments;

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    protected $fillable = [
        'invoice_id',
        'kind',
        'number',
        'motivo',
        'subtotal',
        'iva',
        'total',
        'created_by',
        'dte_type',
        'dte_status',
        'dte_number',
        'dte_generation_code',
        'dte_seal',
        'dte_environment',
        'dte_submitted_at',
        'dte_accepted_at',
        'mh_response',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'mh_response' => 'array',
        'dte_submitted_at' => 'datetime',
        'dte_accepted_at' => 'datetime',
    ];

    public function dteForeignKey(): string
    {
        return 'credit_note_id';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** 05 nota de crédito, 06 nota de débito. */
    public function tipoDte(): string
    {
        return $this->kind === self::DEBIT ? '06' : '05';
    }

    public function isDebit(): bool
    {
        return $this->kind === self::DEBIT;
    }

    public function getResourceType(): string
    {
        return 'credit-notes';
    }
}
