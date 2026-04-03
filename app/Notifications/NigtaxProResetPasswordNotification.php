<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NigtaxProResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $resetUrl = route('nigtax-pro.password.reset', ['token' => $this->token], true)
            .'?email='.urlencode($email);

        return (new MailMessage)
            ->subject('Reset your NigTax PRO password')
            ->line('You asked to reset the password for your NigTax PRO account (used on the NigTax calculator).')
            ->action('Choose a new password', $resetUrl)
            ->line('This link expires in '.config('auth.passwords.nigtax_pro.expire', 60).' minutes.')
            ->line('If you did not request this, you can ignore this email.');
    }
}
