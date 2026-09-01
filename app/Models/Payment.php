<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'gateway',
        'method',
        'amount',
        'currency_code',
        'status',
        'transaction_reference',
        'payload',
        'paid_at',
        'confirmed_by',
        'confirmed_at',
        'confirmation_note',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function link(): HasOne
    {
        return $this->hasOne(PaymentLink::class);
    }

    /** Quién dio el cobro por bueno (solo en cobros confirmados a mano). */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Quién anuló el cobro (contracargo o confirmación equivocada). */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** Un cobro anulado no cuenta como cobrado aunque conserve su historial. */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function getResourceType(): string
    {
        return 'payments';
    }
}
