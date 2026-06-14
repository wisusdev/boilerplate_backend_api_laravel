<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $invoiceCode, private string $amount)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your invoice is ready')
            ->greeting('Hello!')
            ->line('Your invoice has been generated successfully.')
            ->line('Invoice code: ' . $this->invoiceCode)
            ->line('Amount: ' . $this->amount);
    }
}