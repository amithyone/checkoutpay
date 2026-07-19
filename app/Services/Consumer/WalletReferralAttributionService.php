<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletReferral;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WalletReferralAttributionService
{
    public function __construct(
        private WalletReferralSettingsService $settings,
        private ConsumerWalletPayCodeService $payCodes,
    ) {}

    /**
     * Attribute at registration from pay_code or phone. Registration is supreme.
     */
    public function attributeFromRegistration(WhatsappWallet $referred, ?string $codeOrPhone): ?WhatsappWalletReferral
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $input = trim((string) $codeOrPhone);
        if ($input === '') {
            return null;
        }

        if (WhatsappWalletReferral::query()->where('referred_wallet_id', $referred->id)->exists()) {
            return WhatsappWalletReferral::query()->where('referred_wallet_id', $referred->id)->first();
        }

        [$referrer, $source, $used] = $this->resolveReferrerFromCodeOrPhone($input, $referred);
        if ($referrer === null) {
            Log::info('wallet.referral.registration_unresolved', [
                'referred_wallet_id' => $referred->id,
                'input' => $used,
            ]);

            return null;
        }

        return $this->lockAttribution($referred, $referrer, $source, $used);
    }

    /**
     * First successful P2P credit may claim referrer only if not already attributed.
     */
    public function attributeFromFirstP2pCredit(WhatsappWallet $recipient, WhatsappWallet $sender): ?WhatsappWalletReferral
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        if (WhatsappWalletReferral::query()->where('referred_wallet_id', $recipient->id)->exists()) {
            return null;
        }

        if ((int) $recipient->id === (int) $sender->id) {
            return null;
        }

        if ($sender->status !== WhatsappWallet::STATUS_ACTIVE) {
            return null;
        }

        return $this->lockAttribution(
            $recipient,
            $sender,
            WhatsappWalletReferral::SOURCE_FIRST_P2P,
            null,
        );
    }

    /**
     * @return array{0: ?WhatsappWallet, 1: string, 2: string}
     */
    private function resolveReferrerFromCodeOrPhone(string $input, WhatsappWallet $referred): array
    {
        $byCode = $this->payCodes->findByPayCode($input);
        if ($byCode instanceof WhatsappWallet) {
            if ((int) $byCode->id === (int) $referred->id) {
                return [null, WhatsappWalletReferral::SOURCE_CODE, $input];
            }
            if ($byCode->status !== WhatsappWallet::STATUS_ACTIVE) {
                return [null, WhatsappWalletReferral::SOURCE_CODE, $input];
            }

            return [$byCode, WhatsappWalletReferral::SOURCE_CODE, strtoupper(trim($input))];
        }

        $e164 = PhoneNormalizer::canonicalAuthE164Digits($input)
            ?? PhoneNormalizer::digitsOnly($input);
        if ($e164 === null || $e164 === '') {
            return [null, WhatsappWalletReferral::SOURCE_PHONE, $input];
        }

        $byPhone = WhatsappWallet::query()
            ->where('phone_e164', $e164)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! $byPhone || (int) $byPhone->id === (int) $referred->id) {
            return [null, WhatsappWalletReferral::SOURCE_PHONE, $e164];
        }

        return [$byPhone, WhatsappWalletReferral::SOURCE_PHONE, $e164];
    }

    private function lockAttribution(
        WhatsappWallet $referred,
        WhatsappWallet $referrer,
        string $source,
        ?string $codeUsed,
    ): ?WhatsappWalletReferral {
        try {
            return DB::transaction(function () use ($referred, $referrer, $source, $codeUsed) {
                $existing = WhatsappWalletReferral::query()
                    ->where('referred_wallet_id', $referred->id)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }

                $now = now($this->settings->timezone());
                $ends = $now->copy()->addMonths($this->settings->bonusMonths());

                $referral = WhatsappWalletReferral::query()->create([
                    'referred_wallet_id' => $referred->id,
                    'referrer_wallet_id' => $referrer->id,
                    'attribution_source' => $source,
                    'referral_code_used' => $codeUsed,
                    'attributed_at' => $now,
                    'bonus_ends_at' => $ends,
                    'counted_tx_total' => 0,
                    'milestones_paid' => 0,
                ]);

                WhatsappWallet::query()->where('id', $referred->id)->update([
                    'referred_by_wallet_id' => $referrer->id,
                ]);

                Log::info('wallet.referral.attributed', [
                    'referral_id' => $referral->id,
                    'referred_wallet_id' => $referred->id,
                    'referrer_wallet_id' => $referrer->id,
                    'source' => $source,
                ]);

                return $referral;
            });
        } catch (\Throwable $e) {
            Log::warning('wallet.referral.attribute_failed', [
                'referred_wallet_id' => $referred->id,
                'referrer_wallet_id' => $referrer->id,
                'error' => $e->getMessage(),
            ]);

            return WhatsappWalletReferral::query()->where('referred_wallet_id', $referred->id)->first();
        }
    }
}
