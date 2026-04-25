<?php

namespace App\Services\Whatsapp;

use App\Events\PaymentApproved;
use App\Models\Business;
use App\Models\PartnerWalletSpend;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Merchant API (X-API-Key): wallet summary, ensure row, and settle after user confirms PIN on web (pay/start flow only).
 */
final class WhatsappWalletPartnerApiService
{
    public function getWalletSummary(string $phoneInput): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();

        return [
            'ok' => true,
            'phone_e164' => $e164,
            'wallet_id' => $wallet?->id,
            'balance' => $wallet ? (float) $wallet->balance : 0.0,
            'has_pin' => $wallet?->hasPin() ?? false,
            'tier' => $wallet ? (int) $wallet->tier : null,
            'status' => $wallet?->status,
        ];
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function ensureWallet(string $phoneInput): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $e164],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        return [
            'ok' => true,
            'data' => [
                'wallet_id' => $wallet->id,
                'phone_e164' => $wallet->phone_e164,
                'renter_id' => $wallet->renter_id,
            ],
        ];
    }

    /**
     * Settle wallet → merchant after the customer verified PIN on the web (internal; not a public PIN-less API).
     *
     * @param  array<string, mixed>  $metaExtra  Merged into wallet transaction meta
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, http_status?: int}
     */
    public function settlePartnerWalletDebit(
        Business $merchant,
        string $phoneInput,
        float $amount,
        string $idempotencyKey,
        string $orderReference,
        string $payerName,
        string $webhookUrl = '',
        array $metaExtra = []
    ): array {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $idempotencyKey = trim($idempotencyKey);
        if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 80) {
            return ['ok' => false, 'message' => 'idempotency_key must be 8–80 characters.'];
        }

        try {
            return DB::transaction(function () use ($e164, $amount, $idempotencyKey, $orderReference, $payerName, $merchant, $webhookUrl, $metaExtra) {
                $existing = PartnerWalletSpend::query()
                    ->where('business_id', $merchant->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === 'completed' && is_array($existing->response_payload)) {
                    return ['ok' => true, 'data' => $existing->response_payload + ['idempotent_replay' => true]];
                }
                if ($existing && $existing->status === 'processing') {
                    return ['ok' => false, 'message' => 'Duplicate in-flight request for this idempotency key. Retry shortly.', 'http_status' => 409];
                }

                PartnerWalletSpend::query()->create([
                    'business_id' => $merchant->id,
                    'idempotency_key' => $idempotencyKey,
                    'phone_e164' => $e164,
                    'amount' => $amount,
                    'status' => 'processing',
                ]);

                $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->lockForUpdate()->first();
                if (! $wallet) {
                    $this->failSpend($merchant->id, $idempotencyKey, 'Wallet not found for this number.');

                    return ['ok' => false, 'message' => 'Wallet not found. Top up via WhatsApp or Checkout first.'];
                }

                $wallet->resetDailyTransferIfNeeded();
                $debitCheck = $wallet->canDebit($amount);
                if (! $debitCheck['ok']) {
                    $this->failSpend($merchant->id, $idempotencyKey, $debitCheck['message'] ?? 'Cannot debit wallet.');

                    return ['ok' => false, 'message' => $debitCheck['message'] ?? 'Cannot debit wallet.'];
                }

                $newBal = round((float) $wallet->balance - $amount, 2);
                $wallet->balance = $newBal;
                $wallet->daily_transfer_total = round((float) $wallet->daily_transfer_total + $amount, 2);
                $wallet->daily_transfer_for_date = now()->toDateString();
                $wallet->save();

                $meta = array_merge(
                    [
                        'channel' => 'partner_wallet_api',
                        'idempotency_key' => $idempotencyKey,
                        'merchant_business_id' => $merchant->id,
                    ],
                    $metaExtra
                );

                $txn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $wallet->id,
                    'sender_name' => $wallet->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_PARTNER_MERCHANT_PAY,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'external_reference' => $orderReference,
                    'meta' => $meta,
                ]);

                $payment = Payment::query()->create([
                    'transaction_id' => 'WLT-PARTNER-'.Str::upper(Str::random(18)),
                    'amount' => $amount,
                    'payer_name' => $payerName,
                    'business_id' => $merchant->id,
                    'status' => Payment::STATUS_APPROVED,
                    'payment_source' => Payment::SOURCE_PARTNER_WALLET_API,
                    'webhook_url' => $webhookUrl !== '' ? $webhookUrl : '',
                    'matched_at' => now(),
                    'external_reference' => $orderReference,
                ]);

                $received = $merchant->incrementBalanceWithCharges($amount, $payment);

                $payload = [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'wallet_transaction_id' => $txn->id,
                    'wallet_balance_after' => $newBal,
                    'amount' => $amount,
                    'business_receives' => $received,
                    'merchant_business_id' => $merchant->id,
                    'external_reference' => $orderReference,
                ];

                PartnerWalletSpend::query()
                    ->where('business_id', $merchant->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->update([
                        'status' => 'completed',
                        'payment_id' => $payment->id,
                        'whatsapp_wallet_transaction_id' => $txn->id,
                        'response_payload' => $payload,
                    ]);

                event(new PaymentApproved($payment));

                return ['ok' => true, 'data' => $payload];
            });
        } catch (Throwable $e) {
            Log::error('whatsapp.wallet.partner_api: settle failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Partner debit failed. Try again or contact support.'];
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function verifyWalletPinAndUnlock(WhatsappWallet $wallet, string $pinDigits): array
    {
        if ($wallet->isPinLocked()) {
            return ['ok' => false, 'error' => 'Wallet PIN is locked. Try again later in WhatsApp.'];
        }

        if (! $wallet->pin_hash || ! Hash::check($pinDigits, (string) $wallet->pin_hash)) {
            $wallet->increment('pin_failed_attempts');
            $wallet->refresh();
            if ((int) $wallet->pin_failed_attempts >= 5) {
                $wallet->pin_locked_until = now()->addMinutes(15);
                $wallet->save();

                return ['ok' => false, 'error' => 'Too many wrong PIN attempts. Wallet PIN locked for 15 minutes.'];
            }

            return ['ok' => false, 'error' => 'Incorrect wallet PIN.'];
        }

        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        return ['ok' => true];
    }

    private function failSpend(int $businessId, string $idempotencyKey, string $message): void
    {
        PartnerWalletSpend::query()
            ->where('business_id', $businessId)
            ->where('idempotency_key', $idempotencyKey)
            ->update([
                'status' => 'failed',
                'response_payload' => ['error' => $message],
            ]);
    }
}
