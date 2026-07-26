<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    // DTE status constants
    public const DTE_NOT_GENERATED = 'not_generated';

    public const DTE_GENERATING = 'generating';

    public const DTE_SIGNED = 'signed';

    public const DTE_SENT = 'sent';

    public const DTE_ACCEPTED = 'accepted';

    public const DTE_REJECTED = 'rejected';

    public const DTE_ERROR = 'error';

    // DTE type constants (El Salvador MH)
    public const DTE_TYPE_CONSUMIDOR_FINAL = '03'; // Factura Consumidor Final

    public const DTE_TYPE_CREDITO_FISCAL = '01'; // Comprobante de Crédito Fiscal

    protected $fillable = [
        'booking_id',
        'amount',
        'currency_code',
        'status',
        'issued_at',
        // DTE fields
        'dte_type',
        'dte_number',
        'dte_generation_code',
        'dte_seal',
        'dte_status',
        'receptor_name',
        'receptor_document',
        'receptor_email',
        'dte_json',
        'mh_response',
        'dte_environment',
        'dte_submitted_at',
        'dte_accepted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'dte_json' => 'array',
        'mh_response' => 'array',
        'dte_submitted_at' => 'datetime',
        'dte_accepted_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isAccepted(): bool
    {
        return $this->dte_status === self::DTE_ACCEPTED;
    }

    public function canGenerateDte(): bool
    {
        return in_array($this->dte_status, [
            self::DTE_NOT_GENERATED,
            self::DTE_ERROR,
            self::DTE_REJECTED,
        ], true);
    }

    public function getResourceType(): string
    {
        return 'invoices';
    }
}
