<?php

namespace App\Services\Whatsapp;

use App\Events\PaymentApproved;
use App\Models\Business;
use App\Models\PartnerWalletSpend;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\ChargeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settle a pending checkout payment via WhatsApp wallet debit (linked Payment row).
 */
final class WhatsappCheckoutPayCodeSettlementService
{
    public function __construct(
        private ChargeService $charges,
        private WhatsappCheckoutPayCodeService $payCodes,
    ) {}

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, http_status?: int}
     */
    public function settle(
        Payment $payment,
        Business $merchant,
        WhatsappWallet $wallet,
        string $idempotencyKey,
        array $metaExtra = []
    ): array {
        $idempotencyKey = trim($idempotencyKey);
        if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 80) {
            return ['ok' => false, 'message' => 'Invalid idempotency key.'];
        }

        if (! WhatsappCheckoutPayCodePolicy::customerCountryAllowed((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Checkout Pay Code is not available for your country yet.'];
        }

        try {
            return DB::transaction(function () use ($payment, $merchant, $wallet, $idempotencyKey, $metaExtra) {
                $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
                if (! $payment || ! $payment->isPending()) {
                    return ['ok' => false, 'message' => 'This payment is no longer pending.'];
                }

                if (! $this->payCodes->codeIsActive($payment)) {
                    return ['ok' => false, 'message' => 'This pay code has expired.'];
                }

                $existing = PartnerWalletSpend::query()
                    ->where('business_id', $merchant->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === 'completed' && is_array($existing->response_payload)) {
                    return ['ok' => true, 'data' => $existing->response_payload + ['idempotent_replay' => true]];
                }
                if ($existing && $existing->status === 'processing') {
                    return ['ok' => false, 'message' => 'Duplicate in-flight request. Retry shortly.', 'http_status' => 409];
                }

                PartnerWalletSpend::query()->create([
                    'business_id' => $merchant->id,
                    'idempotency_key' => $idempotencyKey,
                    'phone_e164' => $wallet->phone_e164,
                    'amount' => (float) $payment->amount,
                    'status' => 'processing',
                ]);

                $website = $payment->business_website_id ? $payment->website()->first() : null;
                $chargeBreakdown = $this->charges->calculateCharges((float) $payment->amount, $website, $merchant);
                $debitAmount = (float) $chargeBreakdown['amount_to_pay'];

                $wallet = WhatsappWallet::query()->whereKey($wallet->id)->lockForUpdate()->first();
                if (! $wallet) {
                    $this->failSpend($merchant->id, $idempotencyKey, 'Wallet not found.');

                    return ['ok' => false, 'message' => 'Wallet not found.'];
                }

                $wallet->resetDailyTransferIfNeeded();
                $debitCheck = $wallet->canDebit($debitAmount);
                if (! ($debitCheck['ok'] ?? false)) {
                    $this->failSpend($merchant->id, $idempotencyKey, $debitCheck['message'] ?? 'Cannot debit wallet.');

                    return ['ok' => false, 'message' => $debitCheck['message'] ?? 'Insufficient wallet balance.'];
                }

                $newBal = round((float) $wallet->balance - $debitAmount, 2);
                $wallet->balance = $newBal;
                $wallet->daily_transfer_total = round((float) $wallet->daily_transfer_total + $debitAmount, 2);
                $wallet->daily_transfer_for_date = now()->toDateString();
                $wallet->save();

                $meta = array_merge([
                    'channel' => 'checkout_pay_code',
                    'idempotency_key' => $idempotencyKey,
                    'merchant_business_id' => $merchant->id,
                    'payment_id' => $payment->id,
                    'checkout_pay_code' => $payment->checkout_pay_code,
                ], $metaExtra);

                $txn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $wallet->id,
                    'sender_name' => $wallet->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_PARTNER_MERCHANT_PAY,
                    'amount' => $debitAmount,
                    'balance_after' => $newBal,
                    'external_reference' => $payment->transaction_id,
                    'meta' => $meta,
                ]);

                $emailData = is_array($payment->email_data) ? $payment->email_data : [];
                $emailData['skip_auto_match'] = true;
                $emailData['checkout_pay_code'] = $payment->checkout_pay_code;
                $payment->email_data = $emailData;
                $payment->payment_method_used = Payment::METHOD_WHATSAPP_WALLET;
                $payment->payment_source = Payment::SOURCE_WHATSAPP_WALLET;
                $payment->save();

                $payment->approve([
                    'amount' => $debitAmount,
                    'received_amount' => $debitAmount,
                    'payer_name' => $payment->payer_name ?: $wallet->normalizedSenderName(),
                ], false, $debitAmount);

                $received = $merchant->incrementBalanceWithCharges((float) $payment->amount, $payment->fresh(), $debitAmount);

                $this->payCodes->invalidateCode($payment->fresh());

                $payload = [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'wallet_transaction_id' => $txn->id,
                    'wallet_balance_after' => $newBal,
                    'amount' => $debitAmount,
                    'business_receives' => $received,
                    'merchant_business_id' => $merchant->id,
                    'external_reference' => $payment->transaction_id,
                    'payment_method_used' => Payment::METHOD_WHATSAPP_WALLET,
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

                event(new PaymentApproved($payment->fresh()));

                return ['ok' => true, 'data' => $payload];
            });
        } catch (Throwable $e) {
            Log::error('checkout_pay_code: settle failed', ['error' => $e->getMessage(), 'payment_id' => $payment->id]);

            return ['ok' => false, 'message' => 'Payment failed. Try again or contact support.'];
        }
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
