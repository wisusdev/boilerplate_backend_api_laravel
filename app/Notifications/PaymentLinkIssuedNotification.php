<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\PaymentLink;
use App\Support\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El enlace de pago del banco ya está listo para el cliente.
 *
 * El correo nombra el dominio al que lleva el botón. Es deliberado: acostumbrar
 * al cliente a comprobar dónde acaba antes de teclear una tarjeta es la única
 * defensa que le queda el día que le llegue un correo parecido que no sea
 * nuestro.
 */
class PaymentLinkIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PaymentLink $link,
        private readonly ?Booking $booking = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $moneda = $this->link->currency_code ?: SiteSettings::currency();
        $importe = number_format((float) $this->link->amount, 2, '.', ',');
        $titulo = $this->booking?->bookable?->title;
        $front = rtrim((string) config('app.frontend_url'), '/');

        $mensaje = (new MailMessage)
            ->subject('Enlace para pagar tu reserva'.($this->booking ? ' #'.$this->booking->id : ''))
            ->greeting('¡Hola '.($notifiable->first_name ?? '').'!')
            ->line($titulo
                ? 'Ya puedes completar el pago de **'.$titulo.'**.'
                : 'Ya puedes completar el pago de tu reserva.')
            ->line('**Importe:** '.$importe.' '.$moneda)
            ->line('**Referencia:** '.$this->link->reference);

        if ($this->link->expires_at) {
            $mensaje->line('**Válido hasta:** '.$this->link->expires_at->format('d/m/Y H:i'));
        }

        $instrucciones = SiteSettings::bacInstructions();

        if ($instrucciones !== '') {
            $mensaje->line($instrucciones);
        }

        // Se lleva al cliente a NUESTRA página, no directo al banco: allí ve el
        // destino antes de saltar y tiene el botón de "ya pagué".
        $mensaje->action('Ir a pagar', $front.'/pay/'.$this->link->reference);

        $host = $this->link->host();

        if ($host) {
            $mensaje->line('El pago se realiza en **'.$host.'**, la página segura del banco. '
                .'Nosotros no vemos ni guardamos los datos de tu tarjeta.');
        }

        return $mensaje->line('Cuando pagues, avísanos desde esa misma página para que confirmemos tu reserva cuanto antes.');
    }
}
