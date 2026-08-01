<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappProactiveOutbound;
use Illuminate\Support\Facades\Log;

final class WalletSignupStaffNotifier
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
    ) {}

    public function notifyIfFirstComplete(WhatsappWallet $wallet): void
    {
        $wallet = $wallet->fresh();
        if (! $wallet instanceof WhatsappWallet) {
            return;
        }

        if ($wallet->wallet_signup_notified_at !== null) {
            return;
        }

        if (! $wallet->hasPin() || trim((string) $wallet->sender_name) === '') {
            return;
        }

        if ($wallet->needsQuickWalletSetup()) {
            return;
        }

        if (! $this->globallyEnabled()) {
            return;
        }

        $recipients = Admin::query()
            ->where('role', Admin::ROLE_WALLET_SUPPORT)
            ->where('is_active', true)
            ->where('notify_wallet_signup', true)
            ->whereNotNull('whatsapp_e164')
            ->where('whatsapp_e164', '!=', '')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $instance = WhatsappEvolutionConfigResolver::walletInstanceForPhone((string) $wallet->phone_e164);
        if ($instance === '') {
            Log::debug('wallet_signup_staff_notify: no whatsapp instance');

            return;
        }

        $name = trim((string) $wallet->sender_name);
        $phoneMasked = $this->maskPhone((string) $wallet->phone_e164);
        $adminUrl = route('admin.whatsapp-wallet.wallets.show', $wallet);

        $text = "*New wallet signup*\n\n".
            "Name: {$name}\n".
            "Phone: {$phoneMasked}\n".
            "Wallet #{$wallet->id}\n\n".
            "View: {$adminUrl}";

        foreach ($recipients as $admin) {
            if (! $admin->receivesWalletSignupAlerts()) {
                continue;
            }
            try {
                $this->client->sendText($instance, (string) $admin->whatsapp_e164, $text);
            } catch (\Throwable $e) {
                Log::warning('wallet_signup_staff_notify failed', [
                    'admin_id' => $admin->id,
                    'wallet_id' => $wallet->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $wallet->forceFill(['wallet_signup_notified_at' => now()])->saveQuietly();
    }

    private function globallyEnabled(): bool
    {
        if (! WhatsappProactiveOutbound::enabled()) {
            return false;
        }

        $stored = Setting::get('wallet_signup_staff_alerts_enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return true;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 8) {
            return $phone;
        }

        return substr($digits, 0, 4).'***'.substr($digits, -3);
    }
}
