<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PartnerLicense extends Model
{
    protected $fillable = [
        'partner_name',
        'license_key',
        'allowed_hosts',
        'valid_until',
        'min_build_version',
        'features',
        'revoked_at',
        'last_ping_host',
        'last_build_version',
        'last_build_id',
        'last_ping_at',
        'notes',
    ];

    protected $casts = [
        'allowed_hosts' => 'array',
        'features' => 'array',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
        'last_ping_at' => 'datetime',
    ];

    public static function generateKey(): string
    {
        return 'pl_'.Str::lower(Str::random(48));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && now()->greaterThan($this->valid_until);
    }

    public function hostAllowed(?string $hostname): bool
    {
        $host = strtolower(trim((string) $hostname));
        if ($host === '') {
            return false;
        }

        $allowed = collect($this->allowed_hosts ?? [])
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $pattern) {
            if ($host === $pattern) {
                return true;
            }
            if (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1))) {
                return true;
            }
        }

        return false;
    }

    public function buildAllowed(?string $buildVersion): bool
    {
        $build = trim((string) $buildVersion);
        if ($build === '') {
            return false;
        }

        return version_compare($build, (string) $this->min_build_version, '>=');
    }
}
