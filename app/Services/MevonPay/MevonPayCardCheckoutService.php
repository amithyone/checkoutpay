<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Log;
use RuntimeException;

final class MevonPayCardCheckoutService
{
    public function __construct(
        private readonly MevonPayHttpClient $http
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * Create a Mevon/Paga card checkout session.
     *
     * @return array{payment_reference: string, checkout_url: string, raw: mixed}
     */
    public function createCheckout(float $amount, string $email, ?string $phone = null, string $currency = 'NGN'): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('MevonPay card checkout is not configured.');
        }

        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid customer email is required for card checkout.');
        }

        if ($amount < 0.01) {
            throw new \InvalidArgumentException('Amount must be at least 0.01 for card checkout.');
        }

        $path = (string) config('services.mevonpay.card_checkout_path', '/V1/card_checkout');
        $auth = strtolower(trim((string) config('services.mevonpay.card_checkout_auth', 'raw')));
        if (! in_array($auth, ['raw', 'bearer'], true)) {
            $auth = 'raw';
        }

        $payload = array_filter([
            'action' => 'checkout',
            'amount' => round($amount, 2),
            'email' => $email,
            'phone' => $phone !== null && trim($phone) !== '' ? trim($phone) : null,
            'currency' => strtoupper(trim($currency)) ?: 'NGN',
        ], static fn ($v) => $v !== null);

        $result = $this->http->postJson($path, $payload, $auth);

        if (! ($result['ok'] ?? false)) {
            Log::warning('mevonpay.card_checkout_failed', [
                'message' => $result['message'] ?? null,
                'http_status' => $result['http_status'] ?? null,
            ]);

            throw new RuntimeException(
                'MevonPay card checkout failed: '.((string) ($result['message'] ?? 'Unknown error'))
            );
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $checkoutUrl = trim((string) ($data['checkout_url'] ?? ''));
        $paymentReference = trim((string) ($data['payment_reference'] ?? ''));

        if ($checkoutUrl === '' || $paymentReference === '') {
            Log::warning('mevonpay.card_checkout_incomplete', [
                'data_keys' => array_keys($data),
            ]);

            throw new RuntimeException('MevonPay card checkout response missing checkout_url or payment_reference.');
        }

        return [
            'payment_reference' => $paymentReference,
            'checkout_url' => $checkoutUrl,
            'raw' => $result['raw'] ?? $result,
        ];
    }
}
