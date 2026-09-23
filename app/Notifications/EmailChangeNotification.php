<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía a la dirección ANTERIOR cuando la cuenta cambia de correo, para que
 * el titular real pueda notar y reaccionar a un cambio que no hizo (p. ej. un
 * token robado usado para secuestrar la cuenta vía el correo nuevo).
 */
class EmailChangeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $newEmail) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting('Hello!')
            ->line('The email address on your account was recently changed to '.$this->newEmail.'.')
            ->line('If you did not make this change, please contact us immediately — someone else may have access to your account.')
            ->line('If you did change it, no further action is required.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
