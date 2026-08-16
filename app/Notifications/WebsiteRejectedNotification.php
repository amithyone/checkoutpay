<?php

namespace App\Notifications;

use App\Models\BusinessWebsite;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebsiteRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BusinessWebsite $website
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');

        return (new MailMessage)
            ->subject('Website not approved - '.$appName)
            ->view('emails.website-rejected', [
                'business' => $notifiable,
                'website' => $this->website,
                'appName' => $appName,
            ]);
    }
}
