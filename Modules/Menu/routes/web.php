<?php

use Illuminate\Support\Facades\Route;
use Modules\Menu\Http\Controllers\MenuController;

/*
|--------------------------------------------------------------------------
| Menu Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('menus', MenuController::class);
    
    // Menu item management (AJAX)
    Route::post('menus/{menu}/items', [MenuController::class, 'addItem'])->name('menus.items.store');
    Route::put('menu-items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
    Route::delete('menu-items/{item}', [MenuController::class, 'deleteItem'])->name('menus.items.destroy');
    Route::post('menus/{menu}/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
    
    // Get linkable items
    Route::get('menus/linkable/{type}', [MenuController::class, 'linkableItems'])->name('menus.linkable');
});
