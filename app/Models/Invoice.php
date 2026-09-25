<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    // DTE status constants
    public const DTE_NOT_GENERATED = 'not_generated';

    public const DTE_GENERATING = 'generating';

    /** Firmado y enviado (o por enviar) sin sello todavía: se reintenta solo. */
    public const DTE_PENDING = 'pending';

    public const DTE_SIGNED = 'signed';

    public const DTE_SENT = 'sent';

    public const DTE_ACCEPTED = 'accepted';

    public const DTE_REJECTED = 'rejected';

    public const DTE_ERROR = 'error';

    // DTE type constants (El Salvador MH, CAT-002)
    public const DTE_TYPE_CONSUMIDOR_FINAL = '01'; // Factura Consumidor Final

    public const DTE_TYPE_CREDITO_FISCAL = '03'; // Comprobante de Crédito Fiscal

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
        'receptor_document_type',
        'receptor_email',
        'notes',
        'mh_response',
        'dte_environment',
        'dte_submitted_at',
        'dte_accepted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
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

    /** Emitir por primera vez, sustituir uno rechazado o reintentar uno pendiente. */
    public function canGenerateDte(): bool
    {
        return in_array($this->dte_status, [
            self::DTE_NOT_GENERATED,
            self::DTE_ERROR,
            self::DTE_REJECTED,
            self::DTE_PENDING,
        ], true);
    }

    /**
     * Con un DTE sellado o en camino, la factura ya está declarada: cambiarla
     * haría que dijera algo distinto de lo que tiene Hacienda.
     */
    public function isDteLocked(): bool
    {
        return in_array($this->dte_status, [self::DTE_ACCEPTED, self::DTE_PENDING], true);
    }

    public function dteDocuments(): HasMany
    {
        return $this->hasMany(DteDocument::class);
    }

    public function getResourceType(): string
    {
        return 'invoices';
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Recalcula el total desde los conceptos. El importe de una factura nunca se
     * acepta del cliente: se deriva de sus líneas, igual que en las reservas.
     */
    public function recalculateTotal(): self
    {
        $total = $this->items()->sum('total');

        if ((float) $this->amount !== (float) $total) {
            $this->update(['amount' => round((float) $total, 2)]);
        }

        return $this;
    }

    /** Número visible del documento. */
    public function getNumberAttribute(): string
    {
        return 'INV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Una factura manual no nace de una reserva. */
    public function isManual(): bool
    {
        return $this->booking_id === null;
    }
}
