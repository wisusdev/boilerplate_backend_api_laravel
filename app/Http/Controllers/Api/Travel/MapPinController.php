<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapPinRequest;
use App\Http\Resources\MapPinResource;
use App\Models\MapPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Mapa público de pines personalizados (lugares con información de los tours,
 * videos de Instagram o enlaces) y su gestión desde el panel.
 */
class MapPinController extends Controller
{
    /** GET /map-pins — público: solo los activos, en su orden. */
    public function index(): AnonymousResourceCollection
    {
        return MapPinResource::collection(
            MapPin::with(['tour:id,title', 'media'])
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
        );
    }

    /** GET /map-pins/manage — panel: todos, también los ocultos. */
    public function manage(): AnonymousResourceCollection
    {
        return MapPinResource::collection(
            MapPin::with(['tour:id,title', 'media'])->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(MapPinRequest $request): JsonResponse
    {
        $attrs = $request->pinAttributes();
        $attrs['sort_order'] ??= (int) (MapPin::max('sort_order') ?? 0) + 1;

        return MapPinResource::make(MapPin::create($attrs)->load('tour:id,title'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(MapPinRequest $request, MapPin $mapPin): MapPinResource
    {
        $mapPin->update($request->pinAttributes());

        return MapPinResource::make($mapPin->fresh(['tour:id,title', 'media']));
    }

    public function destroy(MapPin $mapPin): JsonResponse
    {
        $mapPin->delete(); // MediaLibrary se lleva también la imagen

        return response()->json(null, 204);
    }

    /** POST /map-pins/{mapPin}/image — imagen opcional del pin (multipart). */
    public function uploadImage(Request $request, MapPin $mapPin): MapPinResource
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']]);
        $mapPin->addMediaFromRequest('image')->toMediaCollection('image');

        return MapPinResource::make($mapPin->fresh(['tour:id,title', 'media']));
    }

    /** DELETE /map-pins/{mapPin}/image */
    public function deleteImage(MapPin $mapPin): MapPinResource
    {
        $mapPin->clearMediaCollection('image');

        return MapPinResource::make($mapPin->fresh(['tour:id,title', 'media']));
    }
}
