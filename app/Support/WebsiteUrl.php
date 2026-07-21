<?php

namespace App\Support;

final class WebsiteUrl
{
    /**
     * Extract a normalized host from a URL or bare domain (example.com/path).
     */
    public static function hostFrom(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $stripped = preg_replace('#^https?://#i', '', $url) ?? $url;
            $host = explode('/', $stripped)[0] ?? '';
            $host = explode('?', $host)[0] ?? '';
            $host = explode('#', $host)[0] ?? '';
        }

        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', $host) ?: null;
    }

    /**
     * @return list<string>
     */
    public static function hostsForMatching(?string $websiteUrl, ?string $webhookUrl = null): array
    {
        $hosts = array_filter([
            self::hostFrom($websiteUrl),
            self::hostFrom($webhookUrl),
        ]);

        return array_values(array_unique($hosts));
    }

    public static function hrefFrom(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return 'https://'.$url;
    }

    public static function hostsMatch(?string $left, ?string $right): bool
    {
        $leftHost = self::hostFrom($left);
        $rightHost = self::hostFrom($right);

        if ($leftHost === null || $rightHost === null) {
            return false;
        }

        if ($leftHost === $rightHost) {
            return true;
        }

        return str_ends_with($leftHost, '.'.$rightHost) || str_ends_with($rightHost, '.'.$leftHost);
    }
}
