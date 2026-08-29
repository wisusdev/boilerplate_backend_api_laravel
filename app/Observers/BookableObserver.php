<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Integridad de las relaciones polimórficas que apuntan a un producto reservable
 * (Tour / TransportVehicle). No pueden declararse como clave foránea, así que se
 * garantizan aquí.
 *
 *  - Si el producto tiene reservas, no se borra: se desactiva (`is_active`). El
 *    historial de reservas y sus facturas deben seguir siendo legibles.
 *  - Si no las tiene, se limpian las reseñas que lo referencian antes de borrarlo.
 */
class BookableObserver
{
    public function deleting(Model $bookable): void
    {
        $bookings = Booking::query()
            ->where('bookable_type', $bookable::class)
            ->where('bookable_id', $bookable->getKey())
            ->count();

        if ($bookings > 0) {
            throw new RuntimeException(
                'No se puede eliminar: tiene '.$bookings.' reserva(s) asociada(s). Desactívalo en su lugar.'
            );
        }

        ProductReview::query()
            ->where('reviewable_type', $bookable::class)
            ->where('reviewable_id', $bookable->getKey())
            ->delete();
    }
}
