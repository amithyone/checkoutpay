<?php

namespace App\Support;

use App\Models\Business;
use App\Models\BusinessWebsite;
use Illuminate\Http\Request;

/**
 * Resolve website / origin / referer for merchant API hit logs.
 * Browser calls send Origin/Referer; server integrations usually do not.
 */
final class ApiHitWebsiteResolver
{
    /**
     * @return array{origin: ?string, referer: ?string, website_host: ?string}
     */
    public static function fromRequest(Request $request, ?Business $business): array
    {
        $origin = self::firstHeader($request, ['Origin', 'X-Origin', 'X-Website-Origin']);
        $referer = self::firstHeader($request, ['Referer', 'Referrer', 'X-Referer', 'X-Website']);

        $webhookFromBody = trim((string) $request->input('webhook_url', ''));
        $websiteFromBody = trim((string) $request->input('website_url', $request->input('website', '')));

        $hintUrl = self::firstNonEmpty([
            $origin,
            $referer,
            self::firstHeader($request, ['X-Website-Url']),
            $websiteFromBody,
            trim((string) $request->input('return_url', '')),
            $webhookFromBody,
        ]);

        $site = self::websiteRow($request, $business, $hintUrl);
        $siteUrl = $site ? trim((string) $site->website_url) : '';
        $webhookUrl = $site ? trim((string) ($site->webhook_url ?? '')) : '';

        $host = WebsiteUrl::hostFrom($hintUrl)
            ?: WebsiteUrl::hostFrom($siteUrl)
            ?: WebsiteUrl::hostFrom($webhookUrl)
            ?: self::fallbackBusinessHost($business);

        if ($origin === '' && $siteUrl !== '') {
            $origin = $siteUrl;
        } elseif ($origin === '' && $hintUrl !== '') {
            $origin = $hintUrl;
        }

        if ($referer === '' && $webhookFromBody !== '') {
            $referer = $webhookFromBody;
        } elseif ($referer === '' && $webhookUrl !== '') {
            $referer = $webhookUrl;
        } elseif ($referer === '' && $siteUrl !== '') {
            $referer = $siteUrl;
        }

        return [
            'origin' => $origin !== '' ? $origin : null,
            'referer' => $referer !== '' ? $referer : null,
            'website_host' => $host,
        ];
    }

    /**
     * @param  list<string>  $names
     */
    private static function firstHeader(Request $request, array $names): string
    {
        foreach ($names as $name) {
            $value = trim((string) $request->headers->get($name, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $values
     */
    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private static function websiteRow(Request $request, ?Business $business, string $hintUrl): ?BusinessWebsite
    {
        $id = (int) $request->input('business_website_id', 0);
        if ($id > 0) {
            $query = BusinessWebsite::query()->whereKey($id);
            if ($business) {
                $query->where('business_id', $business->id);
            }

            $row = $query->first();
            if ($row) {
                return $row;
            }
        }

        if (! $business || ! $business->exists) {
            return null;
        }

        $sites = $business->relationLoaded('websites')
            ? $business->websites
            : $business->websites()->get(['id', 'business_id', 'website_url', 'webhook_url']);

        if ($hintUrl !== '') {
            foreach ($sites as $site) {
                if (WebsiteUrl::hostsMatch($site->website_url, $hintUrl)
                    || WebsiteUrl::hostsMatch($site->webhook_url, $hintUrl)) {
                    return $site;
                }
            }
        }

        if ($sites->count() === 1) {
            return $sites->first();
        }

        return null;
    }

    private static function fallbackBusinessHost(?Business $business): ?string
    {
        if (! $business) {
            return null;
        }

        return WebsiteUrl::hostFrom($business->website);
    }
}
