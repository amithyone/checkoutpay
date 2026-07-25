<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Imunify360 / WAF helpers for ops messaging and consumer-safe API errors.
 */
final class Imunify360Ops
{
    public const CONSUMER_WAF_MESSAGE = 'Account setup is temporarily unavailable. Please try again in a few minutes.';

    public static function looksLikeWafBlock(string $body): bool
    {
        $lower = strtolower($body);
        foreach (['imunify360', 'imunify', 'bot protection', 'bot-protection', 'access denied'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function wafBlockMessage(): string
    {
        return 'Blocked by server WAF (Imunify360). Whitelist this server\'s outbound IP with Mevon/hosting, or allow HTTPS to the Mevon API host.';
    }

    public static function sanitizeConsumerMessage(?string $message): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        if (self::looksLikeWafBlock($message)) {
            return self::CONSUMER_WAF_MESSAGE;
        }

        if (strlen($message) > 280 && (str_contains(strtolower($message), '<html') || str_contains($message, '<!DOCTYPE'))) {
            return self::CONSUMER_WAF_MESSAGE;
        }

        return $message;
    }

    public static function appHost(): string
    {
        $host = parse_url((string) config('app.url', ''), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'your-domain.com';
    }

    public static function serverOutboundIp(): ?string
    {
        return Cache::remember('ops:server_outbound_ip', 3600, function (): ?string {
            try {
                $response = Http::timeout(5)->get('https://api.ipify.org?format=text');
                if ($response->successful()) {
                    $ip = trim($response->body());

                    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
                }
            } catch (\Throwable) {
                // ignore
            }

            return null;
        });
    }

    /**
     * @return list<string>
     */
    public static function pathsNeedingWafBypass(): array
    {
        return [
            '/api/v1/consumer/*',
            '/api/v1/webhook/mevonpay',
            '/api/v1/webhooks/mevonpay',
            '/cron/process-kyc-queue',
            '/cron/process-emails',
            '/api/v1/cron/process-webhooks',
        ];
    }
}
