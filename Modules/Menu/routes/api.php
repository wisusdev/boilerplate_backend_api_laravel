<?php

use Illuminate\Support\Facades\Route;
use Modules\Menu\Http\Controllers\Api\MenuController;

/*
|--------------------------------------------------------------------------
| Menu API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public routes - get menus by location
    Route::get('menus/location/{location}', [MenuController::class, 'byLocation']);
    Route::get('menus/locations', [MenuController::class, 'locations']);
    
    // Protected routes
    Route::middleware(['auth:api'])->group(function () {
        Route::apiResource('menus', MenuController::class);
        
        // Menu item types and linkable items
        Route::get('menus/item-types', [MenuController::class, 'itemTypes']);
        Route::get('menus/linkable/{type}', [MenuController::class, 'linkableItems']);
        
        // Menu item management
        Route::post('menus/{menu}/items', [MenuController::class, 'addItem']);
        Route::put('menu-items/{item}', [MenuController::class, 'updateItem']);
        Route::delete('menu-items/{item}', [MenuController::class, 'deleteItem']);
        Route::post('menus/{menu}/reorder', [MenuController::class, 'reorder']);
    });
});
