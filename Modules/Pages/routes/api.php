<?php

use Illuminate\Support\Facades\Route;
use Modules\Pages\Http\Controllers\Api\PageController;

/*
|--------------------------------------------------------------------------
| Pages API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:api'])->prefix('v1')->name('api.')->group(function () {
    Route::get('pages/tree', [PageController::class, 'tree'])->name('pages.tree');
    Route::get('pages/templates', [PageController::class, 'templates'])->name('pages.templates');
    Route::post('pages/reorder', [PageController::class, 'reorder'])->name('pages.reorder');
    Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
    Route::post('pages/{id}/restore', [PageController::class, 'restore'])->name('pages.restore');
    Route::delete('pages/{id}/force-delete', [PageController::class, 'forceDelete'])->name('pages.force-delete');
    Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->name('pages.revisions');
    Route::post('pages/{page}/revisions/{revisionId}/restore', [PageController::class, 'restoreRevision'])->name('pages.revisions.restore');

    Route::apiResource('pages', PageController::class);
});

/*
|--------------------------------------------------------------------------
| Public API Routes (no auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['api'])->prefix('v1')->name('api.public.')->group(function () {
    Route::get('pages/published', function () {
        return \Modules\Pages\Models\Page::published()
            ->orderBy('created_at', 'desc')
            ->paginate(request()->get('per_page', 15));
    })->name('pages.published');

    Route::get('pages/{slug}', function ($slug) {
        $page = \Modules\Pages\Models\Page::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return new \Modules\Pages\Http\Resources\PageResource($page);
    })->name('pages.show');
});
