<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $ipAddress,
        public string $userAgent
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Changed - ' . config('app.name', 'CheckoutPay'))
            ->view('emails.password-changed', [
                'business' => $notifiable,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'appName' => config('app.name', 'CheckoutPay'),
            ]);
    }
}
