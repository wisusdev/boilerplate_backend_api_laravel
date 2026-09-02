<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\GalleryReorderRequest;
use App\Http\Resources\GalleryItemResource;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = GalleryItem::query()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->jsonPaginate();

        return GalleryItemResource::collection($items);
    }

    public function store(Request $request): AnonymousResourceCollection|GalleryItemResource
    {
        if ($request->hasFile('images')) {
            $request->validate([
                'images' => ['required', 'array', 'min:1', 'max:20'],
                'images.*' => ['required', 'image', 'max:10240'],
                'caption' => ['nullable', 'string', 'max:255'],
            ]);

            $nextOrder = (int) (GalleryItem::max('sort_order') ?? 0) + 1;

            // En una transacción: si falla la subida de una imagen, no queda una
            // fila de galería sin fichero (una tarjeta rota en el frontend).
            $created = DB::transaction(function () use ($request, $nextOrder) {
                $created = collect();

                foreach ($request->file('images') as $file) {
                    $item = GalleryItem::create([
                        'caption' => $request->input('caption') ?? null,
                        'sort_order' => $nextOrder++,
                    ]);

                    $item->addMedia($file)->toMediaCollection('image');
                    $created->push($item->fresh());
                }

                return $created;
            });

            return GalleryItemResource::collection($created);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = DB::transaction(function () use ($validated) {
            $item = GalleryItem::create([
                'caption' => $validated['caption'] ?? null,
                'sort_order' => $validated['sort_order'] ?? (int) (GalleryItem::max('sort_order') ?? 0) + 1,
            ]);

            $item->addMediaFromRequest('image')->toMediaCollection('image');

            return $item;
        });

        return GalleryItemResource::make($item->fresh());
    }

    public function destroy(GalleryItem $galleryItem): JsonResponse
    {
        $galleryItem->delete();

        return response()->json(null, 204);
    }

    public function reorder(GalleryReorderRequest $request): JsonResponse
    {
        $ids = $request->validated()['data']['attributes']['ids'];

        foreach ($ids as $order => $id) {
            GalleryItem::where('id', $id)->update(['sort_order' => $order]);
        }

        return response()->json(['message' => 'Reordered successfully']);
    }
}
