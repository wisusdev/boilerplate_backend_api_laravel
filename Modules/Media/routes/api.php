<?php

use Illuminate\Support\Facades\Route;
use Modules\Media\Http\Controllers\Api\MediaController;

/*
|--------------------------------------------------------------------------
| Media API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:api'])->prefix('v1')->name('api.')->group(function () {
    Route::get('media/folders', [MediaController::class, 'folders'])->name('media.folders');
    Route::post('media/folders', [MediaController::class, 'createFolder'])->name('media.folders.store');
    Route::delete('media/folders/{folder}', [MediaController::class, 'deleteFolder'])->name('media.folders.destroy');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDelete'])->name('media.bulk-delete');
    Route::post('media/move', [MediaController::class, 'move'])->name('media.move');

    Route::apiResource('media', MediaController::class);
});
