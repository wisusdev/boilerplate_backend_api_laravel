<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\PostController;
use Modules\Blog\Http\Controllers\CategoryController;
use Modules\Blog\Http\Controllers\CommentController;

/*
|--------------------------------------------------------------------------
| Admin Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('admin/blog')->name('admin.blog.')->group(function () {
    // Posts
    Route::get('posts/trash', [PostController::class, 'trash'])->name('posts.trash');
    Route::post('posts/{id}/restore', [PostController::class, 'restore'])->name('posts.restore');
    Route::delete('posts/{id}/force-delete', [PostController::class, 'forceDelete'])->name('posts.force-delete');
    Route::post('posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('posts.duplicate');
    Route::get('posts/{post}/preview', [PostController::class, 'preview'])->name('posts.preview');
    Route::resource('posts', PostController::class)->except(['show']);

    // Categories
    Route::resource('categories', CategoryController::class)->except(['show']);

    // Comments
    Route::post('comments/{comment}/approve', [CommentController::class, 'approve'])->name('comments.approve');
    Route::post('comments/{comment}/reject', [CommentController::class, 'reject'])->name('comments.reject');
    Route::resource('comments', CommentController::class)->only(['index', 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])->prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [PostController::class, 'archive'])->name('index');
    Route::get('/category/{slug}', [PostController::class, 'byCategory'])->name('category');
    Route::get('/tag/{slug}', [PostController::class, 'byTag'])->name('tag');
    Route::get('/{slug}', [PostController::class, 'show'])->name('show');
});
