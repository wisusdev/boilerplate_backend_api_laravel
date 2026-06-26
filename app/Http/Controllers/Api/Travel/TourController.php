<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\TourRequest;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TourController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tours = Tour::query()
            ->with(['media', 'category'])
            ->where('is_active', true)
            ->latest()
            ->sparseFieldset()
            ->jsonPaginate();

        return TourResource::collection($tours);
    }

    public function show(Tour $tour): TourResource
    {
        return TourResource::make($tour);
    }

    public function store(TourRequest $request): JsonResponse
    {
        $data = $request->validated()['data']['attributes'];

        $tour = Tour::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => $data['price'],
            'max_capacity' => $data['max_capacity'],
            'location' => $data['location'],
            'category_id' => $data['category_id'] ?? null,
            'currency_code' => $data['currency_code'] ?? config('app.currency', 'USD'),
            'itinerary' => $data['itinerary'] ?? [],
            'highlights' => $data['highlights'] ?? [],
            'map_url' => $data['map_url'] ?? null,
            'map_markers' => $data['map_markers'] ?? [],
            'faqs' => $data['faqs'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return TourResource::make($tour->fresh())
            ->response()
            ->setStatusCode(201);
    }

    public function update(TourRequest $request, Tour $tour): TourResource
    {
        $data = $request->validated()['data']['attributes'];

        $updateData = [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? null,
            'max_capacity' => $data['max_capacity'] ?? null,
            'location' => $data['location'] ?? null,
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : null,
            'currency_code' => $data['currency_code'] ?? null,
            'itinerary' => $data['itinerary'] ?? null,
            'highlights' => $data['highlights'] ?? null,
            'map_url' => $data['map_url'] ?? null,
            'map_markers' => array_key_exists('map_markers', $data) ? ($data['map_markers'] ?? []) : null,
            'faqs' => $data['faqs'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ];

        $tour->update(array_filter($updateData, fn ($v) => $v !== null));

        return TourResource::make($tour->fresh());
    }

    /**
     * Upload or replace the featured image for a tour.
     */
    public function uploadFeaturedImage(Request $request, Tour $tour): TourResource
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $tour->addMediaFromRequest('image')
            ->toMediaCollection('featured_image');

        return TourResource::make($tour->fresh());
    }

    /**
     * Add one or multiple images to the tour gallery.
     */
    public function uploadGalleryImages(Request $request, Tour $tour): TourResource
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:10240'],
        ]);

        foreach ($request->file('images') as $image) {
            $tour->addMedia($image)->toMediaCollection('gallery');
        }

        return TourResource::make($tour->fresh());
    }

    /**
     * Remove a specific image from the tour gallery.
     */
    public function destroyGalleryImage(Tour $tour, Media $media): JsonResponse
    {
        abort_unless($media->model_id == $tour->id && $media->model_type === Tour::class, 404);

        $media->delete();

        return response()->json(null, 204);
    }
}