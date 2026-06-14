<?php

namespace App\Services\Whatsapp;

use App\Models\Business;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPartnerPayIntent;
use App\Services\ChargeService;
use Illuminate\Support\Str;

/**
 * Inbound WhatsApp: customer sends PAY {CODE} to claim a checkout payment.
 */
final class WhatsappCheckoutPayCodeHandler
{
    public function __construct(
        private EvolutionWhatsAppClient $whatsapp,
        private WhatsappCheckoutPayCodeService $payCodes,
        private ChargeService $charges,
    ) {}

    public function tryHandle(string $instance, string $phoneE164, string $text): bool
    {
        $code = $this->extractCode($text);
        if ($code === null) {
            return false;
        }

        if (! WhatsappCheckoutPayCodePolicy::customerCountryAllowed($phoneE164)) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                "Checkout Pay Code is not available for your country yet.\n\nSend *WALLET* for other wallet options."
            );

            return true;
        }

        $payment = $this->payCodes->findActivePaymentByCode($code);
        if (! $payment) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                "That pay code is invalid or has expired.\n\nAsk the merchant for a new checkout link."
            );

            return true;
        }

        $business = Business::query()->find($payment->business_id);
        if (! $business || ! $business->whatsapp_wallet_api_enabled) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                'This merchant does not accept WhatsApp Pay Code right now.'
            );

            return true;
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $phoneE164)->first();
        if (! $wallet) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                "You need a *Checkout wallet* first.\n\nSend *WALLET* to set up your wallet, then send *PAY {$code}* again."
            );

            return true;
        }

        if (! $wallet->hasPin()) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                "Set your wallet PIN first.\n\nSend *WALLET* → follow PIN setup, then send *PAY {$code}* again."
            );

            return true;
        }

        $website = $payment->website;
        $chargeBreakdown = $this->charges->calculateCharges((float) $payment->amount, $website, $business);
        $debitAmount = (float) $chargeBreakdown['amount_to_pay'];

        $wallet->resetDailyTransferIfNeeded();
        $debitCheck = $wallet->canDebit($debitAmount);
        if (! ($debitCheck['ok'] ?? false)) {
            $balFmt = WhatsappWalletMoneyFormatter::format((float) $wallet->balance, 'NGN');
            $needFmt = WhatsappWalletMoneyFormatter::format($debitAmount, 'NGN');
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                "Insufficient balance.\n\nYou need *{$needFmt}* but your balance is *{$balFmt}*.\n\nSend *WALLET* → *Top up* to add funds, then try *PAY {$code}* again."
            );

            return true;
        }

        $intent = $this->createOrRefreshIntent($payment, $business, $wallet, $phoneE164, $debitAmount);
        if (! $intent) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                'Could not start this payment. Try again in a moment.'
            );

            return true;
        }

        $ttlMin = max(5, min(120, (int) config('whatsapp.wallet.partner_pay_intent_ttl_minutes', 30)));
        $url = rtrim((string) config('app.url', ''), '/').'/wallet/partner-pay/'.$intent->confirm_token;
        $bizName = $business->name ?? 'Merchant';
        $amountFmt = WhatsappWalletMoneyFormatter::format($debitAmount, 'NGN');

        $message =
            "🔔 *Checkout — pay {$bizName}*\n\n".
            "Amount: *{$amountFmt}*\n".
            "Ref: `{$payment->transaction_id}`\n\n".
            "Open the secure link below and enter your *4-digit wallet PIN* only on that page — *never* in this chat.\n\n".
            $url."\n\n".
            "_Link expires in {$ttlMin} min._";

        $sent = $this->whatsapp->sendText($instance, $phoneE164, $message);
        if (! $sent) {
            $this->whatsapp->sendText(
                $instance,
                $phoneE164,
                'We could not send your payment link. Try *PAY '.$code.'* again shortly.'
            );
        }

        return true;
    }

    private function extractCode(string $text): ?string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
        if (preg_match('/^PAY\s+([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})$/', $normalized, $m)) {
            return $m[1];
        }

        return null;
    }

    private function createOrRefreshIntent(
        Payment $payment,
        Business $business,
        WhatsappWallet $wallet,
        string $phoneE164,
        float $debitAmount
    ): ?WhatsappWalletPartnerPayIntent {
        $ttlMin = max(5, min(120, (int) config('whatsapp.wallet.partner_pay_intent_ttl_minutes', 30)));
        $expiresAt = now()->addMinutes($ttlMin);
        $idempotencyKey = 'checkout-pay-code-'.$payment->id;
        $webhookUrl = trim((string) ($payment->webhook_url ?? ''));
        $orderSummary = 'Checkout payment '.$payment->transaction_id;
        $emailData = is_array($payment->email_data) ? $payment->email_data : [];
        if (! empty($emailData['service'])) {
            $orderSummary = (string) $emailData['service'].' — '.$payment->transaction_id;
        }

        $existing = WhatsappWalletPartnerPayIntent::query()
            ->where('payment_id', $payment->id)
            ->where('status', WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing && $existing->phone_e164 !== $phoneE164) {
            return null;
        }

        if ($existing) {
            $existing->update([
                'phone_e164' => $phoneE164,
                'amount' => $debitAmount,
                'expires_at' => $expiresAt,
            ]);

            return $existing->fresh();
        }

        return WhatsappWalletPartnerPayIntent::query()->create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'confirm_token' => Str::lower(Str::random(48)),
            'phone_e164' => $phoneE164,
            'amount' => $debitAmount,
            'order_reference' => $payment->transaction_id,
            'order_summary' => $orderSummary,
            'payer_name' => $payment->payer_name ?: $wallet->normalizedSenderName(),
            'webhook_url' => $webhookUrl,
            'client_idempotency_key' => $idempotencyKey,
            'status' => WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN,
            'expires_at' => $expiresAt,
        ]);
    }
}
