<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Tour extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'price',
        'max_capacity',
        'location',
        'is_active',
        'category',
        'currency_code',
        'itinerary',
        'highlights',
        'map_url',
        'map_markers',
        'faqs',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_capacity' => 'integer',
        'is_active' => 'boolean',
        'itinerary' => 'array',
        'highlights' => 'array',
        'map_markers' => 'array',
        'faqs' => 'array',
    ];

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

    public function availabilities(): HasMany
    {
        return $this->hasMany(TourAvailability::class);
    }

    public function getResourceType(): string
    {
        return 'tours';
    }
}