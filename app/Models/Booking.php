<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_TOUR = 'tour';

    public const TYPE_TRANSPORT = 'transport';

    /**
     * Discriminador lógico (booking_type) ↔ clase del bookable polimórfico.
     * `booking_type` se deriva de `bookable_type`; no se persiste para evitar
     * que ambos campos puedan desincronizarse.
     */
    public const BOOKABLE_MAP = [
        self::TYPE_TOUR => Tour::class,
        self::TYPE_TRANSPORT => TransportVehicle::class,
    ];

    protected $fillable = [
        'user_id',
        'bookable_type',
        'bookable_id',
        'upgrade_vehicle_id',
        'upgrade_label',
        'starts_at',
        'ends_at',
        'party_size',
        'total_price',
        'service_fees',
        'upgrade_surcharge',
        'coupon_id',
        'discount_amount',
        'currency_code',
        'status',
        'notes',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'party_size' => 'integer',
        'total_price' => 'decimal:2',
        'service_fees' => 'array',
        'upgrade_surcharge' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
    ];

    /**
     * booking_type derivado de bookable_type (no es una columna).
     */
    public function getBookingTypeAttribute(): ?string
    {
        return array_search($this->bookable_type, self::BOOKABLE_MAP, true) ?: null;
    }

    /**
     * Resuelve la clase del bookable a partir del tipo lógico ('tour'|'transport').
     */
    public static function bookableClassFor(string $type): ?string
    {
        return self::BOOKABLE_MAP[$type] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transportDetail(): HasOne
    {
        return $this->hasOne(TransportBookingDetail::class);
    }

    /**
     * Vehículo referenciado por la opción elegida (tours). Puede ser null si la opción
     * no estaba ligada a un vehículo o si el vehículo fue eliminado (nullOnDelete).
     */
    public function upgradeVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'upgrade_vehicle_id');
    }

    /**
     * Cupón aplicado a la reserva (opcional).
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function getResourceType(): string
    {
        return 'bookings';
    }
}
