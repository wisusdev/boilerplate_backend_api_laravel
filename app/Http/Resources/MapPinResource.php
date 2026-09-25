<?php

namespace App\Http\Resources;

use App\Models\MapPin;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MapPin $resource */
class MapPinResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $p = $this->resource;
        $image = $p->getFirstMedia('image');

        return [
            'title' => $p->title,
            'description' => $p->description,
            'latitude' => $p->latitude,
            'longitude' => $p->longitude,
            'icon' => $p->icon,
            'color' => $p->color,
            'tour' => $p->tour ? ['id' => $p->tour->id, 'title' => $p->tour->title] : null,
            'tour_id' => $p->tour_id,
            'instagram_url' => $p->instagram_url,
            'instagram_embed_url' => $p->instagramEmbedUrl(),
            'instagram_permalink' => $p->instagramPermalink(),
            'link_url' => $p->link_url,
            'link_label' => $p->link_label,
            'image_url' => $image?->getUrl(),
            'image_thumb_url' => $image?->getUrl('thumb'),
            'is_active' => $p->is_active,
            'sort_order' => $p->sort_order,
            'updated_at' => $p->updated_at,
        ];
    }
}
