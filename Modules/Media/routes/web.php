<?php

use Illuminate\Support\Facades\Route;
use Modules\Media\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Admin Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
    Route::put('media/{medium}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDelete'])->name('media.bulk-delete');
    Route::post('media/folders', [MediaController::class, 'createFolder'])->name('media.folders.store');
    Route::delete('media/folders/{folder}', [MediaController::class, 'deleteFolder'])->name('media.folders.destroy');
    Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
});
