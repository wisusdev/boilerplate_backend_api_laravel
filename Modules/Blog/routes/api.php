<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\Api\PostController;
use Modules\Blog\Http\Controllers\Api\CategoryController;
use Modules\Blog\Http\Controllers\Api\TagController;
use Modules\Blog\Http\Controllers\Api\CommentController;

/*
|--------------------------------------------------------------------------
| Blog API Routes (Authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:api'])->prefix('v1/blog')->name('api.blog.')->group(function () {
    // Posts
    Route::get('posts/popular', [PostController::class, 'popular'])->name('posts.popular');
    Route::get('posts/featured', [PostController::class, 'featured'])->name('posts.featured');
    Route::post('posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('posts.duplicate');
    Route::post('posts/{id}/restore', [PostController::class, 'restore'])->name('posts.restore');
    Route::delete('posts/{id}/force-delete', [PostController::class, 'forceDelete'])->name('posts.force-delete');
    Route::get('posts/{post}/related', [PostController::class, 'related'])->name('posts.related');
    Route::apiResource('posts', PostController::class);

    // Categories
    Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
    Route::apiResource('categories', CategoryController::class);

    // Tags
    Route::apiResource('tags', TagController::class);

    // Comments
    Route::post('comments/{comment}/approve', [CommentController::class, 'approve'])->name('comments.approve');
    Route::post('comments/{comment}/reject', [CommentController::class, 'reject'])->name('comments.reject');
    Route::apiResource('comments', CommentController::class);
});

/*
|--------------------------------------------------------------------------
| Public Blog API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api'])->prefix('v1/blog')->name('api.public.blog.')->group(function () {
    // Public posts
    Route::get('posts/published', function () {
        return \Modules\Blog\Models\Post::published()
            ->with(['author:id,first_name,last_name,avatar', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->orderBy('published_at', 'desc')
            ->paginate(request()->get('per_page', 15));
    })->name('posts.published');

    Route::get('posts/{slug}', function ($slug) {
        $post = \Modules\Blog\Models\Post::published()
            ->with(['author', 'categories', 'tags', 'comments' => function ($q) {
                $q->approved()->whereNull('parent_id')->with('replies');
            }])
            ->where('slug', $slug)
            ->firstOrFail();

        $post->increment('views_count');

        return new \Modules\Blog\Http\Resources\PostResource($post);
    })->name('posts.show');

    // Public categories
    Route::get('categories', function () {
        return \Modules\Blog\Models\Category::withCount(['posts' => function ($q) {
            $q->published();
        }])->orderBy('name')->get();
    })->name('categories.index');

    // Public tags
    Route::get('tags', function () {
        return \Modules\Blog\Models\Tag::withCount(['posts' => function ($q) {
            $q->published();
        }])->orderBy('name')->get();
    })->name('tags.index');

    // Submit comment
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('posts.comments.store');
});
