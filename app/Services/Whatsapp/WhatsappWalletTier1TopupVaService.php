<?php

namespace App\Services\Whatsapp;

use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingTopup;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPayVirtualAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MevonPay wallet funding: Tier 1 temp VA (pending top-up + Payment row) and Tier 2 permanent VA (wallet.mevon_virtual_account_number).
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

        $pending = WhatsappWalletPendingTopup::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'account_number' => $va['account_number'],
            'account_name' => $va['account_name'] ?? null,
            'bank_name' => $va['bank_name'] ?? null,
            'bank_code' => $va['bank_code'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://localhost';
        }

        $transactionId = 'WAW'.strtoupper(str_replace('-', '', (string) Str::uuid()));
        $payment = Payment::query()->create([
            'transaction_id' => $transactionId,
            'amount' => 0,
            'payer_name' => strtolower(trim('wa wallet '.(string) $wallet->phone_e164)),
            'webhook_url' => $baseUrl.'/internal/whatsapp-wallet-topup',
            'account_number' => $va['account_number'],
            'business_id' => null,
            'status' => Payment::STATUS_PENDING,
            'payment_source' => Payment::SOURCE_WHATSAPP_WALLET,
            'expires_at' => $expiresAt,
            'email_data' => [
                'wa_topup' => true,
                'whatsapp_wallet_id' => $wallet->id,
                'whatsapp_pending_topup_id' => $pending->id,
            ],
        ]);

        $pending->update(['payment_id' => $payment->id]);

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
     * MevonPay sent funding.success with no/zero amount: attach metadata to the linked admin Payment row only.
     */
    public function tryLogZeroAmountWebhook(string $accountNumber, string $reference, array $payload): bool
    {
        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return false;
        }

        $pending = WhatsappWalletPendingTopup::query()
            ->where('account_number', $accountNumber)
            ->whereNull('fulfilled_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if ($pending && $pending->payment_id) {
            $payment = Payment::query()->find($pending->payment_id);
            if ($payment && $payment->isPending()) {
                $existing = is_array($payment->email_data) ? $payment->email_data : [];
                $merged = array_merge($existing, [
                    'wa_topup' => true,
                    'whatsapp_wallet_id' => $pending->whatsapp_wallet_id,
                    'whatsapp_pending_topup_id' => $pending->id,
                    'mevonpay_webhook_no_amount_at' => now()->toIso8601String(),
                    'mevonpay_last_reference' => $reference,
                    'mevonpay_sender' => (string) data_get($payload, 'data.sender', ''),
                    'mevonpay_bank_name' => (string) data_get($payload, 'data.bank_name', ''),
                ]);

                $payment->update([
                    'email_data' => $merged,
                    'external_reference' => $reference !== '' ? $reference : $payment->external_reference,
                ]);

                return true;
            }
        }

        $permanentWallet = WhatsappWallet::query()
            ->where('mevon_virtual_account_number', $accountNumber)
            ->where('tier', WhatsappWallet::TIER_RUBIES_VA)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if ($permanentWallet) {
            Log::info('whatsapp.wallet.mevon_webhook_no_amount_permanent_va', [
                'wallet_id' => $permanentWallet->id,
                'account_number' => $accountNumber,
                'reference' => $reference,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Match MevonPay funding.success webhook to a pending Tier 1 top-up and credit the wallet.
     *
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    public function tryFulfillFromWebhook(string $accountNumber, float $amount, string $reference, array $webhookMeta = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return false;
        }

        $handled = false;

        DB::transaction(function () use ($accountNumber, $amount, $reference, $webhookMeta, &$handled) {
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

            $this->syncLinkedWhatsappTopupPayment($pending, $amount, $credited, $reference, $webhookMeta);

            $handled = true;
        });

        return $handled;
    }

    /**
     * Tier 2: credit wallet by permanent Mevon VA when there is no merchant Payment and no Tier 1 pending top-up.
     *
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    public function tryFulfillPermanentVaFromWebhook(string $accountNumber, float $amount, string $reference, array $webhookMeta = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return false;
        }

        $handled = false;

        DB::transaction(function () use ($accountNumber, $amount, $reference, $webhookMeta, &$handled) {
            $wallet = WhatsappWallet::query()
                ->where('mevon_virtual_account_number', $accountNumber)
                ->where('tier', WhatsappWallet::TIER_RUBIES_VA)
                ->lockForUpdate()
                ->first();

            if (! $wallet || ! $wallet->isActive()) {
                return;
            }

            if ($reference !== '') {
                $already = WhatsappWalletTransaction::query()
                    ->where('whatsapp_wallet_id', $wallet->id)
                    ->where('external_reference', $reference)
                    ->lockForUpdate()
                    ->exists();
                if ($already) {
                    $handled = true;

                    return;
                }
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
                        'permanent_va' => true,
                    ],
                ]);
            }

            if ($credited < $amount) {
                Log::warning('whatsapp.wallet.permanent_va_topup_partial_or_zero', [
                    'wallet_id' => $wallet->id,
                    'reported' => $amount,
                    'credited' => $credited,
                ]);
            }

            $this->recordPermanentVaAdminPayment($wallet, $amount, $credited, $reference, $webhookMeta);

            $handled = true;
        });

        return $handled;
    }

    /**
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    private function syncLinkedWhatsappTopupPayment(
        WhatsappWalletPendingTopup $pending,
        float $reported,
        float $credited,
        string $reference,
        array $webhookMeta,
    ): void {
        if (! $pending->payment_id) {
            return;
        }

        $pay = Payment::query()->lockForUpdate()->find($pending->payment_id);
        if (! $pay) {
            return;
        }

        $existing = is_array($pay->email_data) ? $pay->email_data : [];
        $merged = array_merge($existing, [
            'wa_topup' => true,
            'whatsapp_wallet_id' => $pending->whatsapp_wallet_id,
            'whatsapp_pending_topup_id' => $pending->id,
            'mevonpay_reference' => $reference,
            'reported_amount' => $reported,
        ]);

        $sender = trim((string) ($webhookMeta['sender'] ?? ''));
        $bank = trim((string) ($webhookMeta['bank_name'] ?? ''));

        $update = [
            'status' => Payment::STATUS_APPROVED,
            'matched_at' => now(),
            'external_reference' => $reference !== '' ? $reference : $pay->external_reference,
            'amount' => $credited > 0 ? $credited : ($reported > 0 ? $reported : $pay->amount),
            'received_amount' => $credited,
            'is_mismatch' => $reported > 0 && $credited < $reported,
            'mismatch_reason' => ($reported > 0 && $credited < $reported)
                ? 'Partial or zero wallet credit (tier limit or rules).'
                : null,
            'email_data' => $merged,
        ];

        if ($sender !== '') {
            $update['payer_name'] = strtolower($sender);
        }
        if ($bank !== '') {
            $update['bank'] = $bank;
        }

        $pay->update($update);
    }

    /**
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    private function recordPermanentVaAdminPayment(
        WhatsappWallet $wallet,
        float $reported,
        float $credited,
        string $reference,
        array $webhookMeta,
    ): void {
        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://localhost';
        }

        $transactionId = 'WAW'.strtoupper(str_replace('-', '', (string) Str::uuid()));
        $sender = trim((string) ($webhookMeta['sender'] ?? ''));
        $bank = trim((string) ($webhookMeta['bank_name'] ?? ''));

        $payerName = $sender !== ''
            ? strtolower($sender)
            : strtolower(trim('wa wallet '.(string) $wallet->phone_e164));

        Payment::query()->create([
            'transaction_id' => $transactionId,
            'amount' => $credited > 0 ? $credited : ($reported > 0 ? $reported : 0),
            'payer_name' => $payerName,
            'bank' => $bank !== '' ? $bank : null,
            'webhook_url' => $baseUrl.'/internal/whatsapp-wallet-topup',
            'account_number' => $wallet->mevon_virtual_account_number,
            'business_id' => null,
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_WHATSAPP_WALLET,
            'matched_at' => now(),
            'expires_at' => null,
            'external_reference' => $reference !== '' ? $reference : null,
            'received_amount' => $credited,
            'is_mismatch' => $reported > 0 && $credited < $reported,
            'mismatch_reason' => ($reported > 0 && $credited < $reported)
                ? 'Partial or zero wallet credit (tier limit or rules).'
                : null,
            'email_data' => [
                'wa_topup' => true,
                'wa_permanent_va' => true,
                'whatsapp_wallet_id' => $wallet->id,
                'reported_amount' => $reported,
                'mevonpay_reference' => $reference,
            ],
        ]);
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
