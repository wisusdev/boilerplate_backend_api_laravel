<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pin del mapa público: un lugar con información de los tours y, opcionalmente,
 * un video de Instagram o un enlace.
 */
class MapPin extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Iconos permitidos (Bootstrap Icons, sin el prefijo `bi-`). Una lista
     * cerrada: el nombre acaba en un atributo `class` del mapa público.
     */
    public const ICONS = [
        'geo-alt-fill', 'camera-fill', 'camera-reels-fill', 'tree-fill', 'water', 'sun-fill',
        'cup-hot-fill', 'shop', 'bank', 'building', 'flag-fill', 'star-fill', 'heart-fill',
        'compass-fill', 'binoculars-fill', 'bicycle', 'bus-front-fill', 'house-fill', 'fire', 'music-note-beamed',
    ];

    protected $fillable = [
        'title',
        'description',
        'latitude',
        'longitude',
        'icon',
        'color',
        'tour_id',
        'instagram_url',
        'link_url',
        'link_label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // El iframe se arma solo con el tipo y el código extraídos aquí; nunca
        // con la URL tal como la escribió alguien.
        static::saving(function (MapPin $pin) {
            if ($pin->isDirty('instagram_url')) {
                $post = self::parseInstagram($pin->instagram_url);
                $pin->instagram_type = $post['type'] ?? null;
                $pin->instagram_code = $post['code'] ?? null;
            }
        });
    }

    /**
     * Tipo y código de un post, reel o video de Instagram, o null si la URL no
     * es uno (un perfil, una historia, otro dominio).
     *
     * @return array{type: string, code: string}|null
     */
    public static function parseInstagram(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || ! in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
            return null;
        }

        // /p/{code}, /reel/{code}, /reels/{code}, /tv/{code}; también con el
        // usuario delante (/{usuario}/reel/{code}).
        if (! preg_match('#^/(?:[A-Za-z0-9._]+/)?(p|reels?|tv)/([A-Za-z0-9_-]{5,40})/?$#', $parts['path'] ?? '', $m)) {
            return null;
        }

        return ['type' => $m[1] === 'reels' ? 'reel' : $m[1], 'code' => $m[2]];
    }

    /** Reproductor oficial de Instagram para este post. */
    public function instagramEmbedUrl(): ?string
    {
        return $this->instagram_code
            ? "https://www.instagram.com/{$this->instagram_type}/{$this->instagram_code}/embed/"
            : null;
    }

    /** El post en Instagram, por si el reproductor no carga (cuenta privada, post borrado). */
    public function instagramPermalink(): ?string
    {
        return $this->instagram_code
            ? "https://www.instagram.com/{$this->instagram_type}/{$this->instagram_code}/"
            : null;
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(800)
            ->height(500)
            ->performOnCollections('image');
    }

    public function getResourceType(): string
    {
        return 'map-pins';
    }
}
