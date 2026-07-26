<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Models\TourCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = TourCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'id' => (string) $c->id,
                'type' => 'tour_categories',
                'attributes' => [
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'color' => $c->color,
                    'is_active' => $c->is_active,
                    'sort_order' => $c->sort_order,
                    'created_at' => $c->created_at,
                    'updated_at' => $c->updated_at,
                ],
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', $request->all());

        $validated = validator($attrs, [
            'name' => 'required|string|max:100|unique:tour_categories,name',
            'color' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ])->validate();

        $cat = TourCategory::create($validated);

        return response()->json([
            'data' => [
                'id' => (string) $cat->id,
                'type' => 'tour_categories',
                'attributes' => $cat->only(['name', 'slug', 'color', 'is_active', 'sort_order', 'created_at', 'updated_at']),
            ],
        ], 201);
    }

    public function update(Request $request, TourCategory $tourCategory): JsonResponse
    {
        $attrs = $request->input('data.attributes', $request->all());

        $validated = validator($attrs, [
            'name' => 'sometimes|string|max:100|unique:tour_categories,name,'.$tourCategory->id,
            'color' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ])->validate();

        $tourCategory->update($validated);

        return response()->json([
            'data' => [
                'id' => (string) $tourCategory->id,
                'type' => 'tour_categories',
                'attributes' => $tourCategory->only(['name', 'slug', 'color', 'is_active', 'sort_order', 'created_at', 'updated_at']),
            ],
        ]);
    }

    public function destroy(TourCategory $tourCategory): JsonResponse
    {
        $tourCategory->delete();

        return response()->json(null, 204);
    }
}
