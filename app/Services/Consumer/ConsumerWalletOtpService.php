<?php

namespace App\Services\Consumer;

use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConsumerWalletOtpService
{
    private const CACHE_OTP = 'consumer_wallet_otp:';

    private const CACHE_ATTEMPTS = 'consumer_wallet_otp_attempts:';

    public function __construct(
        private EvolutionWhatsAppClient $whatsapp,
    ) {}

    private function otpKey(string $e164): string
    {
        return self::CACHE_OTP.hash('sha256', $e164);
    }

    private function attemptsKey(string $e164): string
    {
        return self::CACHE_ATTEMPTS.hash('sha256', $e164);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function requestOtp(string $phoneInput): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $ttl = max(60, (int) config('consumer_wallet.otp_ttl_seconds', 600));
        $len = max(4, min(8, (int) config('consumer_wallet.otp_length', 6)));
        $maxDigits = 10 ** $len - 1;
        $code = str_pad((string) random_int(0, $maxDigits), $len, '0', STR_PAD_LEFT);

        Cache::put($this->otpKey($e164), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], $ttl);

        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            Log::warning('consumer_wallet.otp: no evolution instance');

            return ['ok' => false, 'message' => 'OTP delivery is not configured.'];
        }

        $brand = (string) config('whatsapp.bot_brand_name', 'Checkout');
        $text = "*{$brand}* app login\n\nYour code: *{$code}*\n\nIt expires in ".round($ttl / 60).' minutes. Do not share this code.';

        $sent = $this->whatsapp->sendText($instance, $e164, $text);
        if (! $sent) {
            Cache::forget($this->otpKey($e164));

            return ['ok' => false, 'message' => 'Could not send OTP. Try again later.'];
        }

        return ['ok' => true, 'message' => 'OTP sent to your WhatsApp.'];
    }

    /**
     * @return array{ok: bool, message: string, phone_e164?: string}
     */
    public function verifyOtp(string $phoneInput, string $code): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $attemptsKey = $this->attemptsKey($e164);
        $attempts = (int) Cache::get($attemptsKey, 0);
        $maxAttempts = max(3, (int) config('consumer_wallet.otp_max_attempts', 5));
        if ($attempts >= $maxAttempts) {
            return ['ok' => false, 'message' => 'Too many attempts. Request a new OTP.'];
        }

        $payload = Cache::get($this->otpKey($e164));
        if (! is_array($payload) || empty($payload['code_hash'])) {
            Cache::put($attemptsKey, $attempts + 1, 3600);

            return ['ok' => false, 'message' => 'Invalid or expired OTP.'];
        }

        $code = trim($code);
        if ($code === '' || ! hash_equals((string) $payload['code_hash'], hash('sha256', $code))) {
            Cache::put($attemptsKey, $attempts + 1, 3600);

            return ['ok' => false, 'message' => 'Invalid OTP.'];
        }

        Cache::forget($this->otpKey($e164));
        Cache::forget($attemptsKey);

        return ['ok' => true, 'message' => 'Verified.', 'phone_e164' => $e164];
    }
}
