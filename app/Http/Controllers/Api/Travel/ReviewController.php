<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = Review::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $reviews->map(fn (Review $review) => [
                'id' => (string) $review->id,
                'type' => 'reviews',
                'attributes' => [
                    'name' => $review->name,
                    'location' => $review->location,
                    'tour' => $review->tour,
                    'quote' => $review->quote,
                    'rating' => $review->rating,
                    'avatar_url' => $review->avatar_url,
                    'is_active' => $review->is_active,
                    'sort_order' => $review->sort_order,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                ],
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', $request->all());

        $validated = validator($attrs, [
            'name' => 'required|string|max:120',
            'location' => 'sometimes|nullable|string|max:120',
            'tour' => 'sometimes|nullable|string|max:160',
            'quote' => 'required|string|max:2000',
            'rating' => 'sometimes|integer|min:1|max:5',
            'avatar_url' => 'sometimes|nullable|url|max:500',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ])->validate();

        $review = Review::create($validated);

        return response()->json([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'reviews',
                'attributes' => $review->only([
                    'name',
                    'location',
                    'tour',
                    'quote',
                    'rating',
                    'avatar_url',
                    'is_active',
                    'sort_order',
                    'created_at',
                    'updated_at',
                ]),
            ],
        ], 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        $attrs = $request->input('data.attributes', $request->all());

        $validated = validator($attrs, [
            'name' => 'sometimes|string|max:120',
            'location' => 'sometimes|nullable|string|max:120',
            'tour' => 'sometimes|nullable|string|max:160',
            'quote' => 'sometimes|string|max:2000',
            'rating' => 'sometimes|integer|min:1|max:5',
            'avatar_url' => 'sometimes|nullable|url|max:500',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ])->validate();

        $review->update($validated);

        return response()->json([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'reviews',
                'attributes' => $review->only([
                    'name',
                    'location',
                    'tour',
                    'quote',
                    'rating',
                    'avatar_url',
                    'is_active',
                    'sort_order',
                    'created_at',
                    'updated_at',
                ]),
            ],
        ]);
    }

    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json(null, 204);
    }
}
