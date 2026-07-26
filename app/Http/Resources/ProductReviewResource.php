<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $user = $this->resource->user;
        $displayName = $user
            ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->username ?? 'Usuario')
            : 'Usuario';

        // Devuelve el tipo corto (tour|transport) en lugar del FQCN.
        $shortType = array_search($this->resource->reviewable_type, Booking::BOOKABLE_MAP, true) ?: $this->resource->reviewable_type;

        // Título del producto solo si la relación ya está cargada (evita N+1 en el índice público).
        $reviewableTitle = $this->resource->relationLoaded('reviewable')
            ? $this->resource->reviewable?->title
            : null;

        return [
            'rating' => $this->resource->rating,
            'comment' => $this->resource->comment,
            'is_approved' => $this->resource->is_approved,
            'admin_reply' => $this->resource->admin_reply,
            'replied_at' => $this->resource->replied_at,
            'user_name' => $displayName,
            'reviewable_type' => $shortType,
            'reviewable_id' => $this->resource->reviewable_id,
            'reviewable_title' => $reviewableTitle,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
