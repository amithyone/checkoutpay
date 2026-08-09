<?php

namespace App\Support;

/**
 * Helpers for routing live→Mevon calls through a Contabo egress proxy when the
 * live host cannot open TCP to mevonpay.com.ng.
 */
final class MevonPayEgress
{
    public const TOKEN_HEADER = 'X-Mevon-Egress-Token';

    /**
     * Extra headers live clients must send when BASE_URL points at the proxy.
     *
     * @return array<string, string>
     */
    public static function clientHeaders(): array
    {
        $token = trim((string) config('services.mevonpay.egress_client_token', ''));
        if ($token === '') {
            return [];
        }

        return [self::TOKEN_HEADER => $token];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public static function mergeClientHeaders(array $headers): array
    {
        return array_merge($headers, self::clientHeaders());
    }

    public static function proxyEnabled(): bool
    {
        return (bool) config('services.mevonpay.egress_proxy_enabled', false);
    }

    public static function proxyToken(): string
    {
        return trim((string) config('services.mevonpay.egress_proxy_token', ''));
    }

    /**
     * @return list<string>
     */
    public static function proxyAllowedIps(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.mevonpay.egress_proxy_allowed_ips', ''))
        )));
    }

    public static function upstreamBase(): string
    {
        $upstream = rtrim((string) config('services.mevonpay.egress_upstream', ''), '/');
        if ($upstream !== '') {
            return $upstream;
        }

        return rtrim((string) config('services.mevonpay.base_url', 'https://mevonpay.com.ng'), '/');
    }
}
