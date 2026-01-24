<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminLoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $adminName,
        public string $adminEmail,
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
        if ($notifiable->isTelegramConfigured() && $notifiable->telegram_admin_login_enabled) {
            $channels[] = 'telegram';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return (new MailMessage)
            ->subject('Admin Login Detected - ' . $appName)
            ->view('emails.admin-login', [
                'business' => $notifiable,
                'adminName' => $this->adminName,
                'adminEmail' => $this->adminEmail,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'appName' => $appName,
            ]);
    }

    public function toTelegram(object $notifiable): ?string
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return "⚠️ <b>Admin Login Detected</b>\n\n" .
               "An administrator has logged in as your business account.\n\n" .
               "Admin: {$this->adminName} ({$this->adminEmail})\n" .
               "IP Address: {$this->ipAddress}\n" .
               "Device: {$this->userAgent}\n" .
               "Time: " . now()->format('M d, Y H:i') . "\n\n" .
               "If you didn't authorize this, please contact support immediately.\n\n" .
               "{$appName}";
    }
}
