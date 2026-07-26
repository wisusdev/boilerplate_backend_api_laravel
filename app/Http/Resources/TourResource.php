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
            'slug'               => $this->resource->slug,
            'description'        => $this->resource->description,
            'price'              => $this->resource->price,
            'pricing_tiers'      => $this->resource->pricing_tiers ?? [],
            'vehicle_options'    => $this->resource->vehicleOptionsList(),
            'booking_sections'   => [
                'vehicle' => $this->resource->sectionVisible('vehicle'),
                'pickup'  => $this->resource->sectionVisible('pickup'),
                'coupon'  => $this->resource->sectionVisible('coupon'),
                'fare'    => $this->resource->sectionVisible('fare'),
            ],
            'duration_days'      => $this->resource->duration_days,
            'duration_nights'    => $this->resource->duration_nights,
            'max_capacity'       => $this->resource->max_capacity,
            'location'           => $this->resource->location,
            'category_id'        => $this->resource->category_id,
            'category'           => $this->resource->category?->name,
            'category_color'     => $this->resource->category?->color,
            // Moneda GLOBAL del sitio (política y moneda ya no son por tour).
            'currency_code'      => \App\Support\SiteSettings::currency(),
            'itinerary'          => $this->resource->itinerary,
            'highlights'         => $this->resource->highlights,
            'includes'           => $this->resource->includes,
            'excludes'           => $this->resource->excludes,
            'service_fees'       => $this->resource->service_fees ?? [],
            'map_url'            => $this->resource->map_url,
            'map_markers'        => $this->resource->map_markers ?? [],
            'faqs'               => $this->resource->faqs,
            'is_active'          => $this->resource->is_active,
            'is_featured'        => $this->resource->is_featured,
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