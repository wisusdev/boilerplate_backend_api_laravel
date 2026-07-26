<?php

namespace App\Models;

use App\Models\Concerns\HasProductReviews;
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
    use HasFactory, InteractsWithMedia, HasProductReviews;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'sale_price',
        'child_price',
        'pricing_tiers',
        'vehicle_options',
        'booking_sections',
        'duration_days',
        'duration_nights',
        'min_advance_days',
        'cancellation_hours',
        'max_capacity',
        'location',
        'is_active',
        'is_featured',
        'category_id',
        'currency_code',
        'itinerary',
        'highlights',
        'includes',
        'excludes',
        'service_fees',
        'map_url',
        'map_markers',
        'faqs',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'child_price' => 'decimal:2',
        'pricing_tiers' => 'array',
        'vehicle_options' => 'array',
        'booking_sections' => 'array',
        'duration_days' => 'integer',
        'duration_nights' => 'integer',
        'min_advance_days' => 'integer',
        'cancellation_hours' => 'integer',
        'category_id' => 'integer',
        'max_capacity' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'itinerary' => 'array',
        'highlights' => 'array',
        'includes' => 'array',
        'excludes' => 'array',
        'service_fees' => 'array',
        'map_markers' => 'array',
        'faqs' => 'array',
    ];

    // ─── Scopes de filtrado (usados por allowedFilters del catálogo) ───────────
    public function scopeCategoryId($query, $value)
    {
        return $query->where('category_id', $value);
    }

    public function scopePriceMin($query, $value)
    {
        return $query->where('price', '>=', (float) $value);
    }

    public function scopePriceMax($query, $value)
    {
        return $query->where('price', '<=', (float) $value);
    }

    public function scopeSearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('title', 'LIKE', "%{$value}%")
                ->orWhere('location', 'LIKE', "%{$value}%");
        });
    }

    public function scopeIsFeatured($query, $value)
    {
        return $query->where('is_featured', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

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

    /**
     * Precio efectivo por persona, antes de tramos escalonados. Ya no hay
     * "precio de oferta": los descuentos provienen solo de los cupones que el
     * cliente ingresa al reservar.
     */
    public function effectiveUnitPrice(): float
    {
        return (float) $this->price;
    }

    /**
     * Descuento porcentual del tramo aplicable a un tamaño de grupo dado.
     * Se elige el tramo con mayor min_pax que no supere el nº de pasajeros.
     */
    public function tierDiscountPercent(int $pax): float
    {
        $tiers = is_array($this->pricing_tiers) ? $this->pricing_tiers : [];
        $bestPct = 0.0;
        $bestMin = -1;

        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            $minPax = (int) ($tier['min_pax'] ?? 0);
            $pct = (float) ($tier['discount_percent'] ?? 0);
            if ($minPax <= $pax && $minPax > $bestMin) {
                $bestMin = $minPax;
                $bestPct = $pct;
            }
        }

        return max(0.0, min(100.0, $bestPct));
    }

    /**
     * Precio por persona para un grupo: precio efectivo con el descuento del tramo.
     */
    public function unitPriceFor(int $pax): float
    {
        $pct = $this->tierDiscountPercent($pax);
        return round($this->effectiveUnitPrice() * (1 - $pct / 100), 2);
    }

    /**
     * Opciones de vehículo de paga configuradas (lista saneada de {vehicle_id, name, surcharge}).
     * `vehicle_id` referencia al vehículo del catálogo; `name`/`surcharge` son el snapshot editable.
     *
     * @return array<int, array{vehicle_id: int|null, name: string, surcharge: float}>
     */
    public function vehicleOptionsList(): array
    {
        $options = is_array($this->vehicle_options) ? $this->vehicle_options : [];

        return array_values(array_filter(array_map(function ($opt) {
            if (! is_array($opt) || trim((string) ($opt['name'] ?? '')) === '') {
                return null;
            }
            return [
                'vehicle_id' => isset($opt['vehicle_id']) && $opt['vehicle_id'] !== '' ? (int) $opt['vehicle_id'] : null,
                'name'       => (string) $opt['name'],
                'surcharge'  => round((float) ($opt['surcharge'] ?? 0), 2),
            ];
        }, $options)));
    }

    /**
     * Visibilidad de una sección del flujo de reserva. Por defecto, visible.
     */
    public function sectionVisible(string $key): bool
    {
        $sections = is_array($this->booking_sections) ? $this->booking_sections : [];
        return ! array_key_exists($key, $sections) || (bool) $sections[$key];
    }

    public function getResourceType(): string
    {
        return 'tours';
    }
}