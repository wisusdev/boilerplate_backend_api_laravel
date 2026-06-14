<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryItemResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $image = $this->resource->getFirstMedia('image');

        return [
            'url'        => $image?->getUrl(),
            'thumb_url'  => $image?->getUrl('thumb'),
            'caption'    => $this->resource->caption,
            'sort_order' => $this->resource->sort_order,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
