<?php

namespace Modules\Menu\Services;

use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    /**
     * Get all menus.
     */
    public function getAll()
    {
        return Menu::with(['items' => function ($query) {
            $query->orderBy('order');
        }])->orderBy('name')->get();
    }

    /**
     * Get a menu by ID or location.
     */
    public function find(string $identifier): ?Menu
    {
        return Menu::with(['items' => function ($query) {
            $query->orderBy('order');
        }])->where('id', $identifier)
            ->orWhere('location', $identifier)
            ->first();
    }

    /**
     * Get menu by location with caching.
     */
    public function getByLocation(string $location): ?Menu
    {
        return Cache::remember("menu.{$location}", 3600, function () use ($location) {
            return Menu::with(['items' => function ($query) {
                $query->orderBy('order');
            }])->where('location', $location)->first();
        });
    }

    /**
     * Create a new menu.
     */
    public function create(array $data): Menu
    {
        return Menu::create($data);
    }

    /**
     * Update a menu.
     */
    public function update(Menu $menu, array $data): Menu
    {
        $menu->update($data);

        // Clear cache if location changed
        if (isset($data['location'])) {
            $this->clearMenuCache($menu);
        }

        return $menu->fresh();
    }

    /**
     * Delete a menu.
     */
    public function delete(Menu $menu): bool
    {
        $this->clearMenuCache($menu);

        return $menu->delete();
    }

    /**
     * Add item to menu.
     */
    public function addItem(Menu $menu, array $data): MenuItem
    {
        $data['menu_id'] = $menu->id;
        $data['order'] = $data['order'] ?? $menu->items()->max('order') + 1;

        $item = MenuItem::create($data);

        $this->clearMenuCache($menu);

        return $item;
    }

    /**
     * Update menu item.
     */
    public function updateItem(MenuItem $item, array $data): MenuItem
    {
        $item->update($data);

        $this->clearMenuCache($item->menu);

        return $item->fresh();
    }

    /**
     * Delete menu item.
     */
    public function deleteItem(MenuItem $item): bool
    {
        $menu = $item->menu;

        // Delete children first
        $item->children()->delete();
        $deleted = $item->delete();

        $this->clearMenuCache($menu);

        return $deleted;
    }

    /**
     * Reorder menu items.
     */
    public function reorderItems(Menu $menu, array $items): void
    {
        foreach ($items as $index => $itemData) {
            MenuItem::where('id', $itemData['id'])->update([
                'order' => $index,
                'parent_id' => $itemData['parent_id'] ?? null,
            ]);
        }

        $this->clearMenuCache($menu);
    }

    /**
     * Get menu tree structure.
     */
    public function getMenuTree(Menu $menu): array
    {
        return $menu->getTree();
    }

    /**
     * Get available menu item types.
     */
    public function getItemTypes(): array
    {
        return [
            'custom' => [
                'label' => __('Custom Link'),
                'fields' => ['url'],
            ],
            'page' => [
                'label' => __('Page'),
                'model' => \Modules\Pages\Models\Page::class,
                'field' => 'title',
            ],
            'post' => [
                'label' => __('Blog Post'),
                'model' => \Modules\Blog\Models\Post::class,
                'field' => 'title',
            ],
            'category' => [
                'label' => __('Category'),
                'model' => \Modules\Blog\Models\Category::class,
                'field' => 'name',
            ],
        ];
    }

    /**
     * Get available linkable items for a type.
     */
    public function getLinkableItems(string $type): array
    {
        $types = $this->getItemTypes();

        if (!isset($types[$type]) || !isset($types[$type]['model'])) {
            return [];
        }

        $model = $types[$type]['model'];
        $field = $types[$type]['field'];

        return $model::orderBy($field)->get(['id', $field . ' as label'])->toArray();
    }

    /**
     * Get available locations.
     */
    public function getLocations(): array
    {
        return [
            'primary' => __('Primary Navigation'),
            'secondary' => __('Secondary Navigation'),
            'footer' => __('Footer Menu'),
            'mobile' => __('Mobile Menu'),
            'sidebar' => __('Sidebar Menu'),
        ];
    }

    /**
     * Clear menu cache.
     */
    protected function clearMenuCache(Menu $menu): void
    {
        if ($menu->location) {
            Cache::forget("menu.{$menu->location}");
        }
        Cache::forget("menu.{$menu->id}");
    }
}
