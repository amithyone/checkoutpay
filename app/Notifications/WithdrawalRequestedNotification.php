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
        $channels = [];
        
        // Email notifications
        if ($notifiable->shouldReceiveEmailNotifications() && $notifiable->shouldReceiveWithdrawalNotifications()) {
            $channels[] = 'mail';
        }
        
        // Telegram notifications
        if ($notifiable->isTelegramConfigured() && $notifiable->telegram_withdrawal_enabled) {
            $channels[] = 'telegram';
        }
        
        return $channels;
    }

    public function toTelegram(object $notifiable): ?string
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        $amount = number_format($this->withdrawal->amount, 2);
        
        return "💸 <b>Withdrawal Request Submitted</b>\n\n" .
               "Amount: ₦{$amount}\n" .
               "Account: {$this->withdrawal->account_name}\n" .
               "Account Number: {$this->withdrawal->account_number}\n" .
               "Bank: {$this->withdrawal->bank_name}\n" .
               "Status: Pending Review\n" .
               "Time: " . $this->withdrawal->created_at->format('M d, Y H:i') . "\n\n" .
               "{$appName}";
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
