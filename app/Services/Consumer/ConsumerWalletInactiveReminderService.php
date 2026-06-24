<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletInactiveReminder;
use App\Services\PushNotificationService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappWalletAppLinkCopy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ConsumerWalletInactiveReminderService
{
    public function __construct(
        private EvolutionWhatsAppClient $whatsapp,
        private PushNotificationService $push,
    ) {}

    /**
     * @return array{wallets: int, push: int, whatsapp: int, skipped: int}
     */
    public function sendForSlot(string $slot): array
    {
        $slot = $this->normalizeSlot($slot);
        if ($slot === null) {
            return ['wallets' => 0, 'push' => 0, 'whatsapp' => 0, 'skipped' => 0];
        }

        if (! (bool) config('consumer_wallet.inactive_reminders_enabled', true)) {
            return ['wallets' => 0, 'push' => 0, 'whatsapp' => 0, 'skipped' => 0];
        }

        $today = $this->reminderDate();
        $startOfDay = $today->copy()->startOfDay();
        $minBalance = (float) config('consumer_wallet.inactive_reminder_min_balance', 1);

        $stats = ['wallets' => 0, 'push' => 0, 'whatsapp' => 0, 'skipped' => 0];

        foreach ($this->candidateWallets($startOfDay, $minBalance) as $wallet) {
            if ($this->alreadySent($wallet->id, $today, $slot)) {
                $stats['skipped']++;

                continue;
            }

            $stats['wallets']++;
            $reminder = WhatsappWalletInactiveReminder::query()->create([
                'whatsapp_wallet_id' => $wallet->id,
                'reminder_on' => $today->toDateString(),
                'slot' => $slot,
                'push_sent' => false,
                'whatsapp_sent' => false,
            ]);

            $displayName = $this->displayName($wallet);
            $balanceLabel = $this->formatBalance($wallet);

            if ($this->sendPush($wallet, $displayName, $balanceLabel)) {
                $reminder->push_sent = true;
                $stats['push']++;
            }

            if ($this->sendWhatsapp($wallet, $displayName, $balanceLabel)) {
                $reminder->whatsapp_sent = true;
                $stats['whatsapp']++;
            }

            $reminder->save();

            Log::info('consumer_wallet.inactive_reminder.sent', [
                'wallet_id' => $wallet->id,
                'slot' => $slot,
                'push' => $reminder->push_sent,
                'whatsapp' => $reminder->whatsapp_sent,
            ]);
        }

        return $stats;
    }

    public function inferSlotFromNow(): ?string
    {
        $hour = (int) $this->reminderDate()->format('G');

        if ($hour >= 7 && $hour < 14) {
            return WhatsappWalletInactiveReminder::SLOT_MORNING;
        }

        if ($hour >= 14 && $hour < 22) {
            return WhatsappWalletInactiveReminder::SLOT_EVENING;
        }

        return null;
    }

    /**
     * @return Collection<int, WhatsappWallet>
     */
    private function candidateWallets(Carbon $startOfDay, float $minBalance): Collection
    {
        return WhatsappWallet::query()
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->where('admin_bot_paused', false)
            ->where('balance', '>=', $minBalance)
            ->whereDoesntHave('transactions', function ($query) use ($startOfDay) {
                $query->where('created_at', '>=', $startOfDay);
            })
            ->whereDoesntHave('consumerApiAccount', function ($query) use ($startOfDay) {
                $query->where('last_app_active_at', '>=', $startOfDay);
            })
            ->orderBy('id')
            ->get();
    }

    private function alreadySent(int $walletId, Carbon $today, string $slot): bool
    {
        return WhatsappWalletInactiveReminder::query()
            ->where('whatsapp_wallet_id', $walletId)
            ->whereDate('reminder_on', $today->toDateString())
            ->where('slot', $slot)
            ->exists();
    }

    private function sendPush(WhatsappWallet $wallet, string $displayName, string $balanceLabel): bool
    {
        $account = ConsumerWalletApiAccount::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('fcm_platform', '!=', 'web')
            ->first();

        if (! $account) {
            return false;
        }

        $title = (string) config('consumer_wallet.inactive_reminder_push_title', 'Hope your day is going well');
        $body = $this->pushBody($displayName, $balanceLabel);

        $token = (string) $account->fcm_token;

        $failed = $this->push->sendToTokens(
            [$token],
            $title,
            $body,
            [
                'type' => 'wallet_inactive_reminder',
                'wallet_id' => (string) $wallet->id,
            ],
            (string) config('consumer_wallet.inactive_reminder_push_channel', 'wallet_alerts'),
            PushNotificationService::PROFILE_CHECKOUTNOW,
        );
        ConsumerWalletApiAccount::clearFcmTokenIfInvalid($token, $failed);

        return true;
    }

    private function sendWhatsapp(WhatsappWallet $wallet, string $displayName, string $balanceLabel): bool
    {
        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            return false;
        }

        $brand = trim((string) config('whatsapp.bot_brand_name', 'CheckoutNow'));
        $text = $this->whatsappText($brand, $displayName, $balanceLabel);

        return $this->whatsapp->sendText($instance, (string) $wallet->phone_e164, $text);
    }

    private function whatsappText(string $brand, string $displayName, string $balanceLabel): string
    {
        $greeting = $displayName !== ''
            ? "Hey *{$displayName}*, hope you're doing well today!"
            : "Hey, hope you're doing well today!";

        return "{$greeting}\n\n".
            "Your *{$brand}* wallet balance is *{$balanceLabel}*.\n\n".
            "Would you like to complete at least one transaction today? You can send money, pay bills, or top up — we're here when you need us.".
            WhatsappWalletAppLinkCopy::downloadBlock();
    }

    private function pushBody(string $displayName, string $balanceLabel): string
    {
        $prefix = $displayName !== ''
            ? "Hey {$displayName}, hope you're doing well. "
            : "Hope you're doing well. ";

        return $prefix."Your balance is {$balanceLabel}. Complete a transfer or bill payment today?";
    }

    private function displayName(WhatsappWallet $wallet): string
    {
        $name = trim((string) ($wallet->sender_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $fname = trim((string) ($wallet->kyc_fname ?? ''));
        if ($fname !== '') {
            return $fname;
        }

        return '';
    }

    private function formatBalance(WhatsappWallet $wallet): string
    {
        return '₦'.number_format((float) $wallet->balance, 2);
    }

    private function reminderDate(): Carbon
    {
        $tz = (string) config('consumer_wallet.inactive_reminder_timezone', 'Africa/Lagos');

        return Carbon::now($tz);
    }

    private function normalizeSlot(string $slot): ?string
    {
        $slot = strtolower(trim($slot));
        if (in_array($slot, [WhatsappWalletInactiveReminder::SLOT_MORNING, WhatsappWalletInactiveReminder::SLOT_EVENING], true)) {
            return $slot;
        }

        return null;
    }
}
