<?php

use Illuminate\Support\Facades\Route;
use Modules\Pages\Http\Controllers\PageController;
use Modules\Pages\Http\Controllers\Api\PageController as ApiPageController;

/*
|--------------------------------------------------------------------------
| Admin Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('pages/trash', [PageController::class, 'trash'])->name('pages.trash');
    Route::post('pages/{id}/restore', [PageController::class, 'restore'])->name('pages.restore');
    Route::delete('pages/{id}/force-delete', [PageController::class, 'forceDelete'])->name('pages.force-delete');
    Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
    Route::get('pages/{page}/builder', [PageController::class, 'builder'])->name('pages.builder');
    Route::post('pages/{page}/builder', [PageController::class, 'saveBuilder'])->name('pages.builder.save');
    Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->name('pages.revisions');
    Route::post('pages/{page}/revisions/{revisionId}/restore', [PageController::class, 'restoreRevision'])->name('pages.revisions.restore');
    Route::get('pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview');

    Route::resource('pages', PageController::class)->except(['show']);
});

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
*/

// NOTE: The page catch-all route is registered in routes/web.php
// to ensure it's loaded AFTER all other routes (blog, admin, etc.)
