<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class TransportVehicleResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $featuredImage = $this->resource->getFirstMedia('featured_image');
        $galleryMedia  = $this->resource->getMedia('gallery');

        return [
            'title'              => $this->resource->title,
            'vehicle_type'       => $this->resource->vehicle_type,
            'description'        => $this->resource->description,
            'location'           => $this->resource->location,
            'hourly_rate'        => $this->resource->hourly_rate,
            'daily_rate'         => $this->resource->daily_rate,
            'capacity'           => $this->resource->capacity,
            // Moneda GLOBAL del sitio (ya no se configura por vehículo).
            'currency_code'      => \App\Support\SiteSettings::currency(),
            'features'           => $this->resource->features,
            'is_active'          => $this->resource->is_active,
            'meta_title'         => $this->resource->meta_title,
            'meta_description'   => $this->resource->meta_description,
            'average_rating'     => $this->resource->averageRating(),
            'reviews_count'      => $this->resource->reviewsCount(),
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