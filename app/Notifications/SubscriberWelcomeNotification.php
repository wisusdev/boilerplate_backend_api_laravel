<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriberWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private ?string $name = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = 'Cusgo Adventures';
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');
        $greeting = $this->name ? "¡Hola, {$this->name}!" : '¡Hola!';

        return (new MailMessage)
            ->from('ofertas@cusgo.sv', $appName)
            ->subject("¡Bienvenido a las ofertas de {$appName}! 🌋")
            ->greeting($greeting)
            ->line('Gracias por suscribirte. A partir de ahora recibirás nuestras mejores ofertas, descuentos y nuevas aventuras antes que nadie.')
            ->action('Explorar tours', $frontendUrl . '/tours')
            ->line('Si en algún momento deseas dejar de recibir estos correos, podrás darte de baja cuando quieras.')
            ->salutation("Nos vemos en la aventura,\n{$appName}");
    }
}
