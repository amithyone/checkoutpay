<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Support\WebsiteUrl;

class BusinessWebsiteSyncService
{
    /**
     * Copy businesses.website / businesses.webhook_url onto business_websites.
     * Never overwrites a website webhook that is already set to a different URL
     * unless $overwriteMatching is true (settings / admin save).
     *
     * @return array{created: int, webhook_filled: int, skipped: int}
     */
    public function syncFromBusinessRecord(Business $business, bool $overwriteMatching = false): array
    {
        $created = 0;
        $webhookFilled = 0;
        $skipped = 0;

        $business->loadMissing('websites');

        $legacySite = trim((string) ($business->website ?? ''));
        $legacyHook = trim((string) ($business->webhook_url ?? ''));

        if ($legacySite !== '') {
            $row = $this->findMatchingWebsite($business, $legacySite);
            if (! $row) {
                BusinessWebsite::create([
                    'business_id' => $business->id,
                    'website_url' => $legacySite,
                    'webhook_url' => $legacyHook !== '' ? $legacyHook : null,
                    'is_approved' => (bool) $business->website_approved,
                    'approved_at' => $business->website_approved ? now() : null,
                ]);
                $created++;
                if ($legacyHook !== '') {
                    $webhookFilled++;
                }
                $business->unsetRelation('websites');
                $business->load('websites');
            }
        }

        if ($legacyHook === '') {
            return [
                'created' => $created,
                'webhook_filled' => $webhookFilled,
                'skipped' => $skipped,
            ];
        }

        $sites = $business->websites()->get();

        if ($sites->isEmpty()) {
            $host = WebsiteUrl::hostFrom($legacyHook);
            BusinessWebsite::create([
                'business_id' => $business->id,
                'website_url' => $host ? 'https://'.$host : $legacyHook,
                'webhook_url' => $legacyHook,
                'is_approved' => false,
            ]);

            return [
                'created' => $created + 1,
                'webhook_filled' => $webhookFilled + 1,
                'skipped' => $skipped,
            ];
        }

        if ($sites->count() === 1 && $overwriteMatching) {
            $only = $sites->first();
            if (trim((string) ($only->webhook_url ?? '')) !== $legacyHook) {
                $only->update(['webhook_url' => $legacyHook]);
                $webhookFilled++;
            }

            return [
                'created' => $created,
                'webhook_filled' => $webhookFilled,
                'skipped' => $skipped,
            ];
        }

        foreach ($sites as $site) {
            $existing = trim((string) ($site->webhook_url ?? ''));
            $matches = WebsiteUrl::hostsMatch($site->website_url, $legacySite)
                || WebsiteUrl::hostsMatch($site->website_url, $legacyHook)
                || WebsiteUrl::hostsMatch($site->webhook_url, $legacyHook);

            if ($existing === '') {
                $site->update(['webhook_url' => $legacyHook]);
                $webhookFilled++;

                continue;
            }

            if ($overwriteMatching && $matches && $existing !== $legacyHook) {
                $site->update(['webhook_url' => $legacyHook]);
                $webhookFilled++;

                continue;
            }

            $skipped++;
        }

        return [
            'created' => $created,
            'webhook_filled' => $webhookFilled,
            'skipped' => $skipped,
        ];
    }

    private function findMatchingWebsite(Business $business, string $url): ?BusinessWebsite
    {
        foreach ($business->websites as $site) {
            if (WebsiteUrl::hostsMatch($site->website_url, $url)) {
                return $site;
            }
        }

        return null;
    }
}
