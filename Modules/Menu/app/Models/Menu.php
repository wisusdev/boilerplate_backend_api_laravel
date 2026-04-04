<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Menu extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'location',
        'description',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Menu $menu) {
            if (empty($menu->slug)) {
                $menu->slug = Str::slug($menu->name);
            }
        });

        static::saved(function () {
            Cache::forget('cms.menus.all');
        });

        static::deleted(function () {
            Cache::forget('cms.menus.all');
        });
    }

    /**
     * Get the menu items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('order');
    }

    /**
     * Get top-level menu items.
     */
    public function topLevelItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * Get a hierarchical tree of menu items.
     */
    public function getTree(): array
    {
        $cacheKey = "cms.menu.{$this->id}.tree";

        return Cache::remember($cacheKey, now()->addHour(), function () {
            $items = $this->items()
                ->where('is_active', true)
                ->with('target')
                ->get();

            return $this->buildTree($items);
        });
    }

    /**
     * Build a tree from flat collection.
     */
    protected function buildTree($items, ?string $parentId = null): array
    {
        $branch = [];

        foreach ($items as $item) {
            if ($item->parent_id === $parentId) {
                $children = $this->buildTree($items, $item->id);

                $branch[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'url' => $item->getUrl(),
                    'target' => $item->target_attr,
                    'type' => $item->type ?? 'custom',
                    'icon' => $item->icon,
                    'css_class' => $item->css_class,
                    'children' => $children,
                    'meta' => $item->meta,
                ];
            }
        }

        return $branch;
    }

    /**
     * Clear menu cache.
     */
    public function clearCache(): void
    {
        Cache::forget("cms.menu.{$this->id}.tree");
    }

    /**
     * Get menu by location.
     */
    public static function findByLocation(string $location): ?self
    {
        return self::where('location', $location)->first();
    }

    /**
     * Get all menus with their locations.
     */
    public static function getAllWithLocations(): array
    {
        return Cache::remember('cms.menus.all', now()->addHour(), function () {
            return self::all()->mapWithKeys(fn ($menu) => [
                $menu->location ?? $menu->slug => $menu
            ])->toArray();
        });
    }

    /**
     * Assign this menu to a location.
     */
    public function assignToLocation(string $location): bool
    {
        // Remove any other menu from this location
        self::where('location', $location)
            ->where('id', '!=', $this->id)
            ->update(['location' => null]);

        return $this->update(['location' => $location]);
    }
}
