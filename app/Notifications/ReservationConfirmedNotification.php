<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $title, private string $body, private array $details = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello!')
            ->line($this->body);

        foreach ($this->details as $label => $value) {
            $mail->line(ucfirst(str_replace('_', ' ', (string) $label)) . ': ' . (is_array($value) ? json_encode($value) : $value));
        }

        return $mail;
    }
}