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
}
