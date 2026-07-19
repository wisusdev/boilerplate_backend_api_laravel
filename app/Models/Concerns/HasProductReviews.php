<?php

namespace App\Models\Concerns;

use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Da a un modelo (Tour, TransportVehicle) reseñas de usuario polimórficas,
 * con helpers para el rating promedio y el conteo de reseñas aprobadas.
 */
trait HasProductReviews
{
    public function productReviews(): MorphMany
    {
        return $this->morphMany(ProductReview::class, 'reviewable');
    }

    public function approvedReviews(): MorphMany
    {
        return $this->productReviews()->where('is_approved', true);
    }

    /**
     * Rating promedio (aprobadas). Usa el alias eager-loaded `reviews_avg`
     * cuando está presente (withAvg) para evitar N+1; si no, consulta.
     */
    public function averageRating(): ?float
    {
        if (array_key_exists('reviews_avg', $this->attributes)) {
            $avg = $this->attributes['reviews_avg'];

            return $avg !== null ? round((float) $avg, 1) : null;
        }

        $avg = $this->approvedReviews()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function reviewsCount(): int
    {
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) $this->attributes['reviews_count'];
        }

        return $this->approvedReviews()->count();
    }
}
