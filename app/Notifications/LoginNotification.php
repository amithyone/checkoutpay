<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $ipAddress,
        public string $userAgent
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->shouldReceiveEmailNotifications() && $notifiable->shouldReceiveSecurityNotifications()) {
            $channels[] = 'mail';
        }

        if ($notifiable->isTelegramConfigured() && $notifiable->telegram_login_enabled) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    public function toTelegram(object $notifiable): ?string
    {
        $appName = Setting::get('site_name', 'CheckoutPay');

        return "🔐 <b>New Login Detected</b>\n\n".
               "Account: {$notifiable->name}\n".
               "IP Address: {$this->ipAddress}\n".
               "Device: {$this->userAgent}\n".
               'Time: '.now()->format('M d, Y H:i')."\n\n".
               "If this wasn't you, please secure your account immediately.\n\n".
               "{$appName}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        $data = [
            'business' => $notifiable,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'appName' => $appName,
        ];

        return EmailTemplateService::toMailMessage(
            'login-notification',
            'emails.login-notification',
            'New Login Detected - '.$appName,
            $data,
        );
    }
}
