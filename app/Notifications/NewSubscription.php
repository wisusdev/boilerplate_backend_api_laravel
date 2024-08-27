<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSubscription extends Notification
{
    use Queueable;

	private $name;
	private $start_date;
	private $plan_name;
	private $price;

    /**
     * Create a new notification instance.
     */
	public function __construct($name, $start_date, $plan_name, $price)
	{
		$this->name = $name;
		$this->start_date = $start_date;
		$this->plan_name = $plan_name;
		$this->price = $price;
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
        $mailMessage = new MailMessage();

		return $mailMessage
			->subject(__('mail.new_subscription_subject', ['name' => $this->name, 'plan' => $this->plan_name]))
			->view('mail.invoices.new_subscription', [
			'name' => $this->name,
			'start_date' => $this->start_date,
			'plan_name' => $this->plan_name,
			'price' => $this->price
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
