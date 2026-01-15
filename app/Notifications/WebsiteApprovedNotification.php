<?php

namespace App\Notifications;

use App\Models\BusinessWebsite;
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Website Approved - ' . config('app.name', 'CheckoutPay'))
            ->view('emails.website-approved', [
                'business' => $notifiable,
                'website' => $this->website,
                'appName' => config('app.name', 'CheckoutPay'),
            ]);
    }
}
