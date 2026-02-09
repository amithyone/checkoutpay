<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    protected string $secretKey;

    protected bool $enabled;

    protected string $version;

    protected float $scoreThreshold;

    public function __construct()
    {
        $this->secretKey = config('services.recaptcha.secret_key', '');
        $this->enabled = config('services.recaptcha.enabled', true);
        $this->version = config('services.recaptcha.version', 'v3');
        $this->scoreThreshold = config('services.recaptcha.score_threshold', 0.5);
    }

    /**
     * Verify reCAPTCHA response token with Google.
     * v2: success/fail only. v3: also requires score >= score_threshold.
     */
    public function verify(string $response, ?string $remoteIp = null): bool
    {
        if (!$this->enabled || empty($this->secretKey)) {
            return true; // Skip verification when not configured
        }

        if (empty($response)) {
            Log::warning('RecaptchaService: Empty response token');
            return false;
        }

        $payload = [
            'secret' => $this->secretKey,
            'response' => $response,
        ];
        if ($remoteIp) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $result = Http::asForm()
                ->timeout(10)
                ->post('https://www.google.com/recaptcha/api/siteverify', $payload);

            $data = $result->json();
            $success = ($data['success'] ?? false) === true;

            if (!$success) {
                Log::info('RecaptchaService: Verification failed', [
                    'error_codes' => $data['error-codes'] ?? [],
                ]);
                return false;
            }

            // v3: require minimum score (0.0 = bot, 1.0 = likely human)
            if ($this->version === 'v3') {
                $score = (float) ($data['score'] ?? 0);
                if ($score < $this->scoreThreshold) {
                    Log::info('RecaptchaService: v3 score below threshold', [
                        'score' => $score,
                        'threshold' => $this->scoreThreshold,
                    ]);
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('RecaptchaService: Verification error', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->secretKey);
    }
}
