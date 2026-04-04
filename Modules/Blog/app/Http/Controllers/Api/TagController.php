<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Models\Tag;

class TagController extends Controller
{
    /**
     * Display a listing of tags.
     */
    public function index(): JsonResponse
    {
        $tags = Tag::withCount('posts')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $tags]);
    }

    /**
     * Store a newly created tag.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags',
            'description' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        $tag = Tag::create($validated);

        return response()->json([
            'message' => 'Tag created successfully.',
            'data' => $tag,
        ], 201);
    }

    /**
     * Display the specified tag.
     */
    public function show(Tag $tag): JsonResponse
    {
        $tag->load(['posts' => function ($query) {
            $query->where('status', 'published')
                  ->orderBy('published_at', 'desc')
                  ->limit(10);
        }]);

        return response()->json(['data' => $tag]);
    }

    /**
     * Update the specified tag.
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags,slug,' . $tag->id,
            'description' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        $tag->update($validated);

        return response()->json([
            'message' => 'Tag updated successfully.',
            'data' => $tag->fresh(),
        ]);
    }

    /**
     * Remove the specified tag.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(null, 204);
    }

    /**
     * Get popular tags.
     */
    public function popular(): JsonResponse
    {
        $tags = Tag::withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->limit(20)
            ->get();

        return response()->json(['data' => $tags]);
    }
}
