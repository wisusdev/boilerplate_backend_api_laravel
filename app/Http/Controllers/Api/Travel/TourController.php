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
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg(['approvedReviews as reviews_avg'], 'rating')
            ->where('is_active', true)
            ->allowedFilters(['categoryId', 'priceMin', 'priceMax', 'location', 'search', 'isFeatured'])
            ->allowedSorts(['price', 'title', 'max_capacity', 'created_at'])
            ->when(! request()->filled('sort'), fn ($q) => $q->latest())
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
            'sale_price' => $data['sale_price'] ?? null,
            'child_price' => $data['child_price'] ?? null,
            'pricing_tiers' => $data['pricing_tiers'] ?? [],
            'vehicle_options' => $data['vehicle_options'] ?? [],
            'booking_sections' => $data['booking_sections'] ?? null,
            'duration_days' => $data['duration_days'] ?? null,
            'duration_nights' => $data['duration_nights'] ?? null,
            'min_advance_days' => $data['min_advance_days'] ?? null,
            'cancellation_hours' => $data['cancellation_hours'] ?? null,
            'max_capacity' => $data['max_capacity'],
            'location' => $data['location'],
            'category_id' => $data['category_id'] ?? null,
            'currency_code' => $data['currency_code'] ?? config('app.currency', 'USD'),
            'itinerary' => $data['itinerary'] ?? [],
            'highlights' => $data['highlights'] ?? [],
            'includes' => $data['includes'] ?? [],
            'excludes' => $data['excludes'] ?? [],
            'service_fees' => $data['service_fees'] ?? [],
            'map_url' => $data['map_url'] ?? null,
            'map_markers' => $data['map_markers'] ?? [],
            'faqs' => $data['faqs'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
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
            'sale_price' => array_key_exists('sale_price', $data) ? $data['sale_price'] : null,
            'child_price' => array_key_exists('child_price', $data) ? $data['child_price'] : null,
            'pricing_tiers' => array_key_exists('pricing_tiers', $data) ? ($data['pricing_tiers'] ?? []) : null,
            'vehicle_options' => array_key_exists('vehicle_options', $data) ? ($data['vehicle_options'] ?? []) : null,
            'booking_sections' => array_key_exists('booking_sections', $data) ? $data['booking_sections'] : null,
            'duration_days' => array_key_exists('duration_days', $data) ? $data['duration_days'] : null,
            'duration_nights' => array_key_exists('duration_nights', $data) ? $data['duration_nights'] : null,
            'min_advance_days' => array_key_exists('min_advance_days', $data) ? $data['min_advance_days'] : null,
            'cancellation_hours' => array_key_exists('cancellation_hours', $data) ? $data['cancellation_hours'] : null,
            'max_capacity' => $data['max_capacity'] ?? null,
            'location' => $data['location'] ?? null,
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : null,
            'currency_code' => $data['currency_code'] ?? null,
            'itinerary' => $data['itinerary'] ?? null,
            'highlights' => $data['highlights'] ?? null,
            'includes' => array_key_exists('includes', $data) ? ($data['includes'] ?? []) : null,
            'excludes' => array_key_exists('excludes', $data) ? ($data['excludes'] ?? []) : null,
            'service_fees' => array_key_exists('service_fees', $data) ? ($data['service_fees'] ?? []) : null,
            'map_url' => $data['map_url'] ?? null,
            'map_markers' => array_key_exists('map_markers', $data) ? ($data['map_markers'] ?? []) : null,
            'faqs' => $data['faqs'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'is_featured' => array_key_exists('is_featured', $data) ? $data['is_featured'] : null,
            'meta_title' => array_key_exists('meta_title', $data) ? $data['meta_title'] : null,
            'meta_description' => array_key_exists('meta_description', $data) ? $data['meta_description'] : null,
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