<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;

class MenuItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'menu_id',
        'title',
        'url',
        'type',
        'target_id',
        'target_type',
        'parent_id',
        'order',
        'icon',
        'css_class',
        'target_attr',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (MenuItem $item) {
            $item->menu?->clearCache();
        });

        static::deleted(function (MenuItem $item) {
            $item->menu?->clearCache();
        });
    }

    /**
     * Get the menu this item belongs to.
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get the parent item.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Get child items.
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get the target model (polymorphic).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the URL for this menu item.
     */
    public function getUrl(): string
    {
        if ($this->type === 'custom' || $this->type === 'external') {
            return $this->url ?? '#';
        }

        if ($this->target) {
            return match ($this->type) {
                'page' => $this->target->getUrl(),
                'post' => $this->target->getUrl(),
                'category' => route('blog.category', $this->target->slug),
                default => $this->url ?? '#',
            };
        }

        return $this->url ?? '#';
    }

    /**
     * Check if the menu item is active (current page).
     */
    public function isCurrentUrl(): bool
    {
        $currentUrl = url()->current();
        $itemUrl = $this->getUrl();

        // Exact match
        if ($currentUrl === $itemUrl) {
            return true;
        }

        // Check if current URL starts with item URL (for section highlighting)
        if ($itemUrl !== '/' && $itemUrl !== '#') {
            return str_starts_with($currentUrl, $itemUrl);
        }

        return false;
    }

    /**
     * Check if any child is active.
     */
    public function hasActiveChild(): bool
    {
        foreach ($this->children as $child) {
            if ($child->isCurrentUrl() || $child->hasActiveChild()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope for active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for top-level items.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get descendants (recursive).
     */
    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Move item to a new position.
     */
    public function moveTo(?string $parentId, int $order): bool
    {
        return $this->update([
            'parent_id' => $parentId,
            'order' => $order,
        ]);
    }
}
