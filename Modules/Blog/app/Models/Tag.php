<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }

            // Ensure unique slug
            $originalSlug = $tag->slug;
            $counter = 1;
            while (self::where('slug', $tag->slug)->exists()) {
                $tag->slug = $originalSlug . '-' . $counter++;
            }
        });
    }

    /**
     * Get the posts with this tag.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Get post count.
     */
    public function getPostsCount(): int
    {
        return $this->posts()->published()->count();
    }

    /**
     * Scope for popular tags.
     */
    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount(['posts' => function ($q) {
            $q->where('status', 'published');
        }])
            ->orderByDesc('posts_count')
            ->limit($limit);
    }

    /**
     * Find or create a tag by name.
     */
    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug($name);

        return self::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );
    }

    /**
     * Sync tags from a comma-separated string.
     */
    public static function syncFromString(string $tagsString): array
    {
        $tagNames = array_map('trim', explode(',', $tagsString));
        $tagIds = [];

        foreach ($tagNames as $name) {
            if (!empty($name)) {
                $tag = self::findOrCreateByName($name);
                $tagIds[] = $tag->id;
            }
        }

        return $tagIds;
    }
}
