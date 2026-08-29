<?php

namespace App\Notifications;

use App\Http\Controllers\Api\Travel\SubscriberController;
use App\Support\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriberWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private ?string $name = null, private ?string $email = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // El nombre sale de los ajustes: estaba fijado en el código y quedaba
        // desincronizado en cuanto el sitio cambiaba de nombre.
        $appName = SiteSettings::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');
        $greeting = $this->name ? "¡Hola, {$this->name}!" : '¡Hola!';

        $message = (new MailMessage)
            // Sin `from` fijo: el remitente sale de MAIL_FROM_ADDRESS, que es el
            // dominio verificado para enviar. Forzar otro rompe SPF/DKIM.
            ->subject("¡Bienvenido a las ofertas de {$appName}! 🌋")
            ->greeting($greeting)
            ->line('Gracias por suscribirte. A partir de ahora recibirás nuestras mejores ofertas, descuentos y nuevas aventuras antes que nadie.')
            ->action('Explorar tours', $frontendUrl.'/tours');

        // El enlace lleva el token de baja: sin él nadie puede dar de baja a otro.
        if ($this->email) {
            $query = http_build_query([
                'email' => $this->email,
                'token' => SubscriberController::unsubscribeToken($this->email),
            ]);
            $message->line('Si deseas dejar de recibir estos correos, puedes darte de baja aquí: '.$frontendUrl.'/unsubscribe?'.$query);
        }

        return $message->salutation("Nos vemos en la aventura,\n{$appName}");
    }
}
