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

        $mailMessage = new MailMessage();

        return $mailMessage->view('mail.email_verify', [
            'name' => $name,
            'url' => $url
        ]);
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
