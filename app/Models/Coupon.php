<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public const SCOPE_ALL = 'all';

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'max_discount',
        'min_pax',
        'min_amount',
        'applies_to',
        'usage_limit',
        'used_count',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value'          => 'decimal:2',
        'max_discount'   => 'decimal:2',
        'min_pax'        => 'integer',
        'min_amount'     => 'decimal:2',
        'usage_limit'    => 'integer',
        'used_count'     => 'integer',
        'per_user_limit' => 'integer',
        'starts_at'      => 'datetime',
        'expires_at'     => 'datetime',
        'is_active'      => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Normaliza el código a mayúsculas sin espacios (búsqueda case-insensitive).
     */
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    /**
     * ¿El cupón está dentro de su ventana de vigencia y activo?
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $now = Carbon::now();
        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->expires_at !== null && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Calcula el descuento para un subtotal dado, respetando el tope y el subtotal.
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === self::TYPE_PERCENTAGE
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        // Nunca descontar más que el subtotal.
        return round(min($discount, $subtotal), 2);
    }

    public function getResourceType(): string
    {
        return 'coupons';
    }
}
