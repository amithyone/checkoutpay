<?php

namespace App\Notifications;

use App\Models\BusinessLoan;
use App\Models\BusinessLoanSchedule;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PeerLoanRepaymentCollectedNotification extends Notification
{
    use Queueable;

    public const ROLE_BORROWER = 'borrower';

    public const ROLE_LENDER = 'lender';

    public function __construct(
        public BusinessLoan $loan,
        public BusinessLoanSchedule $schedule,
        public float $amountCollected,
        public float $remainingOnSchedule,
        public float $remainingOnLoan,
        public string $role
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if (method_exists($notifiable, 'shouldReceiveEmailNotifications') && $notifiable->shouldReceiveEmailNotifications()) {
            $channels[] = 'mail';
        }
        if (method_exists($notifiable, 'isTelegramConfigured') && $notifiable->isTelegramConfigured()) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        $subject = $this->role === self::ROLE_LENDER
            ? 'Loan repayment received - '.$appName
            : 'Loan repayment collected - '.$appName;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.peer-loan-repayment-collected', [
                'business' => $notifiable,
                'loan' => $this->loan,
                'schedule' => $this->schedule,
                'amountCollected' => $this->amountCollected,
                'remainingOnSchedule' => $this->remainingOnSchedule,
                'remainingOnLoan' => $this->remainingOnLoan,
                'role' => $this->role,
                'appName' => $appName,
            ]);
    }

    public function toTelegram(object $notifiable): ?string
    {
        $appName = Setting::get('site_name', 'CheckoutPay');
        $amount = number_format($this->amountCollected, 2);
        $remainingLoan = number_format($this->remainingOnLoan, 2);
        $remainingInstallment = number_format($this->remainingOnSchedule, 2);
        $title = $this->role === self::ROLE_LENDER
            ? '💰 <b>Loan repayment received</b>'
            : '✅ <b>Loan repayment collected</b>';
        $counterparty = $this->role === self::ROLE_LENDER
            ? 'From borrower: '.($this->loan->borrower->name ?? 'N/A')
            : 'To lender: '.($this->loan->offer?->lender?->name ?? 'N/A');

        return $title."\n\n".
            "Loan #{$this->loan->id}\n".
            "{$counterparty}\n".
            "Installment #{$this->schedule->sequence} due ".$this->schedule->due_at->format('M d, Y')."\n".
            "Amount: ₦{$amount}\n".
            "Installment remaining: ₦{$remainingInstallment}\n".
            "Loan remaining: ₦{$remainingLoan}\n\n".
            "{$appName}";
    }
}
