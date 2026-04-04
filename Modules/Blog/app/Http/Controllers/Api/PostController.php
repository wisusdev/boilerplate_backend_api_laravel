<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Http\Requests\StorePostRequest;
use Modules\Blog\Http\Requests\UpdatePostRequest;
use Modules\Blog\Http\Resources\PostResource;
use Modules\Blog\Http\Resources\PostCollection;
use Modules\Blog\Models\Post;
use Modules\Blog\Services\PostService;

class PostController extends Controller
{
    public function __construct(
        protected PostService $postService
    ) {}

    /**
     * Display a listing of posts.
     */
    public function index(Request $request): PostCollection
    {
        $posts = $this->postService->getAll($request->all());

        return new PostCollection($posts);
    }

    /**
     * Store a newly created post.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->create($request->validated());

        return (new PostResource($post))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified post.
     */
    public function show(string $id): PostResource
    {
        $post = $this->postService->find($id);

        if (!$post) {
            abort(404, 'Post not found');
        }

        return new PostResource($post);
    }

    /**
     * Update the specified post.
     */
    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $post = $this->postService->update($post, $request->validated());

        return new PostResource($post);
    }

    /**
     * Remove the specified post.
     */
    public function destroy(Post $post): JsonResponse
    {
        $this->postService->delete($post);

        return response()->json(null, 204);
    }

    /**
     * Restore a soft-deleted post.
     */
    public function restore(string $id): JsonResponse
    {
        $post = $this->postService->restore($id);

        if (!$post) {
            abort(404, 'Post not found or not deleted');
        }

        return (new PostResource($post))->response();
    }

    /**
     * Force delete a post.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $deleted = $this->postService->forceDelete($id);

        if (!$deleted) {
            abort(404, 'Post not found');
        }

        return response()->json(null, 204);
    }

    /**
     * Duplicate a post.
     */
    public function duplicate(Post $post): JsonResponse
    {
        $newPost = $this->postService->duplicate($post);

        return (new PostResource($newPost))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get popular posts.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 5);
        $posts = $this->postService->getPopular($limit);

        return response()->json(['data' => PostResource::collection($posts)]);
    }

    /**
     * Get featured posts.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 3);
        $posts = $this->postService->getFeatured($limit);

        return response()->json(['data' => PostResource::collection($posts)]);
    }

    /**
     * Get related posts.
     */
    public function related(Post $post, Request $request): JsonResponse
    {
        $limit = $request->get('limit', 4);
        $related = $this->postService->getRelated($post, $limit);

        return response()->json(['data' => PostResource::collection($related)]);
    }
}
