<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceNotification extends Notification
{
    use Queueable;

	private string $name;
	private array $items;
	private float $total;

	private string $invoiceId;

    /**
     * Create a new notification instance.
     */
    public function __construct($name, $total, $items, $invoiceId)
    {
        $this->name = $name;
		$this->items = $items;
		$this->total = $total;
		$this->invoiceId = $invoiceId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
		$url = config('app.frontend_url') . '/account/invoices/show/' . $this->invoiceId;

        $mailMessage = new MailMessage();

		return $mailMessage
			->subject(__('mail.invoice_subject', ['name' => $this->name]))
			->view('mail.invoices.invoice', [
			'name' => $this->name,
			'items' => $this->items,
			'total' => $this->total,
			'url' => $url
		]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
