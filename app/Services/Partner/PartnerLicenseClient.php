<?php

namespace App\Services\Partner;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PartnerLicenseClient
{
    public function isEnforced(): bool
    {
        return (bool) config('partner_license.enforced', false);
    }

    public function licenseKey(): string
    {
        return trim((string) config('partner_license.license_key', ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifest(): ?array
    {
        $path = (string) config('partner_license.manifest_path', '');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function buildVersion(): ?string
    {
        $manifest = $this->manifest();
        $version = trim((string) ($manifest['version'] ?? ''));

        return $version !== '' ? $version : null;
    }

    public function buildId(): ?string
    {
        $manifest = $this->manifest();
        $id = trim((string) ($manifest['build_id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * @return array{ok: bool, message: string, missing?: list<string>}
     */
    public function verifyBuildManifest(): array
    {
        $manifest = $this->manifest();
        if ($manifest === null) {
            return [
                'ok' => false,
                'message' => 'partner-build-manifest.json is missing. Partner drops must ship with a build manifest.',
            ];
        }

        $product = trim((string) ($manifest['product'] ?? ''));
        if ($product !== '' && $product !== (string) config('partner_license.product_slug')) {
            return [
                'ok' => false,
                'message' => 'Build manifest product slug does not match this package.',
            ];
        }

        if ((bool) ($manifest['requires_ioncube'] ?? false) && ! extension_loaded('ionCube Loader')) {
            return [
                'ok' => false,
                'message' => 'This build requires the ionCube Loader PHP extension.',
            ];
        }

        $checksums = $manifest['checksums'] ?? [];
        if (! is_array($checksums) || $checksums === []) {
            return [
                'ok' => true,
                'message' => 'Manifest present (checksum verification skipped).',
            ];
        }

        $missing = [];
        foreach ($checksums as $relativePath => $expected) {
            $fullPath = base_path((string) $relativePath);
            if (! is_readable($fullPath)) {
                $missing[] = (string) $relativePath;

                continue;
            }

            $hash = 'sha256:'.hash_file('sha256', $fullPath);
            if (! hash_equals((string) $expected, $hash)) {
                $missing[] = (string) $relativePath.' (checksum mismatch)';
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Encoded build integrity check failed.',
                'missing' => $missing,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Build manifest and encoded file checksums verified.',
        ];
    }

    /**
     * @return array{ok: bool, status: string, message?: string, cached?: bool, payload?: array<string, mixed>}
     */
    public function ping(bool $forceRemote = false): array
    {
        if (! $this->isEnforced()) {
            return ['ok' => true, 'status' => 'not_enforced'];
        }

        $key = $this->licenseKey();
        if ($key === '') {
            return [
                'ok' => false,
                'status' => 'missing_key',
                'message' => 'PARTNER_LICENSE_KEY is not configured.',
            ];
        }

        $cacheKey = 'partner_license:client:'.sha1($key);
        if (! $forceRemote) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached + ['cached' => true];
            }
        }

        $issuer = rtrim((string) config('partner_license.issuer_url', ''), '/');
        if ($issuer === '') {
            return [
                'ok' => false,
                'status' => 'issuer_missing',
                'message' => 'PARTNER_LICENSE_ISSUER_URL is not configured.',
            ];
        }

        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        $hostname = is_string($appHost) && $appHost !== '' ? $appHost : (string) request()->getHost();

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->post($issuer.'/api/v1/partner-license/ping', [
                    'license_key' => $key,
                    'hostname' => $hostname,
                    'build_version' => $this->buildVersion(),
                    'build_id' => $this->buildId(),
                    'php_version' => PHP_VERSION,
                    'product' => config('partner_license.product_slug'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('partner_license.ping_failed', ['error' => $e->getMessage()]);

            return $this->graceFallback($cacheKey, 'network_error', $e->getMessage());
        }

        if (! $response->successful()) {
            return $this->graceFallback($cacheKey, 'issuer_error', 'License server returned HTTP '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return $this->graceFallback($cacheKey, 'invalid_response', 'License server returned an invalid payload.');
        }

        $result = [
            'ok' => (bool) ($payload['ok'] ?? false),
            'status' => (string) ($payload['status'] ?? 'unknown'),
            'message' => $payload['message'] ?? null,
            'payload' => $payload,
            'cached' => false,
            'checked_at' => now()->toIso8601String(),
        ];

        if ($result['ok']) {
            Cache::put($cacheKey, $result, now()->addMinutes((int) config('partner_license.ping_cache_minutes', 360)));
            Cache::put($cacheKey.':last_success', now()->toIso8601String(), now()->addDays(30));
        } else {
            Cache::forget($cacheKey);
        }

        return $result;
    }

    public function shouldBlockLicensedFeatures(): bool
    {
        if (! $this->isEnforced()) {
            return false;
        }

        $ping = $this->ping();
        if ($ping['ok'] ?? false) {
            return false;
        }

        return ! $this->withinGracePeriod();
    }

    public function blockMessage(): string
    {
        $ping = $this->ping();
        $message = trim((string) ($ping['message'] ?? ''));

        return $message !== ''
            ? $message
            : 'Partner license is invalid or could not be verified. Contact your platform provider.';
    }

    private function withinGracePeriod(): bool
    {
        $key = $this->licenseKey();
        if ($key === '') {
            return false;
        }

        $lastSuccess = Cache::get('partner_license:client:'.sha1($key).':last_success');
        if (! is_string($lastSuccess) || $lastSuccess === '') {
            return false;
        }

        try {
            $at = Carbon::parse($lastSuccess);
        } catch (\Throwable) {
            return false;
        }

        return $at->addHours((int) config('partner_license.grace_hours', 72))->isFuture();
    }

    /**
     * @return array{ok: bool, status: string, message?: string, cached?: bool, grace?: bool}
     */
    private function graceFallback(string $cacheKey, string $status, string $message): array
    {
        if ($this->withinGracePeriod()) {
            return [
                'ok' => true,
                'status' => 'grace',
                'message' => $message,
                'grace' => true,
                'cached' => true,
            ];
        }

        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'cached' => false,
        ];
    }
}
