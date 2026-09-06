<?php

namespace App\Support;

final class InternalPaymentWebhookUrl
{
    /**
     * Platform callback URLs handled by app listeners/services — not merchant dashboard webhooks.
     */
    public static function isInternal(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }

        $path = rtrim($path, '/');

        if (preg_match('#^/invoices/pay/[^/]+/webhook$#', $path)) {
            return true;
        }
        if (preg_match('#^/pay/l/[^/]+/webhook$#', $path)) {
            return true;
        }
        if (preg_match('#^/tickets/payment/webhook/[^/]+$#', $path)) {
            return true;
        }
        if (preg_match('#^/memberships/[^/]+/payment/webhook$#', $path)) {
            return true;
        }
        if (preg_match('#^/api/v1/internal/#', $path) || str_starts_with($path, '/internal/')) {
            return true;
        }

        return false;
    }

    /**
     * Rewrite legacy check-outpay.com (or any known legacy host) internal URLs onto APP_URL
     * so Contabo never POSTs platform callbacks at Namecheap.
     */
    public static function rewriteToAppUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! self::isInternal($url)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['path'])) {
            return $url;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $legacyHosts = config('checkout.legacy_hosts', ['check-outpay.com', 'www.check-outpay.com']);
        $appBase = rtrim((string) config('app.url'), '/');
        $appHost = strtolower((string) (parse_url($appBase, PHP_URL_HOST) ?? ''));

        if ($host === '' || $host === $appHost) {
            return $url;
        }

        if (! in_array($host, $legacyHosts, true) && ! str_contains($host, 'check-outpay')) {
            return $url;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $appBase.$path.$query;
    }
}
