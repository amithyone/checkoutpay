<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenterResetPasswordNotification extends Notification
{
    public function __construct(protected string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(sprintf(
            '/password/reset/%s?email=%s',
            $this->token,
            urlencode($notifiable->email)
        ));

        return (new MailMessage)
            ->subject('Reset your rentals password')
            ->line('You are receiving this email because we received a password reset request for your rentals account.')
            ->action('Reset Password', $resetUrl)
            ->line('If you did not request a password reset, no further action is required.');
    }
}

