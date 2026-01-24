<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toTelegram')) {
            return;
        }

        $message = $notification->toTelegram($notifiable);

        if (!$message) {
            return;
        }

        // Check if Telegram is configured
        if (!$notifiable->telegram_bot_token || !$notifiable->telegram_chat_id) {
            return;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$notifiable->telegram_bot_token}/sendMessage", [
                'chat_id' => $notifiable->telegram_chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram notification failed', [
                    'business_id' => $notifiable->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification error', [
                'business_id' => $notifiable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
