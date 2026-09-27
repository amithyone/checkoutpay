<?php

namespace App\Services\Partner;

use App\Models\PartnerLicense;
use Illuminate\Support\Facades\Cache;

final class PartnerLicenseIssuerService
{
    public function isEnabled(): bool
    {
        return (bool) config('partner_license.issuer_enabled', false);
    }

    /**
     * @param  list<string>|null  $allowedHosts
     * @param  list<string>|null  $features
     */
    public function create(array $data): PartnerLicense
    {
        return PartnerLicense::query()->create([
            'partner_name' => trim((string) ($data['partner_name'] ?? 'Partner')),
            'license_key' => PartnerLicense::generateKey(),
            'allowed_hosts' => array_values(array_filter($data['allowed_hosts'] ?? [])),
            'valid_until' => $data['valid_until'] ?? null,
            'min_build_version' => trim((string) ($data['min_build_version'] ?? '1.0.0')) ?: '1.0.0',
            'features' => array_values(array_filter($data['features'] ?? ['wallet', 'payout', 'whatsapp'])),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function revoke(PartnerLicense $license): void
    {
        $license->update(['revoked_at' => now()]);
        Cache::forget($this->cacheKey($license->license_key));
    }

    /**
     * @return array<string, mixed>
     */
    public function handlePing(string $licenseKey, array $payload): array
    {
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'status' => 'issuer_disabled',
                'message' => 'License issuer is not enabled on this server.',
            ];
        }

        $license = PartnerLicense::query()
            ->where('license_key', trim($licenseKey))
            ->first();

        if ($license === null) {
            return [
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Unknown license key.',
            ];
        }

        if ($license->isRevoked()) {
            return [
                'ok' => false,
                'status' => 'revoked',
                'message' => 'License has been revoked.',
            ];
        }

        if ($license->isExpired()) {
            return [
                'ok' => false,
                'status' => 'expired',
                'message' => 'License has expired.',
                'valid_until' => optional($license->valid_until)->toIso8601String(),
            ];
        }

        $hostname = trim((string) ($payload['hostname'] ?? ''));
        if (! $license->hostAllowed($hostname)) {
            return [
                'ok' => false,
                'status' => 'host_mismatch',
                'message' => 'This license is not valid for the reported hostname.',
            ];
        }

        $buildVersion = trim((string) ($payload['build_version'] ?? ''));
        if (! $license->buildAllowed($buildVersion)) {
            return [
                'ok' => false,
                'status' => 'build_too_old',
                'message' => 'Installed build is below the minimum allowed version.',
                'min_build_version' => (string) $license->min_build_version,
            ];
        }

        $license->update([
            'last_ping_host' => $hostname !== '' ? $hostname : $license->last_ping_host,
            'last_build_version' => $buildVersion !== '' ? $buildVersion : $license->last_build_version,
            'last_build_id' => trim((string) ($payload['build_id'] ?? '')) ?: $license->last_build_id,
            'last_ping_at' => now(),
        ]);

        $latest = trim((string) config('partner_license.latest_build_version', ''));
        $updateAvailable = $latest !== '' && $buildVersion !== '' && version_compare($latest, $buildVersion, '>');

        $response = [
            'ok' => true,
            'status' => 'active',
            'valid_until' => optional($license->valid_until)->toIso8601String(),
            'min_build_version' => (string) $license->min_build_version,
            'latest_build_version' => $latest !== '' ? $latest : null,
            'update_available' => $updateAvailable,
            'features' => array_values($license->features ?? []),
        ];

        Cache::put($this->cacheKey($license->license_key), $response, now()->addHours(6));

        return $response;
    }

    private function cacheKey(string $licenseKey): string
    {
        return 'partner_license:ping:'.sha1($licenseKey);
    }
}
