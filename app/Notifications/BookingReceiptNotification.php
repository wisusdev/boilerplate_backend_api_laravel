<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\BookingReceipt;
use App\Support\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Comprobante de la reserva en PDF.
 *
 * El cliente se queda con un documento con todo lo contratado y su referencia,
 * en vez de depender de una pantalla que se cierra. Se envía al pedir el pago
 * asistido: la reserva está apartada pero aún pendiente de cobro.
 */
class BookingReceiptNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly ?string $whatsappUrl = null,
        private readonly bool $paid = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['user', 'bookable', 'transportDetail', 'upgradeVehicle', 'coupon', 'latestPayment']);
        $esTransporte = $booking->booking_type === Booking::TYPE_TRANSPORT;
        $titulo = $booking->bookable?->title ?? ('Reserva #'.$booking->id);
        $moneda = $booking->currency_code ?: SiteSettings::currency();
        $total = number_format((float) $booking->total_price, 2, '.', ',');

        $mensaje = (new MailMessage)
            ->subject($this->paid
                ? 'Pago recibido · reserva #'.$booking->id
                : 'Tu reserva #'.$booking->id.' está apartada')
            ->greeting('¡Hola '.($booking->user?->first_name ?? '').'!')
            ->line($this->paid
                ? 'Hemos recibido tu pago de **'.$titulo.'**. Adjuntamos el comprobante.'
                : 'Hemos apartado tu reserva de **'.$titulo.'**. Adjuntamos el comprobante con todo el detalle.')
            ->line('**Referencia:** #'.$booking->id)
            ->line($esTransporte
                ? '**Recogida:** '.$booking->starts_at?->format('d/m/Y H:i')
                : '**Fecha:** '.$booking->starts_at?->format('d/m/Y'))
            ->line(($esTransporte ? '**Unidades:** ' : '**Personas:** ').$booking->party_size)
            ->line('**Total:** '.$total.' '.$moneda)
            ->line($this->paid
                ? 'Tu reserva queda **pagada**. Nos vemos pronto.'
                : 'Tu reserva está **pendiente de pago**. Un agente te acompañará por WhatsApp para completarlo.');

        if ($this->whatsappUrl) {
            $mensaje->action('Continuar por WhatsApp', $this->whatsappUrl);
        }

        $mensaje->line($this->paid
            ? 'Cualquier duda, respóndenos a este correo.'
            : 'Si no completas el pago, la reserva podría liberarse. Cualquier duda, respóndenos a este correo.');

        // El PDF se genera aquí para que el adjunto refleje el estado actual.
        return $mensaje->attachData(
            BookingReceipt::pdf($booking),
            BookingReceipt::filename($booking),
            BookingReceipt::attachmentOptions(),
        );
    }
}
