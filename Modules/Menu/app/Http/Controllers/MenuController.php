<?php

namespace Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuItem;
use Modules\Menu\Services\MenuService;

class MenuController extends Controller
{
    public function __construct(
        protected MenuService $menuService
    ) {}

    /**
     * Display a listing of menus.
     */
    public function index(): View
    {
        $menus = $this->menuService->getAll();
        $locations = $this->menuService->getLocations();

        return view('menu::admin.index', compact('menus', 'locations'));
    }

    /**
     * Show the form for creating a new menu.
     */
    public function create(): View
    {
        $locations = $this->menuService->getLocations();

        return view('menu::admin.create', compact('locations'));
    }

    /**
     * Store a newly created menu.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $menu = $this->menuService->create($validated);

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', __('Menu created successfully.'));
    }

    /**
     * Show the form for editing the specified menu.
     */
    public function edit(Menu $menu): View
    {
        $menu->load(['items' => function ($query) {
            $query->orderBy('order');
        }]);

        $locations = $this->menuService->getLocations();
        $itemTypes = $this->menuService->getItemTypes();
        $menuTree = $this->menuService->getMenuTree($menu);

        return view('menu::admin.edit', compact('menu', 'locations', 'itemTypes', 'menuTree'));
    }

    /**
     * Update the specified menu.
     */
    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $this->menuService->update($menu, $validated);

        return redirect()
            ->route('admin.menus.edit', $menu)
            ->with('success', __('Menu updated successfully.'));
    }

    /**
     * Remove the specified menu.
     */
    public function destroy(Menu $menu): RedirectResponse
    {
        $this->menuService->delete($menu);

        return redirect()
            ->route('admin.menus.index')
            ->with('success', __('Menu deleted successfully.'));
    }

    /**
     * Add item to menu (AJAX).
     */
    public function addItem(Request $request, Menu $menu): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:custom,page,post,category',
            'url' => 'nullable|string|max:500',
            'target_type' => 'nullable|string|max:255',
            'target_id' => 'nullable|uuid',
            'parent_id' => 'nullable|uuid|exists:menu_items,id',
            'css_class' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'open_in_new_tab' => 'nullable|boolean',
        ]);

        $item = $this->menuService->addItem($menu, $validated);

        return response()->json([
            'success' => true,
            'message' => __('Menu item added successfully.'),
            'data' => $item,
        ]);
    }

    /**
     * Update menu item (AJAX).
     */
    public function updateItem(Request $request, MenuItem $item): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|string|in:custom,page,post,category',
            'url' => 'nullable|string|max:500',
            'target_type' => 'nullable|string|max:255',
            'target_id' => 'nullable|uuid',
            'parent_id' => 'nullable|uuid|exists:menu_items,id',
            'css_class' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'open_in_new_tab' => 'nullable|boolean',
        ]);

        $item = $this->menuService->updateItem($item, $validated);

        return response()->json([
            'success' => true,
            'message' => __('Menu item updated successfully.'),
            'data' => $item,
        ]);
    }

    /**
     * Delete menu item (AJAX).
     */
    public function deleteItem(MenuItem $item): JsonResponse
    {
        $this->menuService->deleteItem($item);

        return response()->json([
            'success' => true,
            'message' => __('Menu item deleted successfully.'),
        ]);
    }

    /**
     * Reorder menu items (AJAX).
     */
    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid|exists:menu_items,id',
            'items.*.parent_id' => 'nullable|uuid|exists:menu_items,id',
        ]);

        $this->menuService->reorderItems($menu, $request->items);

        return response()->json([
            'success' => true,
            'message' => __('Menu items reordered successfully.'),
        ]);
    }

    /**
     * Get linkable items for a type (AJAX).
     */
    public function linkableItems(string $type): JsonResponse
    {
        return response()->json([
            'data' => $this->menuService->getLinkableItems($type),
        ]);
    }
}
