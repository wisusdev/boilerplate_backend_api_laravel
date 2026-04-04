<?php

namespace Modules\Menu\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function index(): JsonResponse
    {
        $menus = $this->menuService->getAll();

        return response()->json(['data' => $menus]);
    }

    /**
     * Store a newly created menu.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $menu = $this->menuService->create($validated);

        return response()->json([
            'message' => 'Menu created successfully.',
            'data' => $menu,
        ], 201);
    }

    /**
     * Display the specified menu.
     */
    public function show(string $id): JsonResponse
    {
        $menu = $this->menuService->find($id);

        if (!$menu) {
            abort(404, 'Menu not found');
        }

        return response()->json([
            'data' => $menu,
            'tree' => $this->menuService->getMenuTree($menu),
        ]);
    }

    /**
     * Update the specified menu.
     */
    public function update(Request $request, Menu $menu): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $menu = $this->menuService->update($menu, $validated);

        return response()->json([
            'message' => 'Menu updated successfully.',
            'data' => $menu,
        ]);
    }

    /**
     * Remove the specified menu.
     */
    public function destroy(Menu $menu): JsonResponse
    {
        $this->menuService->delete($menu);

        return response()->json(null, 204);
    }

    /**
     * Get menu by location.
     */
    public function byLocation(string $location): JsonResponse
    {
        $menu = $this->menuService->getByLocation($location);

        if (!$menu) {
            abort(404, 'Menu not found for location: ' . $location);
        }

        return response()->json([
            'data' => $menu,
            'tree' => $this->menuService->getMenuTree($menu),
        ]);
    }

    /**
     * Add item to menu.
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
            'order' => 'nullable|integer|min:0',
            'css_class' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'open_in_new_tab' => 'nullable|boolean',
        ]);

        $item = $this->menuService->addItem($menu, $validated);

        return response()->json([
            'message' => 'Menu item added successfully.',
            'data' => $item,
        ], 201);
    }

    /**
     * Update menu item.
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
            'order' => 'nullable|integer|min:0',
            'css_class' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'open_in_new_tab' => 'nullable|boolean',
        ]);

        $item = $this->menuService->updateItem($item, $validated);

        return response()->json([
            'message' => 'Menu item updated successfully.',
            'data' => $item,
        ]);
    }

    /**
     * Delete menu item.
     */
    public function deleteItem(MenuItem $item): JsonResponse
    {
        $this->menuService->deleteItem($item);

        return response()->json(null, 204);
    }

    /**
     * Reorder menu items.
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
            'message' => 'Menu items reordered successfully.',
        ]);
    }

    /**
     * Get available item types.
     */
    public function itemTypes(): JsonResponse
    {
        return response()->json([
            'data' => $this->menuService->getItemTypes(),
        ]);
    }

    /**
     * Get linkable items for a type.
     */
    public function linkableItems(string $type): JsonResponse
    {
        return response()->json([
            'data' => $this->menuService->getLinkableItems($type),
        ]);
    }

    /**
     * Get available locations.
     */
    public function locations(): JsonResponse
    {
        return response()->json([
            'data' => $this->menuService->getLocations(),
        ]);
    }
}
