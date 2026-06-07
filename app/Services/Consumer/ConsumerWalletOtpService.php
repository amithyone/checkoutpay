<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConsumerWalletOtpService
{
    private const CACHE_OTP = 'consumer_wallet_otp:';

    private const CACHE_ATTEMPTS = 'consumer_wallet_otp_attempts:';

    private const CACHE_UNUSED_SENDS = 'consumer_wallet_otp_unused_sends:';

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

    private function unusedSendsKey(string $e164): string
    {
        return self::CACHE_UNUSED_SENDS.hash('sha256', $e164);
    }

    public function isOtpBlocked(string $e164): bool
    {
        return (int) Cache::get($this->unusedSendsKey($e164), 0) >= $this->maxUnusedOtpSends();
    }

    public function clearUnusedOtpSends(string $e164): void
    {
        Cache::forget($this->unusedSendsKey($e164));
    }

    private function maxUnusedOtpSends(): int
    {
        return max(2, min(5, (int) config('consumer_wallet.otp_max_unused_sends', 3)));
    }

    private function recordUnusedOtpSend(string $e164): void
    {
        $key = $this->unusedSendsKey($e164);
        $n = (int) Cache::get($key, 0);
        Cache::put($key, $n + 1, now()->addHours(24));
    }

    /**
     * @return array{ok: bool, message: string, whatsapp?: bool, email?: bool, email_masked?: string|null}
     */
    public function otpOptions(string $phoneInput): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();
        $email = $wallet?->resolveOtpEmail();
        $emailEligible = $wallet?->isTier2() === true && $email !== null;
        $otpBlocked = $this->isOtpBlocked($e164);

        return [
            'ok' => true,
            'message' => 'OK',
            'whatsapp' => ! $otpBlocked,
            'email' => $emailEligible && ! $otpBlocked,
            'email_masked' => $emailEligible ? $this->maskEmail($email) : null,
            'otp_blocked' => $otpBlocked,
            'has_pin' => $wallet?->hasPin() ?? false,
        ];
    }

    /**
     * @return array{ok: bool, message: string, channel?: string}
     */
    public function requestOtp(string $phoneInput, string $channel = 'whatsapp'): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['whatsapp', 'email'], true)) {
            return ['ok' => false, 'message' => 'Invalid delivery channel.'];
        }

        if ($this->isOtpBlocked($e164)) {
            return [
                'ok' => false,
                'message' => 'Too many unused login codes. Sign in with your wallet PIN or use Forgot PIN.',
                'otp_blocked' => true,
            ];
        }

        $ttl = max(60, (int) config('consumer_wallet.otp_ttl_seconds', 600));
        $len = max(4, min(8, (int) config('consumer_wallet.otp_length', 6)));
        $maxDigits = 10 ** $len - 1;
        $code = str_pad((string) random_int(0, $maxDigits), $len, '0', STR_PAD_LEFT);

        Cache::put($this->otpKey($e164), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], $ttl);

        if ($channel === 'email') {
            $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();
            $email = $wallet?->resolveOtpEmail();
            if (! $wallet?->isTier2() || $email === null) {
                Cache::forget($this->otpKey($e164));

                return ['ok' => false, 'message' => 'Email OTP is only available for verified Tier 2 wallets with a KYC email.'];
            }

            try {
                $brand = (string) config('whatsapp.bot_brand_name', 'Checkout');
                Mail::send('emails.login-otp-code', [
                    'code' => $code,
                    'ttlMinutes' => max(1, (int) round($ttl / 60)),
                ], function ($message) use ($email, $brand) {
                    $message->to($email)->subject("Your {$brand} app login code");
                });
            } catch (\Throwable $e) {
                Cache::forget($this->otpKey($e164));
                Log::warning('consumer_wallet.otp: email send failed', ['error' => $e->getMessage()]);

                return ['ok' => false, 'message' => 'Could not send OTP email. Try WhatsApp instead.'];
            }

            Cache::forget($this->attemptsKey($e164));
            $this->recordUnusedOtpSend($e164);

            return [
                'ok' => true,
                'message' => 'OTP sent to your KYC email.',
                'channel' => 'email',
            ];
        }

        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            Log::warning('consumer_wallet.otp: no evolution instance');
            Cache::forget($this->otpKey($e164));

            return ['ok' => false, 'message' => 'OTP delivery is not configured.'];
        }

        $brand = (string) config('whatsapp.bot_brand_name', 'Checkout');
        $text = "*{$brand}* app login\n\nYour code: *{$code}*\n\nIt expires in ".round($ttl / 60).' minutes. Do not share this code.';

        $sent = $this->whatsapp->sendText($instance, $e164, $text);
        if (! $sent) {
            Cache::forget($this->otpKey($e164));

            return ['ok' => false, 'message' => 'Could not send OTP. Try again later.'];
        }

        Cache::forget($this->attemptsKey($e164));
        $this->recordUnusedOtpSend($e164);

        return ['ok' => true, 'message' => 'OTP sent to your WhatsApp.', 'channel' => 'whatsapp'];
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $visible = substr($local, 0, 1);
        $maskedLocal = strlen($local) <= 2 ? $visible.'*' : $visible.str_repeat('*', min(6, strlen($local) - 1));

        return $maskedLocal.'@'.$domain;
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
            return ['ok' => false, 'message' => 'Too many wrong codes. Tap back and send a new code to try again.'];
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
        $this->clearUnusedOtpSends($e164);

        return ['ok' => true, 'message' => 'Verified.', 'phone_e164' => $e164];
    }
}
