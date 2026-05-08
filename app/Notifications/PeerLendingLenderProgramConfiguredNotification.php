<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PeerLendingLenderProgramConfiguredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Business $business
    ) {}

    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'shouldReceiveEmailNotifications') && $notifiable->shouldReceiveEmailNotifications()) {
            return ['mail'];
        }

        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        $rules = $this->business->peerLendingLenderRulesSummary();

        return (new MailMessage)
            ->subject('Peer lending (lender) program — '.$appName)
            ->view('emails.peer-lending-lender-program', [
                'business' => $this->business,
                'rules' => $rules,
                'appName' => $appName,
            ]);
    }
}
