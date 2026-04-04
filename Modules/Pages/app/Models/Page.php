<?php

namespace Modules\Pages\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'content_html',
        'content_json',
        'content_css',
        'template',
        'layout',
        'status',
        'author_id',
        'parent_id',
        'order',
        'featured_image',
        'meta',
        'published_at',
    ];

    protected $casts = [
        'content_json' => 'array',
        'content_css' => 'array',
        'meta' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Page $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }

            // Ensure unique slug
            $originalSlug = $page->slug;
            $counter = 1;
            while (self::where('slug', $page->slug)->exists()) {
                $page->slug = $originalSlug . '-' . $counter++;
            }
        });

        static::updating(function (Page $page) {
            // Create revision on update
            if ($page->isDirty(['title', 'content', 'content_html', 'content_json', 'content_css', 'meta'])) {
                $page->createRevision();
            }
        });
    }

    /**
     * Get the author of the page.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the parent page.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * Get the child pages.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get the page revisions.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('created_at');
    }

    /**
     * Create a revision of the current page state.
     */
    public function createRevision(): PageRevision
    {
        return $this->revisions()->create([
            'user_id' => auth()->id() ?? $this->author_id,
            'title' => $this->getOriginal('title'),
            'content' => $this->getOriginal('content'),
            'content_html' => $this->getOriginal('content_html'),
            'content_json' => $this->getOriginal('content_json'),
            'content_css' => $this->getOriginal('content_css'),
            'meta' => $this->getOriginal('meta'),
        ]);
    }

    /**
     * Restore from a revision.
     */
    public function restoreRevision(PageRevision $revision): bool
    {
        return $this->update([
            'title' => $revision->title,
            'content' => $revision->content,
            'content_html' => $revision->content_html,
            'content_json' => $revision->content_json,
            'content_css' => $revision->content_css,
            'meta' => $revision->meta,
        ]);
    }

    /**
     * Scope for published pages.
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
     * Scope for draft pages.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for top-level pages (no parent).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Check if page is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at <= now());
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
    public function getSeoDescription(): ?string
    {
        return $this->meta['seo_description'] ?? null;
    }

    /**
     * Get SEO keywords.
     */
    public function getSeoKeywords(): ?string
    {
        return $this->meta['seo_keywords'] ?? null;
    }

    /**
     * Get the URL for the page.
     */
    public function getUrl(): string
    {
        $segments = collect([$this->slug]);

        $parent = $this->parent;
        while ($parent) {
            $segments->prepend($parent->slug);
            $parent = $parent->parent;
        }

        return '/' . $segments->implode('/');
    }

    /**
     * Get breadcrumbs for the page.
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            ['title' => $this->title, 'url' => $this->getUrl()]
        ];

        $parent = $this->parent;
        while ($parent) {
            array_unshift($breadcrumbs, [
                'title' => $parent->title,
                'url' => $parent->getUrl()
            ]);
            $parent = $parent->parent;
        }

        return $breadcrumbs;
    }
}
