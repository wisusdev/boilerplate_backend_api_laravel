<?php

namespace App\Support;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Comprobante de reserva en PDF.
 *
 * Un único generador para los tres momentos en que el cliente lo recibe: al
 * apartar la reserva (pago asistido), al confirmarla y al cobrarse el pago. El
 * documento refleja el estado en el instante en que se emite, así que el mismo
 * comprobante sirve para "pendiente" y para "pagado".
 */
class BookingReceipt
{
    /** Contenido binario del PDF. */
    public static function pdf(Booking $booking): string
    {
        $booking->loadMissing(['user', 'bookable', 'transportDetail', 'upgradeVehicle', 'coupon', 'latestPayment']);

        return Pdf::loadView('pdf.booking-receipt', [
            'booking' => $booking,
            'attrs' => (new BookingResource($booking))->toJsonApi(),
        ])->output();
    }

    public static function filename(Booking $booking): string
    {
        return 'comprobante-reserva-'.$booking->id.'.pdf';
    }

    /** Opciones de adjunto para MailMessage::attachData(). */
    public static function attachmentOptions(): array
    {
        return ['mime' => 'application/pdf'];
    }
}
