<?php

namespace App\Support;

/**
 * Reject URLs that would cause SSRF to loopback, private, or cloud metadata targets.
 */
final class SafeOutboundUrl
{
    /**
     * @return string|null Null if safe; otherwise a short reason for rejection.
     */
    public static function rejectionReason(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return 'URL is empty.';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return 'URL is invalid.';
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Only http and https webhook URLs are allowed.';
        }

        $host = strtolower((string) $parts['host']);
        $host = trim($host, '[]');

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === '0.0.0.0') {
            return 'Localhost webhook URLs are not allowed.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                return 'Private or reserved IP webhook URLs are not allowed.';
            }

            return null;
        }

        if ($host === 'metadata.google.internal' || str_ends_with($host, '.internal')) {
            return 'Internal hostnames are not allowed.';
        }

        $resolved = gethostbynamel($host);
        if ($resolved === false || $resolved === []) {
            // Allow unresolved hosts — HTTP client will fail later; do not open DNS rebinding window by skipping resolve.
            return 'Could not resolve webhook hostname.';
        }

        foreach ($resolved as $ip) {
            if (! self::isPublicIp($ip)) {
                return 'Webhook hostname resolves to a private or reserved IP.';
            }
        }

        return null;
    }

    public static function isSafe(string $url): bool
    {
        return self::rejectionReason($url) === null;
    }

    private static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            // Cloud metadata commonly used in SSRF
            if ($ip === '169.254.169.254' || str_starts_with($ip, '169.254.')) {
                return false;
            }

            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            $normalized = strtolower($ip);
            if ($normalized === '::1' || str_starts_with($normalized, 'fe80:') || str_starts_with($normalized, 'fc') || str_starts_with($normalized, 'fd')) {
                return false;
            }

            return true;
        }

        return false;
    }
}
