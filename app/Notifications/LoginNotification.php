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
        // Check if email notifications and security notifications are enabled
        if (!$notifiable->shouldReceiveEmailNotifications() || !$notifiable->shouldReceiveSecurityNotifications()) {
            return []; // Don't send notification
        }
        
        return ['mail'];
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
