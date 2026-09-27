<?php

namespace App\Services\Checkface;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bootstrap a first-party CheckFace account + API key when checkout has none configured.
 *
 * Allowed emails: @check-outnow.com / @check-outpay.com (enforced by CheckFace).
 */
final class CheckfaceProvisionService
{
    /**
     * @return array{ok: bool, message: string, api_key?: string, account_id?: int, email?: string, created?: bool, persisted?: bool}
     */
    public function provision(?string $email = null, ?string $password = null, bool $persist = true): array
    {
        $base = rtrim((string) config('checkface.base_url'), '/');
        if ($base === '') {
            return ['ok' => false, 'message' => 'CHECKFACE_BASE_URL is not set.'];
        }

        $email = strtolower(trim($email ?: (string) config('checkface.provision_email')));
        $password = (string) ($password ?: config('checkface.provision_password'));
        if ($password === '') {
            $password = Str::password(24);
        }

        if ($email === '' || ! str_contains($email, '@')) {
            return ['ok' => false, 'message' => 'Set CHECKFACE_PROVISION_EMAIL (must be @check-outnow.com or @check-outpay.com).'];
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('checkface.timeout_seconds', 45))
                ->post($base.'/v1/partner/register', [
                    'name' => (string) config('checkface.provision_name', 'CheckoutNow Wallet'),
                    'company' => (string) config('checkface.provision_company', 'CheckoutNow'),
                    'email' => $email,
                    'password' => $password,
                    'key_label' => (string) config('checkface.provision_key_label', 'CheckoutNow Wallet'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('checkface.provision_failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not reach CheckFace: '.$e->getMessage()];
        }

        $data = $response->json();
        if (! is_array($data)) {
            $data = [];
        }

        if (! $response->successful() || empty($data['api_key'])) {
            $detail = is_string($data['detail'] ?? null) ? $data['detail'] : 'Partner registration failed.';

            return ['ok' => false, 'message' => $detail, 'email' => $email];
        }

        $apiKey = (string) $data['api_key'];
        $persisted = false;
        if ($persist) {
            $persisted = $this->persistApiToken($apiKey);
            config(['checkface.api_token' => $apiKey]);
        }

        return [
            'ok' => true,
            'message' => $persisted
                ? 'CheckFace account ready; API key saved to secrets file.'
                : 'CheckFace account ready; copy the API key into CHECKFACE_API_TOKEN.',
            'api_key' => $apiKey,
            'account_id' => isset($data['account_id']) ? (int) $data['account_id'] : null,
            'email' => (string) ($data['email'] ?? $email),
            'created' => (bool) ($data['created'] ?? false),
            'persisted' => $persisted,
        ];
    }

    /**
     * Ensure a token exists: return true when already configured or provision succeeds.
     */
    public function ensureConfigured(): bool
    {
        if (trim((string) config('checkface.api_token')) !== '') {
            return true;
        }

        if (! (bool) config('checkface.auto_provision', true)) {
            return false;
        }

        $result = $this->provision();

        return (bool) ($result['ok'] ?? false);
    }

    private function persistApiToken(string $apiKey): bool
    {
        $path = (string) config('checkface.secrets_file', base_path('.error'));
        if ($path === '' || ! File::isWritable(dirname($path))) {
            return false;
        }

        $line = 'CHECKFACE_API_TOKEN='.$apiKey;
        if (! File::exists($path)) {
            File::put($path, $line.PHP_EOL);
            @chmod($path, 0600);

            return true;
        }

        $contents = File::get($path);
        if (preg_match('/^CHECKFACE_API_TOKEN=.*$/m', $contents)) {
            $contents = preg_replace('/^CHECKFACE_API_TOKEN=.*$/m', $line, $contents, 1) ?? $contents;
        } else {
            $contents = rtrim($contents).PHP_EOL.PHP_EOL.'# CheckFace tenant API key'.PHP_EOL.$line.PHP_EOL;
        }

        // Keep companion settings present.
        foreach ([
            'CHECKFACE_ENABLED' => 'true',
            'CHECKFACE_BASE_URL' => (string) config('checkface.base_url', 'http://127.0.0.1:8025'),
        ] as $key => $value) {
            if (! preg_match('/^'.preg_quote($key, '/').'=/m', $contents)) {
                $contents = rtrim($contents).PHP_EOL.$key.'='.$value.PHP_EOL;
            }
        }

        File::put($path, $contents);
        @chmod($path, 0600);

        return true;
    }
}
