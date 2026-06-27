<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Tour extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'max_capacity',
        'location',
        'is_active',
        'category_id',
        'currency_code',
        'itinerary',
        'highlights',
        'map_url',
        'map_markers',
        'faqs',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'category_id' => 'integer',
        'max_capacity' => 'integer',
        'is_active' => 'boolean',
        'itinerary' => 'array',
        'highlights' => 'array',
        'map_markers' => 'array',
        'faqs' => 'array',
    ];

    protected static function booted(): void
    {
        // Genera un slug único a partir del título al crear (si no se envía uno).
        // No se regenera al editar para no romper URLs/bookmarks existentes.
        static::creating(function (self $tour) {
            if (empty($tour->slug)) {
                $tour->slug = self::uniqueSlug((string) $tour->title);
            }
        });
    }

    protected static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'tour';
        $slug = $base;
        $i = 2;
        while (self::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    /**
     * Permite resolver tours por slug (público) o por id numérico (admin).
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where(is_numeric($value) ? 'id' : 'slug', $value)->firstOrFail();
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'bookable_id')
            ->where('bookable_type', self::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TourAvailability::class);
    }

    public function getResourceType(): string
    {
        return 'tours';
    }
}