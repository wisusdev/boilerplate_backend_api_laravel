<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'featured_image',
        'meta',
        'order',
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

        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            // Ensure unique slug
            $originalSlug = $category->slug;
            $counter = 1;
            while (self::where('slug', $category->slug)->exists()) {
                $category->slug = $originalSlug . '-' . $counter++;
            }
        });
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the posts in this category.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Scope for top-level categories.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get the full path of the category.
     */
    public function getPath(): string
    {
        $segments = collect([$this->slug]);

        $parent = $this->parent;
        while ($parent) {
            $segments->prepend($parent->slug);
            $parent = $parent->parent;
        }

        return $segments->implode('/');
    }

    /**
     * Get breadcrumbs for the category.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            ['name' => $this->name, 'slug' => $this->slug]
        ];

        $parent = $this->parent;
        while ($parent) {
            array_unshift($breadcrumbs, [
                'name' => $parent->name,
                'slug' => $parent->slug
            ]);
            $parent = $parent->parent;
        }

        return $breadcrumbs;
    }

    /**
     * Get post count including subcategories.
     */
    public function getTotalPostsCount(): int
    {
        $count = $this->posts()->published()->count();

        foreach ($this->children as $child) {
            $count += $child->getTotalPostsCount();
        }

        return $count;
    }
}
