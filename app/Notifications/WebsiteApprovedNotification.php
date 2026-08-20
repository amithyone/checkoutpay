<?php

namespace App\Notifications;

use App\Models\BusinessWebsite;
use App\Models\Setting;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
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
        if (! $notifiable->shouldReceiveEmailNotifications() || ! $notifiable->shouldReceiveWebsiteNotifications()) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');

        return EmailTemplateService::toMailMessage(
            'website-approved',
            'emails.website-approved',
            'Website Approved - '.$appName,
            [
                'business' => $notifiable,
                'website' => $this->website,
                'appName' => $appName,
            ],
        );
    }
}
