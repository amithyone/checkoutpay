<?php

namespace App\Support;

/**
 * Build admin-dashboard cron URLs with CRON_EMAIL_FETCH_TOKEN when configured.
 */
final class AdminCronUrl
{
    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function build(string $path, array $query = []): string
    {
        $token = (string) config('checkout.cron_api_token', '');
        if ($token !== '') {
            $query['token'] = $token;
        }

        $url = url($path);

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }
}
