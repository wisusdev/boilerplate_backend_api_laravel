<?php

namespace Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'content_html',
        'status',
        'author_id',
        'featured_image',
        'meta',
        'allow_comments',
        'is_featured',
        'views_count',
        'published_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'allow_comments' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Post $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }

            // Ensure unique slug
            $originalSlug = $post->slug;
            $counter = 1;
            while (self::where('slug', $post->slug)->exists()) {
                $post->slug = $originalSlug . '-' . $counter++;
            }

            // Generate excerpt if not provided
            if (empty($post->excerpt) && !empty($post->content)) {
                $post->excerpt = Str::limit(strip_tags($post->content), 160);
            }
        });
    }

    /**
     * Get the author of the post.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the categories for the post.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get the tags for the post.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the comments for the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get approved comments count.
     */
    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved');
    }

    /**
     * Scope for published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope for featured posts.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for draft posts.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for posts in a category.
     */
    public function scopeInCategory($query, $categorySlug)
    {
        return $query->whereHas('categories', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    /**
     * Scope for posts with a tag.
     */
    public function scopeWithTag($query, $tagSlug)
    {
        return $query->whereHas('tags', function ($q) use ($tagSlug) {
            $q->where('slug', $tagSlug);
        });
    }

    /**
     * Check if post is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at <= now());
    }

    /**
     * Increment views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Get reading time in minutes.
     */
    public function getReadingTime(): int
    {
        $wordsPerMinute = 200;
        $wordCount = str_word_count(strip_tags($this->content ?? ''));

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    /**
     * Get SEO title.
     */
    public function getSeoTitle(): string
    {
        return $this->meta['seo_title'] ?? $this->title;
    }

    /**
     * Get SEO description.
     */
    public function getSeoDescription(): string
    {
        return $this->meta['seo_description'] ?? $this->excerpt ?? '';
    }

    /**
     * Get the URL for the post.
     */
    public function getUrl(): string
    {
        return route('blog.show', $this->slug);
    }

    /**
     * Get related posts.
     */
    public function getRelatedPosts(int $limit = 4)
    {
        $categoryIds = $this->categories->pluck('id');
        $tagIds = $this->tags->pluck('id');

        return self::published()
            ->where('id', '!=', $this->id)
            ->where(function ($query) use ($categoryIds, $tagIds) {
                $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
                    ->orWhereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
