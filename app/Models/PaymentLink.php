<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Enlace de pago emitido a mano en el portal del banco.
 *
 * OJO con la relación entre los dos estados que hay en juego:
 *   - `payments.status`  → el DINERO. Única fuente de verdad del cobro.
 *   - `payment_links.status` → el ENLACE. Su ciclo de vida como documento.
 *
 * Solo `PaymentLinkService` escribe ambos, para que no puedan divergir.
 */
class PaymentLink extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /** Emitido, pero el agente todavía no ha pegado la URL del banco. */
    public const STATUS_DRAFT = 'draft';

    /** Con URL y enviado al cliente: esperando que pague. */
    public const STATUS_ACTIVE = 'active';

    /** El cliente dice que ya pagó. NO significa cobrado. */
    public const STATUS_REPORTED = 'reported';

    /** Cotejado contra el banco y dado por cobrado. */
    public const STATUS_CONFIRMED = 'confirmed';

    /**
     * Dejamos de esperar. NO significa que el enlace ya no cobre: no podemos
     * anularlo en el banco, así que un cobro tardío sigue siendo posible y el
     * enlace sigue siendo conciliable.
     */
    public const STATUS_EXPIRED = 'expired';

    /** Anulado antes de cobrarse (reserva cancelada, enlace equivocado). */
    public const STATUS_VOID = 'void';

    /** Estados en los que el enlace todavía puede acabar en un cobro. */
    public const OPEN_STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_REPORTED];

    protected $fillable = [
        'payment_id', 'provider', 'reference', 'url', 'status',
        'amount', 'currency_code', 'expires_at', 'created_by',
        'sent_at', 'sent_channel', 'reported_at', 'reported_ref',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        // Un comprobante puede ser la foto de una tarjeta. Disco privado y una
        // ruta con permisos; nunca una URL pública.
        $this->addMediaCollection('payment_proof')->useDisk('private');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** La reserva a la que pertenece el cobro (o null si el pagable es otra cosa). */
    public function booking(): ?Booking
    {
        $payable = $this->payment?->payable;

        return $payable instanceof Booking ? $payable : null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Caducado por fecha aunque nadie haya ejecutado todavía el cierre.
     * La caducidad es informativa: no impide confirmar un cobro tardío.
     */
    public function isPastDue(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Host del banco al que se redirige, para poder enseñárselo al cliente. */
    public function host(): ?string
    {
        return $this->url ? (parse_url($this->url, PHP_URL_HOST) ?: null) : null;
    }

    public function getResourceType(): string
    {
        return 'payment-links';
    }
}
