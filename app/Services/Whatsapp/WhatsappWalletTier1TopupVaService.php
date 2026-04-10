<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingTopup;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPayVirtualAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tier 1: issue a fresh MevonPay temporary VA (createtempva) per top-up request; webhook credits the wallet.
 */
class WhatsappWalletTier1TopupVaService
{
    public function __construct(
        private MevonPayVirtualAccountService $mevonPayVa,
    ) {}

    public function isAvailable(): bool
    {
        if (! $this->mevonPayVa->isConfigured()) {
            return false;
        }

        return trim((string) config('services.mevonpay.temp_va_registration_number', '')) !== '';
    }

    /**
     * @return array{ok: bool, message?: string, account_number?: string, account_name?: string, bank_name?: string, bank_code?: string, expires_at?: string}
     */
    public function issueFreshVa(WhatsappWallet $wallet): array
    {
        if ((int) $wallet->tier !== WhatsappWallet::TIER_WHATSAPP_ONLY) {
            return ['ok' => false, 'message' => 'Permanent account is used for Tier 2.'];
        }

        if (! $this->isAvailable()) {
            return [
                'ok' => false,
                'message' => 'Temporary top-up accounts are not configured. Set *MEVONPAY_TEMP_VA_REGISTRATION_NUMBER* (and MevonPay keys), or *UPGRADE* to Tier 2 for a fixed account.',
            ];
        }

        $registrationNumber = trim((string) config('services.mevonpay.temp_va_registration_number', ''));
        $suffix = substr(preg_replace('/\D/', '', (string) $wallet->phone_e164) ?: '0', -4) ?: '0000';
        $fname = trim((string) config('whatsapp.wallet.tier1_temp_va_fname', 'WhatsApp'));
        $lname = trim((string) config('whatsapp.wallet.tier1_temp_va_lname', 'User')).' '.$suffix;

        try {
            $va = $this->mevonPayVa->createTempVa($fname, $lname, $registrationNumber, null);
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.tier1_temp_va_failed', [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Could not create a top-up account right now. Try again shortly or use *UPGRADE* (Tier 2).',
            ];
        }

        $ttlHours = max(1, (int) config('whatsapp.wallet.tier1_temp_va_ttl_hours', 48));
        $expiresAt = now()->addHours($ttlHours);

        WhatsappWalletPendingTopup::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNull('fulfilled_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        WhatsappWalletPendingTopup::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'account_number' => $va['account_number'],
            'account_name' => $va['account_name'] ?? null,
            'bank_name' => $va['bank_name'] ?? null,
            'bank_code' => $va['bank_code'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        return [
            'ok' => true,
            'account_number' => $va['account_number'],
            'account_name' => $va['account_name'] ?? '',
            'bank_name' => $va['bank_name'] ?? '',
            'bank_code' => $va['bank_code'] ?? '',
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Match MevonPay funding.success webhook to a pending Tier 1 top-up and credit the wallet.
     */
    public function tryFulfillFromWebhook(string $accountNumber, float $amount, string $reference): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return false;
        }

        $handled = false;

        DB::transaction(function () use ($accountNumber, $amount, $reference, &$handled) {
            $pending = WhatsappWalletPendingTopup::query()
                ->where('account_number', $accountNumber)
                ->whereNull('fulfilled_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $pending) {
                return;
            }

            $wallet = WhatsappWallet::query()->lockForUpdate()->find($pending->whatsapp_wallet_id);
            if (! $wallet || ! $wallet->isActive()) {
                return;
            }

            $credited = $this->computeCreditAmount($wallet, $amount);
            $newBal = round((float) $wallet->balance + $credited, 2);

            if ($credited > 0) {
                $wallet->balance = $newBal;
                $wallet->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $wallet->id,
                    'type' => WhatsappWalletTransaction::TYPE_TOPUP,
                    'amount' => $credited,
                    'balance_after' => $newBal,
                    'external_reference' => $reference !== '' ? $reference : null,
                    'meta' => [
                        'source' => 'mevonpay_funding',
                        'reported_amount' => $amount,
                        'pending_topup_id' => $pending->id,
                    ],
                ]);
            }

            $pending->update([
                'fulfilled_at' => now(),
                'amount_reported' => $amount,
                'amount_credited' => $credited,
                'mavon_reference' => $reference !== '' ? $reference : null,
            ]);

            if ($credited < $amount) {
                Log::warning('whatsapp.wallet.topup_partial_or_zero', [
                    'pending_id' => $pending->id,
                    'wallet_id' => $wallet->id,
                    'reported' => $amount,
                    'credited' => $credited,
                ]);
            }

            $handled = true;
        });

        return $handled;
    }

    private function computeCreditAmount(WhatsappWallet $wallet, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $check = $wallet->canCredit($amount);
        if ($check['ok']) {
            return round($amount, 2);
        }

        if ((int) $wallet->tier === WhatsappWallet::TIER_WHATSAPP_ONLY) {
            $room = max(0.0, $wallet->tier1MaxBalance() - (float) $wallet->balance);

            return round(min($amount, $room), 2);
        }

        return round($amount, 2);
    }
}
