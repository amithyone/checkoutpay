<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDepositNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Payment Received - ₦' . number_format($this->payment->amount, 2) . ' - ' . config('app.name', 'CheckoutPay'))
            ->view('emails.new-deposit', [
                'business' => $notifiable,
                'payment' => $this->payment,
                'appName' => config('app.name', 'CheckoutPay'),
            ]);
    }
}
