<?php

namespace App\Services\Whatsapp;

use App\Models\Business;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPartnerPayIntent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Partner pay: merchant sends order summary → Checkout WhatsApps user → user confirms PIN on web → debit + webhook.
 */
final class WhatsappWalletPartnerPayIntentService
{
    public function __construct(
        private EvolutionWhatsAppClient $whatsapp,
        private WhatsappWalletPartnerApiService $partnerApi
    ) {}

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, http_status?: int}
     */
    public function start(
        Business $business,
        string $phoneInput,
        float $amount,
        string $orderReference,
        string $orderSummary,
        string $payerName,
        string $webhookUrl,
        string $clientIdempotencyKey
    ): array {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $orderReference = trim($orderReference);
        if ($orderReference === '' || strlen($orderReference) > 120) {
            return ['ok' => false, 'message' => 'order_reference is required (max 120 characters).'];
        }

        $orderSummary = trim($orderSummary);
        if ($orderSummary === '' || strlen($orderSummary) > 8000) {
            return ['ok' => false, 'message' => 'order_summary is required (what the customer is paying for).'];
        }

        $clientIdempotencyKey = trim($clientIdempotencyKey);
        if (strlen($clientIdempotencyKey) < 8 || strlen($clientIdempotencyKey) > 80) {
            return ['ok' => false, 'message' => 'idempotency_key must be 8–80 characters.'];
        }

        $webhookUrl = trim($webhookUrl);
        if ($webhookUrl === '' || strlen($webhookUrl) > 500) {
            return ['ok' => false, 'message' => 'webhook_url is required for partner wallet pay (max 500 characters).'];
        }

        if (! filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'webhook_url must be a valid URL.'];
        }

        if (! $business->incomingPartnerWebhookUrlIsAllowed($webhookUrl)) {
            return ['ok' => false, 'message' => 'webhook_url must match your business or approved website webhook in Checkout.'];
        }

        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $ttlMin = max(5, min(120, (int) config('whatsapp.wallet.partner_pay_intent_ttl_minutes', 30)));
        $expiresAt = now()->addMinutes($ttlMin);

        $existing = WhatsappWalletPartnerPayIntent::query()
            ->where('business_id', $business->id)
            ->where('client_idempotency_key', $clientIdempotencyKey)
            ->first();

        if ($existing && $existing->status === WhatsappWalletPartnerPayIntent::STATUS_COMPLETED) {
            return [
                'ok' => true,
                'data' => [
                    'status' => 'already_completed',
                    'payment_id' => $existing->payment_id,
                    'transaction_id' => $existing->payment?->transaction_id,
                ],
            ];
        }

        if ($existing && $existing->isPending()) {
            return [
                'ok' => true,
                'data' => $this->responsePayloadForIntent($existing, $ttlMin),
            ];
        }

        $token = Str::lower(Str::random(48));

        if ($existing && in_array($existing->status, [
            WhatsappWalletPartnerPayIntent::STATUS_FAILED,
            WhatsappWalletPartnerPayIntent::STATUS_EXPIRED,
        ], true)) {
            $existing->update([
                'confirm_token' => $token,
                'phone_e164' => $e164,
                'amount' => $amount,
                'order_reference' => $orderReference,
                'order_summary' => $orderSummary,
                'payer_name' => $payerName,
                'webhook_url' => $webhookUrl,
                'status' => WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN,
                'payment_id' => null,
                'failure_reason' => null,
                'expires_at' => $expiresAt,
            ]);
            $intent = $existing->fresh();
        } else {
            $intent = WhatsappWalletPartnerPayIntent::query()->create([
                'business_id' => $business->id,
                'confirm_token' => $token,
                'phone_e164' => $e164,
                'amount' => $amount,
                'order_reference' => $orderReference,
                'order_summary' => $orderSummary,
                'payer_name' => $payerName,
                'webhook_url' => $webhookUrl,
                'client_idempotency_key' => $clientIdempotencyKey,
                'status' => WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN,
                'expires_at' => $expiresAt,
            ]);
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();
        if (! $wallet) {
            $intent->update([
                'status' => WhatsappWalletPartnerPayIntent::STATUS_FAILED,
                'failure_reason' => 'Wallet not found for this number.',
            ]);

            return ['ok' => false, 'message' => 'Wallet not found for this number.'];
        }

        if (! $wallet->hasPin()) {
            $intent->update([
                'status' => WhatsappWalletPartnerPayIntent::STATUS_FAILED,
                'failure_reason' => 'Wallet PIN not set.',
            ]);

            return ['ok' => false, 'message' => 'Customer must set a wallet PIN in WhatsApp (*WALLET*) before paying with this method.'];
        }

        $url = rtrim((string) config('app.url', ''), '/').'/wallet/partner-pay/'.$intent->confirm_token;
        $summaryShort = Str::limit(preg_replace('/\s+/', ' ', $orderSummary), 400, '…');
        $bizName = $business->name ?? 'Merchant';

        $message =
            "🔔 *Checkout — pay {$bizName}*\n\n".
            "You are about to pay *₦".number_format((float) $amount, 2)."*\n\n".
            "*Order:*\n{$summaryShort}\n\n".
            "Open the secure link below and enter your *4-digit wallet PIN* only on that page — *never* in this chat.\n\n".
            $url."\n\n".
            "_Link expires in {$ttlMin} min._";

        $instance = WhatsappEvolutionConfigResolver::defaultInstance();
        $sent = $this->whatsapp->sendText($instance, $e164, $message);
        if (! $sent) {
            Log::warning('partner_pay: WhatsApp notify failed', ['intent_id' => $intent->id]);
            $intent->update([
                'status' => WhatsappWalletPartnerPayIntent::STATUS_FAILED,
                'failure_reason' => 'Could not send WhatsApp message.',
            ]);

            return ['ok' => false, 'message' => 'Could not send WhatsApp to the customer. Check Evolution configuration.', 'http_status' => 502];
        }

        return [
            'ok' => true,
            'data' => $this->responsePayloadForIntent($intent->fresh(), $ttlMin),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function completeWithPin(string $token, string $pinDigits): array
    {
        $token = trim($token);
        if (strlen($token) < 32) {
            return ['ok' => false, 'message' => 'Invalid link.'];
        }

        $intent = WhatsappWalletPartnerPayIntent::query()
            ->where('confirm_token', $token)
            ->first();

        if (! $intent) {
            return ['ok' => false, 'message' => 'This link is invalid or has expired.'];
        }

        if ($intent->status === WhatsappWalletPartnerPayIntent::STATUS_COMPLETED) {
            return ['ok' => false, 'message' => 'This payment was already completed.'];
        }

        if ($intent->status !== WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN || ! $intent->isPending()) {
            return ['ok' => false, 'message' => 'This link has expired. Start payment again from the app.'];
        }

        $business = $intent->business;
        if (! $business || ! $business->whatsapp_wallet_api_enabled) {
            return ['ok' => false, 'message' => 'This payment is no longer available.'];
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $intent->phone_e164)->first();
        if (! $wallet) {
            return ['ok' => false, 'message' => 'Wallet not found.'];
        }

        $pinDigits = preg_replace('/\D/', '', $pinDigits) ?? '';
        if (strlen($pinDigits) !== 4) {
            return ['ok' => false, 'message' => 'Enter a valid 4-digit PIN.'];
        }

        $pinResult = $this->partnerApi->verifyWalletPinAndUnlock($wallet, $pinDigits);
        if (! ($pinResult['ok'] ?? false)) {
            return ['ok' => false, 'message' => $pinResult['error'] ?? 'Incorrect PIN.'];
        }

        $idem = hash('sha256', 'partner-pay|'.$intent->business_id.'|'.$intent->id.'|'.$intent->client_idempotency_key);

        $settle = $this->partnerApi->settlePartnerWalletDebit(
            $business,
            $intent->phone_e164,
            (float) $intent->amount,
            $idem,
            $intent->order_reference,
            $intent->payer_name,
            (string) $intent->webhook_url,
            [
                'partner_pay_intent_id' => $intent->id,
                'channel' => 'partner_wallet_pay_web_pin',
            ]
        );

        if (! ($settle['ok'] ?? false)) {
            $intent->update([
                'status' => WhatsappWalletPartnerPayIntent::STATUS_FAILED,
                'failure_reason' => (string) ($settle['message'] ?? 'Debit failed'),
            ]);

            return ['ok' => false, 'message' => (string) ($settle['message'] ?? 'Payment failed.')];
        }

        $data = $settle['data'] ?? [];
        $paymentId = isset($data['payment_id']) ? (int) $data['payment_id'] : null;

        $intent->update([
            'status' => WhatsappWalletPartnerPayIntent::STATUS_COMPLETED,
            'payment_id' => $paymentId,
            'failure_reason' => null,
        ]);

        $this->notifyPartnerPaySuccessWhatsApp($intent, $business, $wallet, $data);

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Customer confirmation: send WhatsApp receipt + new balance (non-fatal if Evolution fails).
     */
    private function notifyPartnerPaySuccessWhatsApp(
        WhatsappWalletPartnerPayIntent $intent,
        Business $business,
        WhatsappWallet $wallet,
        array $settleData
    ): void {
        $cur = 'NGN';
        $newBal = (float) ($settleData['wallet_balance_after'] ?? $wallet->fresh()->balance);
        $bizName = $business->name ?? 'Merchant';
        $amountFmt = WhatsappWalletMoneyFormatter::format((float) $intent->amount, $cur);
        $balFmt = WhatsappWalletMoneyFormatter::format($newBal, $cur);
        $ref = (string) ($intent->order_reference ?? '');
        $refLine = $ref !== '' ? "Ref: `{$ref}`\n" : '';

        $message =
            "✅ *Payment successful*\n\n".
            "You paid {$amountFmt} to *{$bizName}*.\n".
            $refLine."\n".
            "New wallet balance: *{$balFmt}*\n\n".
            '_Send *WALLET* anytime for your balance._';

        try {
            $instance = WhatsappEvolutionConfigResolver::defaultInstance();
            $sent = $this->whatsapp->sendText($instance, $intent->phone_e164, $message);
            if (! $sent) {
                Log::warning('partner_pay: post-success WhatsApp failed', ['intent_id' => $intent->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('partner_pay: post-success WhatsApp exception', [
                'intent_id' => $intent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function describeForWeb(string $token): array
    {
        $intent = WhatsappWalletPartnerPayIntent::query()
            ->with('business')
            ->where('confirm_token', trim($token))
            ->first();

        if (! $intent || ! $intent->isPending()) {
            return ['ok' => false, 'message' => 'This link has expired or is invalid.'];
        }

        return [
            'ok' => true,
            'data' => [
                'business_name' => $intent->business?->name ?? 'Merchant',
                'amount' => (float) $intent->amount,
                'order_summary' => $intent->order_summary,
                'order_reference' => $intent->order_reference,
                'payer_name' => $intent->payer_name,
            ],
        ];
    }

    private function responsePayloadForIntent(WhatsappWalletPartnerPayIntent $intent, int $ttlMin): array
    {
        $base = rtrim((string) config('app.url', ''), '/');

        return [
            'pay_intent_id' => $intent->id,
            'status' => $intent->status,
            'confirm_url' => $base.'/wallet/partner-pay/'.$intent->confirm_token,
            'expires_at' => $intent->expires_at?->toISOString(),
            'expires_in_minutes' => $ttlMin,
            'order_reference' => $intent->order_reference,
        ];
    }
}
