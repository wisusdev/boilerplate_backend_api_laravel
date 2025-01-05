<?php

namespace App\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends Notification
{
    public static $toMailCallback;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable);
        }

        $name = $notifiable->first_name;

        $url = config('app.frontend_url') . str_replace('/api/v1', '', $this->verificationUrl($notifiable));

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->greeting('Hello!')
            ->line('Hello ' . $name . ',')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $url)
            ->line('If you did not create an account, no further action is required.');
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification())
            ],
            false
        );
    }

    public static function toMailUsing($callback): void
    {
        static::$toMailCallback = $callback;
    }
}
