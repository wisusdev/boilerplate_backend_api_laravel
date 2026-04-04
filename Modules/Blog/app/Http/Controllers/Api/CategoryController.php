<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:categories,id',
            'featured_image' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['posts' => function ($query) {
            $query->where('status', 'published')
                  ->orderBy('published_at', 'desc')
                  ->limit(10);
        }]);

        return response()->json(['data' => $category]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:categories,id',
            'featured_image' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(null, 204);
    }

    /**
     * Get category tree.
     */
    public function tree(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return response()->json(['data' => $categories]);
    }
}
