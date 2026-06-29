<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación genérica de eventos de una reserva (cancelación, reagendamiento,
 * confirmación de mensaje, etc.). Reutilizable: recibe título, cuerpo y detalles.
 */
class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $subject,
        private string $body,
        private array $details = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->line($this->body);

        foreach ($this->details as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $mail->line(ucfirst(str_replace('_', ' ', (string) $label)) . ': ' . (is_array($value) ? json_encode($value) : $value));
        }

        return $mail;
    }
}
