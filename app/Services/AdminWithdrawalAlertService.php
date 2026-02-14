<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminWithdrawalAlertService
{
    /**
     * Send Telegram and email notification to admin for a new withdrawal request.
     * Configure admin_telegram_bot_token, admin_telegram_chat_id, admin_withdrawal_notification_email in Settings.
     */
    public function send(WithdrawalRequest $withdrawal): void
    {
        $withdrawal->load('business');

        $this->sendTelegram($withdrawal);
        $this->sendEmail($withdrawal);
    }

    protected function sendTelegram(WithdrawalRequest $withdrawal): void
    {
        $botToken = Setting::get('admin_telegram_bot_token');
        $chatId = Setting::get('admin_telegram_chat_id');

        if (! $botToken || ! $chatId) {
            return;
        }

        $appName = Setting::get('site_name', 'CheckoutPay');
        $amount = number_format($withdrawal->amount, 2);
        $businessName = $withdrawal->business->name ?? 'N/A';
        $url = route('admin.withdrawals.show', $withdrawal);

        $text = "🔔 <b>New Withdrawal Request</b>\n\n" .
            "Amount: ₦{$amount}\n" .
            "Business: {$businessName}\n" .
            "Account: {$withdrawal->account_name}\n" .
            "Bank: {$withdrawal->bank_name}\n" .
            "Request #{$withdrawal->id}\n" .
            "Time: " . $withdrawal->created_at->format('M d, Y H:i') . "\n\n" .
            "Review: {$url}\n\n" .
            "{$appName}";

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if (! $response->successful()) {
                Log::warning('Admin withdrawal Telegram alert failed', [
                    'withdrawal_id' => $withdrawal->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Admin withdrawal Telegram alert error', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendEmail(WithdrawalRequest $withdrawal): void
    {
        $email = Setting::get('admin_withdrawal_notification_email');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::send('emails.admin-withdrawal-requested', [
                'withdrawal' => $withdrawal,
                'business' => $withdrawal->business,
                'appName' => Setting::get('site_name', 'CheckoutPay'),
            ], function ($message) use ($email, $withdrawal) {
                $message->to($email)
                    ->subject('New Withdrawal Request #' . $withdrawal->id . ' – Please review');
            });
        } catch (\Exception $e) {
            Log::error('Admin withdrawal email alert failed', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
