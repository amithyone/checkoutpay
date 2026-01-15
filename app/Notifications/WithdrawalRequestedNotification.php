<?php

namespace App\Notifications;

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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Withdrawal Request Submitted - ' . config('app.name', 'CheckoutPay'))
            ->view('emails.withdrawal-requested', [
                'business' => $notifiable,
                'withdrawal' => $this->withdrawal,
                'appName' => config('app.name', 'CheckoutPay'),
            ]);
    }
}
