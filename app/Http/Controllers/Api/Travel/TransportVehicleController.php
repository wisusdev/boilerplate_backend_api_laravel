<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransportVehicleRequest;
use App\Http\Resources\TransportVehicleResource;
use App\Models\Booking;
use App\Models\TransportVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransportVehicleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $vehicles = TransportVehicle::query()
            ->with('media')
            ->withCount(['approvedReviews as reviews_count'])
            ->withAvg(['approvedReviews as reviews_avg'], 'rating')
            ->where('is_active', true)
            ->allowedFilters(['vehicleType', 'capacityMin', 'rateMin', 'rateMax', 'location', 'search'])
            ->allowedSorts(['daily_rate', 'hourly_rate', 'capacity', 'created_at'])
            ->when(! request()->filled('sort'), fn ($q) => $q->latest())
            ->sparseFieldset()
            ->jsonPaginate();

        return TransportVehicleResource::collection($vehicles);
    }

    public function show(TransportVehicle $transportVehicle): TransportVehicleResource
    {
        return TransportVehicleResource::make($transportVehicle);
    }

    /**
     * Tipos de vehículo distintos entre los vehículos activos.
     * GET /transport-vehicles/types
     */
    public function types(): JsonResponse
    {
        $types = TransportVehicle::query()
            ->where('is_active', true)
            ->whereNotNull('vehicle_type')
            ->where('vehicle_type', '!=', '')
            ->distinct()
            ->orderBy('vehicle_type')
            ->pluck('vehicle_type')
            ->values();

        return response()->json([
            'data' => [
                'type'       => 'transport_vehicle_types',
                'attributes' => ['types' => $types],
            ],
        ]);
    }

    public function store(TransportVehicleRequest $request): TransportVehicleResource
    {
        $data = $request->validated()['data']['attributes'];

        $vehicle = TransportVehicle::create([
            'title' => $data['title'],
            'vehicle_type' => $data['vehicle_type'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'],
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'daily_rate' => $data['daily_rate'] ?? null,
            'capacity' => $data['capacity'],
            // Moneda GLOBAL del sitio (ya no se configura por vehículo).
            'currency_code' => \App\Support\SiteSettings::currency(),
            'features' => $data['features'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ]);

        return TransportVehicleResource::make($vehicle->fresh());
    }

    public function update(TransportVehicleRequest $request, TransportVehicle $transportVehicle): TransportVehicleResource
    {
        $data = $request->validated()['data']['attributes'];

        $transportVehicle->update(array_filter([
            'title' => $data['title'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'daily_rate' => $data['daily_rate'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'features' => $data['features'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'meta_title' => array_key_exists('meta_title', $data) ? $data['meta_title'] : null,
            'meta_description' => array_key_exists('meta_description', $data) ? $data['meta_description'] : null,
        ], fn ($v) => $v !== null));

        return TransportVehicleResource::make($transportVehicle->fresh());
    }

    /**
     * Check whether a vehicle is available for a given pickup/dropoff window.
     * Public endpoint — returns no booking details, only a boolean.
     *
     * GET /transport-vehicles/{id}/availability?pickup_at=Y-m-d+H:i:s&dropoff_at=Y-m-d+H:i:s
     */
    public function checkAvailability(Request $request, TransportVehicle $transportVehicle): JsonResponse
    {
        $request->validate([
            'pickup_at'  => ['required', 'date'],
            'dropoff_at' => ['required', 'date', 'after:pickup_at'],
        ]);

        $overlapping = Booking::query()
            ->where('bookable_type', TransportVehicle::class)
            ->where('bookable_id', $transportVehicle->id)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->where('starts_at', '<', $request->input('dropoff_at'))
            ->where('ends_at', '>', $request->input('pickup_at'))
            ->count();

        return response()->json([
            'data' => [
                'type'       => 'availability',
                'attributes' => [
                    'available'  => $overlapping === 0,
                    'vehicle_id' => $transportVehicle->id,
                    'pickup_at'  => $request->input('pickup_at'),
                    'dropoff_at' => $request->input('dropoff_at'),
                ],
            ],
        ]);
    }

    /**
     * Upload or replace the featured image for a vehicle.
     */
    public function uploadFeaturedImage(Request $request, TransportVehicle $transportVehicle): TransportVehicleResource
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $transportVehicle->addMediaFromRequest('image')
            ->toMediaCollection('featured_image');

        return TransportVehicleResource::make($transportVehicle->fresh());
    }

    /**
     * Add one or multiple images to the vehicle gallery.
     */
    public function uploadGalleryImages(Request $request, TransportVehicle $transportVehicle): TransportVehicleResource
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:10240'],
        ]);

        foreach ($request->file('images') as $image) {
            $transportVehicle->addMedia($image)->toMediaCollection('gallery');
        }

        return TransportVehicleResource::make($transportVehicle->fresh());
    }

    /**
     * Remove a specific image from the vehicle gallery.
     */
    public function destroyGalleryImage(TransportVehicle $transportVehicle, Media $media): JsonResponse
    {
        abort_unless($media->model_id == $transportVehicle->id && $media->model_type === TransportVehicle::class, 404);

        $media->delete();

        return response()->json(null, 204);
    }
}