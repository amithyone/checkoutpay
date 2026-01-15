<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public WithdrawalRequest $withdrawal
    ) {}

    public function via(object $notifiable): array
    {
        // Check if email notifications and withdrawal notifications are enabled
        if (!$notifiable->shouldReceiveEmailNotifications() || !$notifiable->shouldReceiveWithdrawalNotifications()) {
            return []; // Don't send notification
        }
        
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return (new MailMessage)
            ->subject('Withdrawal Request Submitted - ' . $appName)
            ->view('emails.withdrawal-requested', [
                'business' => $notifiable,
                'withdrawal' => $this->withdrawal,
                'appName' => $appName,
            ]);
    }
}
