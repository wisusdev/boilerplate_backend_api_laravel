<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $featuredImage = $this->resource->getFirstMedia('featured_image');
        $galleryMedia  = $this->resource->getMedia('gallery');

        return [
            'title'              => $this->resource->title,
            'description'        => $this->resource->description,
            'price'              => $this->resource->price,
            'max_capacity'       => $this->resource->max_capacity,
            'location'           => $this->resource->location,
            'category'           => $this->resource->category,
            'currency_code'      => $this->resource->currency_code,
            'itinerary'          => $this->resource->itinerary,
            'highlights'         => $this->resource->highlights,
            'map_url'            => $this->resource->map_url,
            'map_markers'        => $this->resource->map_markers ?? [],
            'faqs'               => $this->resource->faqs,
            'is_active'          => $this->resource->is_active,
            'featured_image_url' => $featuredImage?->getUrl(),
            'featured_image_thumb_url' => $featuredImage?->getUrl('thumb'),
            'gallery'            => $galleryMedia->map(fn ($m) => [
                'id'        => $m->id,
                'url'       => $m->getUrl(),
                'thumb_url' => $m->getUrl('thumb'),
                'name'      => $m->name,
            ])->values()->toArray(),
            'created_at'         => $this->resource->created_at,
            'updated_at'         => $this->resource->updated_at,
        ];
    }
}