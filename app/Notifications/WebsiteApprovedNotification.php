<?php

namespace App\Notifications;

use App\Models\BusinessWebsite;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebsiteApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BusinessWebsite $website
    ) {}

    public function via(object $notifiable): array
    {
        // Check if email notifications and website notifications are enabled
        if (!$notifiable->shouldReceiveEmailNotifications() || !$notifiable->shouldReceiveWebsiteNotifications()) {
            return []; // Don't send notification
        }
        
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return (new MailMessage)
            ->subject('Website Approved - ' . $appName)
            ->view('emails.website-approved', [
                'business' => $notifiable,
                'website' => $this->website,
                'appName' => $appName,
            ]);
    }
}
