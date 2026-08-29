<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\BookingReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $body,
        private array $details = [],
        private ?Booking $booking = null,
    ) {}

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
            $mail->line(ucfirst(str_replace('_', ' ', (string) $label)).': '.(is_array($value) ? json_encode($value) : $value));
        }

        // Se adjunta el comprobante: antes el cliente recibía un correo diciendo
        // que su reserva estaba confirmada, pero sin ningún documento.
        if ($this->booking) {
            $mail->attachData(
                BookingReceipt::pdf($this->booking),
                BookingReceipt::filename($this->booking),
                BookingReceipt::attachmentOptions(),
            );
        }

        return $mail;
    }
}
