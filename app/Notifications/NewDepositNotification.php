<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Setting;
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
        $channels = [];
        
        // Email notifications
        if ($notifiable->shouldReceiveEmailNotifications() && $notifiable->shouldReceivePaymentNotifications()) {
            $channels[] = 'mail';
        }
        
        // Telegram notifications
        if ($notifiable->isTelegramConfigured() && $notifiable->telegram_payment_enabled) {
            $channels[] = 'telegram';
        }
        
        return $channels;
    }

    public function toTelegram(object $notifiable): ?string
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        $amount = number_format($this->payment->amount, 2);
        
        return "💰 <b>New Payment Received</b>\n\n" .
               "Amount: ₦{$amount}\n" .
               "Transaction ID: {$this->payment->transaction_id}\n" .
               "Payer: {$this->payment->payer_name}\n" .
               "Time: " . $this->payment->created_at->format('M d, Y H:i') . "\n\n" .
               "{$appName}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        
        return (new MailMessage)
            ->subject('New Payment Received - ₦' . number_format($this->payment->amount, 2) . ' - ' . $appName)
            ->view('emails.new-deposit', [
                'business' => $notifiable,
                'payment' => $this->payment,
                'appName' => $appName,
            ]);
    }
}
