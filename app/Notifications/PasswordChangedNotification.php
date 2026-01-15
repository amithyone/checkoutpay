<?php

namespace App\Notifications;

use App\Models\Setting;
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
        // Check if email notifications and security notifications are enabled
        if (!$notifiable->shouldReceiveEmailNotifications() || !$notifiable->shouldReceiveSecurityNotifications()) {
            return []; // Don't send notification
        }
        
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return (new MailMessage)
            ->subject('Password Changed - ' . $appName)
            ->view('emails.password-changed', [
                'business' => $notifiable,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'appName' => $appName,
            ]);
    }
}
