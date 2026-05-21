<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use Illuminate\Support\Facades\Log;

final class SupportWalletOnboardingService
{
    public function __construct(
        private ConsumerWalletPayCodeService $payCodes,
        private EvolutionWhatsAppClient $whatsapp,
    ) {}

    /**
     * @return array{ok: bool, message?: string, wallet?: WhatsappWallet}
     */
    public function ensureWalletFromPhone(string $phoneInput, string $countryIso = 'NG'): array
    {
        $iso = strtoupper(substr(trim($countryIso), 0, 2));
        $e164 = PhoneNormalizer::canonicalE164ForCountry($phoneInput, $iso);
        if ($e164 === null) {
            $label = $iso !== '' ? $iso : 'selected country';

            return ['ok' => false, 'message' => "Invalid WhatsApp number for {$label}."];
        }

        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $e164],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        $this->payCodes->ensureForWallet($wallet);

        return ['ok' => true, 'wallet' => $wallet];
    }

    public function alreadySentSupportWelcome(WhatsappWallet $wallet): bool
    {
        if ($wallet->support_whatsapp_welcome_sent_at !== null) {
            return true;
        }

        return SupportTicket::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('wallet_onboarding_sent_at')
            ->exists();
    }

    public function sendWelcomeMessage(WhatsappWallet $wallet): bool
    {
        if ($this->alreadySentSupportWelcome($wallet)) {
            return true;
        }

        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            Log::warning('support.wallet_onboarding: no evolution instance');

            return false;
        }

        $brand = (string) config('whatsapp.bot_brand_name', 'CheckoutPay');
        $template = (string) config('support.whatsapp_welcome');
        $text = str_replace(':brand', $brand, $template);

        $sent = $this->whatsapp->sendText($instance, $wallet->phone_e164, $text);
        if ($sent) {
            $wallet->forceFill(['support_whatsapp_welcome_sent_at' => now()])->save();
        } else {
            Log::warning('support.wallet_onboarding: welcome send failed', [
                'wallet_id' => $wallet->id,
            ]);
        }

        return $sent;
    }

    public static function maskPhone(string $e164): string
    {
        if (strlen($e164) < 8) {
            return $e164;
        }

        return substr($e164, 0, 5).'***'.substr($e164, -3);
    }
}
