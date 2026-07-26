<?php

namespace App\Models;

use App\Models\Concerns\HasProductReviews;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransportVehicle extends Model implements HasMedia
{
    use HasFactory, HasProductReviews, InteractsWithMedia;

    protected $fillable = [
        'title',
        'vehicle_type',
        'description',
        'location',
        'hourly_rate',
        'sale_hourly_rate',
        'daily_rate',
        'sale_daily_rate',
        'capacity',
        'currency_code',
        'features',
        'is_active',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'sale_hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'sale_daily_rate' => 'decimal:2',
        'capacity' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    // ─── Scopes de filtrado (usados por allowedFilters del catálogo) ───────────
    public function scopeVehicleType($query, $value)
    {
        return $query->where('vehicle_type', $value);
    }

    public function scopeCapacityMin($query, $value)
    {
        return $query->where('capacity', '>=', (int) $value);
    }

    public function scopeRateMin($query, $value)
    {
        return $query->where('daily_rate', '>=', (float) $value);
    }

    public function scopeRateMax($query, $value)
    {
        return $query->where('daily_rate', '<=', (float) $value);
    }

    public function scopeSearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('title', 'LIKE', "%{$value}%")
                ->orWhere('location', 'LIKE', "%{$value}%");
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->performOnCollections('featured_image', 'gallery');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'bookable_id')
            ->where('bookable_type', self::class);
    }

    public function getResourceType(): string
    {
        return 'transport_vehicles';
    }
}
