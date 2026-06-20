<?php

namespace App\Services\Whatsapp;

use App\Models\Business;
use App\Models\Payment;
use App\Models\WhatsappWalletPartnerPayIntent;
use App\Services\ChargeService;
use App\Support\WhatsappWalletMarketing;
use Illuminate\Support\Str;

/**
 * Generate and expose Checkout WhatsApp Pay Code on payment-request responses.
 */
final class WhatsappCheckoutPayCodeService
{
    private const CODE_LENGTH = 6;

    private const CODE_CHARS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private ChargeService $charges,
    ) {}

    public function businessMayOfferPayCode(Business $business, bool $includeWhatsappPay = true): bool
    {
        if (! $includeWhatsappPay) {
            return false;
        }

        return (bool) $business->whatsapp_wallet_api_enabled
            && WhatsappCheckoutPayCodePolicy::isGloballyEnabled();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attachToPayment(Payment $payment, Business $business, bool $includeWhatsappPay = true): ?array
    {
        if (! $this->businessMayOfferPayCode($business, $includeWhatsappPay)) {
            return null;
        }

        $ttlMin = max(5, min(120, (int) config('whatsapp.wallet.partner_pay_intent_ttl_minutes', 30)));
        $expiresAt = now()->addMinutes($ttlMin);

        $code = $this->generateUniqueCode();
        $payment->update([
            'checkout_pay_code' => $code,
            'checkout_pay_code_expires_at' => $expiresAt,
        ]);

        return $this->buildPayload($payment->fresh(), $business);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildPayload(?Payment $payment, Business $business): ?array
    {
        if (! $payment || ! $payment->checkout_pay_code || ! $this->codeIsActive($payment)) {
            return null;
        }

        $website = $payment->website;
        $chargeBreakdown = $this->charges->calculateCharges((float) $payment->amount, $website, $business);
        $code = (string) $payment->checkout_pay_code;
        $message = 'PAY '.$code;

        return [
            'code' => $code,
            'message' => $message,
            'wa_link' => $this->buildWaLink($message),
            'expires_at' => $payment->checkout_pay_code_expires_at?->toISOString(),
            'amount' => (float) $chargeBreakdown['amount_to_pay'],
            'enabled_countries' => WhatsappCheckoutPayCodePolicy::enabledCountries(),
            'status' => $this->whatsappPayStatus($payment),
            'instructions' => 'Send the message on WhatsApp, then enter your wallet PIN on the secure link.',
        ];
    }

    public function whatsappPayStatus(Payment $payment): string
    {
        if ($payment->payment_method_used === Payment::METHOD_WHATSAPP_WALLET) {
            return 'completed';
        }

        if ($payment->isApproved()) {
            return 'expired';
        }

        if (! $this->codeIsActive($payment)) {
            return 'expired';
        }

        $hasPendingIntent = WhatsappWalletPartnerPayIntent::query()
            ->where('payment_id', $payment->id)
            ->where('status', WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN)
            ->where('expires_at', '>', now())
            ->exists();

        return $hasPendingIntent ? 'claimed' : 'available';
    }

    public function codeIsActive(Payment $payment): bool
    {
        if ($payment->checkout_pay_code === null || $payment->checkout_pay_code === '') {
            return false;
        }

        if (! $payment->isPending()) {
            return false;
        }

        if ($payment->checkout_pay_code_expires_at === null) {
            return false;
        }

        return $payment->checkout_pay_code_expires_at->isFuture();
    }

    public function findActivePaymentByCode(string $code): ?Payment
    {
        $code = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
        if (! preg_match('/^[A-Z0-9]{5,6}$/', $code)) {
            return null;
        }

        $payment = Payment::query()
            ->where('checkout_pay_code', $code)
            ->where('status', Payment::STATUS_PENDING)
            ->where('checkout_pay_code_expires_at', '>', now())
            ->first();

        if (! $payment || ! $this->codeIsActive($payment)) {
            return null;
        }

        return $payment;
    }

    public function invalidateCode(Payment $payment): void
    {
        WhatsappWalletPartnerPayIntent::query()
            ->where('payment_id', $payment->id)
            ->where('status', WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN)
            ->update([
                'status' => WhatsappWalletPartnerPayIntent::STATUS_EXPIRED,
                'failure_reason' => 'Payment completed via another method.',
            ]);

        $payment->update([
            'checkout_pay_code' => null,
            'checkout_pay_code_expires_at' => null,
        ]);
    }

    public function buildWaLink(string $message): ?string
    {
        $contact = WhatsappWalletMarketing::contactUrl();
        if ($contact === null || $contact === '') {
            return null;
        }

        if (preg_match('#wa\.me/(\d+)#i', $contact, $m)) {
            $digits = $m[1];
        } elseif (preg_match('/(\d{10,15})/', $contact, $m)) {
            $digits = $m[1];
        } else {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = '';
            $max = strlen(self::CODE_CHARS) - 1;
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARS[random_int(0, $max)];
            }

            $exists = Payment::query()
                ->where('checkout_pay_code', $code)
                ->where('status', Payment::STATUS_PENDING)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return strtoupper(Str::random(self::CODE_LENGTH));
    }
}
