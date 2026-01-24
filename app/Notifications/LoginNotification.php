<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        
        // Email notifications
        if ($notifiable->shouldReceiveEmailNotifications() && $notifiable->shouldReceiveSecurityNotifications()) {
            $channels[] = 'mail';
        }
        
        // Telegram notifications
        if ($notifiable->isTelegramConfigured() && $notifiable->telegram_login_enabled) {
            $channels[] = 'telegram';
        }
        
        return $channels;
    }

    public function toTelegram(object $notifiable): ?string
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return "🔐 <b>New Login Detected</b>\n\n" .
               "Account: {$notifiable->name}\n" .
               "IP Address: {$this->ipAddress}\n" .
               "Device: {$this->userAgent}\n" .
               "Time: " . now()->format('M d, Y H:i') . "\n\n" .
               "If this wasn't you, please secure your account immediately.\n\n" .
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
        
        $subject = EmailTemplateService::getTemplateSubject('login-notification', 'New Login Detected - ' . $appName);
        
        // Check if custom template is enabled
        if (EmailTemplateService::isCustomTemplate('login-notification')) {
            $html = EmailTemplateService::renderTemplate('login-notification', $data, 'emails.login-notification');
            return (new MailMessage)
                ->subject($subject)
                ->html($html);
        }
        
        return (new MailMessage)
            ->subject($subject)
            ->view('emails.login-notification', $data);
    }
}
