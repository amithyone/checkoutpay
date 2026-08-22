<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Blocks Tor exits, unresolvable signup domains, and obvious bot-registration garbage.
 */
final class RegistrationAbuseGuard
{
    /**
     * @param  array{name?: ?string, email?: ?string, website?: ?string, business_id?: ?string}  $fields
     */
    public function blockReason(string $ip, array $fields = []): ?string
    {
        if (! (bool) config('security.registration_abuse_guard.enabled', true)) {
            return null;
        }

        if ($this->isTorExitIp($ip)) {
            return 'tor_exit';
        }

        $name = trim((string) ($fields['name'] ?? ''));
        if ($name !== '' && $this->looksLikeSpamName($name)) {
            return 'spam_name';
        }

        $businessId = trim((string) ($fields['business_id'] ?? ''));
        if ($businessId !== '' && $this->looksLikeSpamName($businessId)) {
            return 'spam_business_id';
        }

        $website = trim((string) ($fields['website'] ?? ''));
        if ($website !== '' && ! $this->websiteHostResolvable($website)) {
            return 'website_nxdomain';
        }

        return null;
    }

    /**
     * @param  array{name?: ?string, email?: ?string, website?: ?string, business_id?: ?string}  $fields
     */
    public function logBlocked(string $context, string $ip, array $fields, string $reason): void
    {
        Log::warning('registration_abuse.blocked', [
            'context' => $context,
            'reason' => $reason,
            'ip' => $ip,
            'email' => $fields['email'] ?? null,
            'name' => isset($fields['name']) ? mb_substr((string) $fields['name'], 0, 80) : null,
            'website' => $fields['website'] ?? null,
        ]);
    }

    public function isTorExitIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return false;
        }

        if (! (bool) config('security.registration_abuse_guard.block_tor_exits', true)) {
            return false;
        }

        $needles = (array) config('security.registration_abuse_guard.tor_rdns_needles', [
            'tor-exit',
            '.relayon.org',
            'relayon.org',
            '.torexit.',
            'torexit.',
        ]);

        $host = Cache::remember(
            'reg-abuse-rdns:'.hash('sha256', $ip),
            max(300, (int) config('security.registration_abuse_guard.rdns_cache_seconds', 3600)),
            function () use ($ip): string {
                $resolved = @gethostbyaddr($ip);

                return is_string($resolved) ? strtolower($resolved) : '';
            },
        );

        if ($host === '' || $host === strtolower($ip)) {
            return false;
        }

        foreach ($needles as $needle) {
            $needle = strtolower(trim((string) $needle));
            if ($needle !== '' && str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function looksLikeSpamName(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/^(.+?)\s+LLC$/i', $value, $m)) {
            $prefix = trim($m[1]);
            if ($this->isGarbageToken($prefix)) {
                return true;
            }

            // Bot registrations often use consonant-heavy fake LLC names (e.g. Crzwokloo LLC).
            $len = strlen($prefix);
            if ($len >= 7 && $len <= 24 && preg_match('/^[A-Za-z]+$/', $prefix)) {
                $vowels = preg_match_all('/[aeiouAEIOU]/', $prefix);
                $ratio = $len > 0 ? $vowels / $len : 0;
                if ($ratio < 0.35) {
                    return true;
                }
            }

            return false;
        }

        return $this->isGarbageToken($value);
    }

    public function websiteHostResolvable(string $url): bool
    {
        if (! (bool) config('security.registration_abuse_guard.require_website_dns', true)) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $cacheKey = 'reg-abuse-dns:'.hash('sha256', $host);

        return (bool) Cache::remember(
            $cacheKey,
            max(300, (int) config('security.registration_abuse_guard.dns_cache_seconds', 3600)),
            function () use ($host): bool {
                return @checkdnsrr($host, 'A')
                    || @checkdnsrr($host, 'AAAA')
                    || @checkdnsrr($host, 'CNAME');
            },
        );
    }

    private function isGarbageToken(string $token): bool
    {
        $token = trim($token);
        $len = strlen($token);
        if ($len < 10 || preg_match('/\s/', $token)) {
            return false;
        }

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $token)) {
            return false;
        }

        if ($len >= 14 && preg_match('/^[A-Za-z0-9]+$/', $token)) {
            return true;
        }

        if ($len >= 8 && preg_match('/^[A-Za-z]+$/', $token)) {
            $vowels = preg_match_all('/[aeiouAEIOU]/', $token);
            $ratio = $len > 0 ? $vowels / $len : 0;
            if ($ratio < 0.25 && $len >= 8) {
                return true;
            }
        }

        return false;
    }
}
