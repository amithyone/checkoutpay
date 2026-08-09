<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Cache;

final class MevonPayBalanceSnapshotService
{
    public const CACHE_KEY = 'mevonpay_balance_snapshot';

    public const STALE_CACHE_KEY = 'mevonpay_balance_snapshot_stale';

    public function __construct(
        private MevonPayHttpClient $http,
    ) {}

    /**
     * Live wallet balances from MevonPay POST /V1/balance.
     *
     * Successful responses are cached briefly. On failure, the last good
     * snapshot is returned (marked stale) so admin UI stays usable when Mevon
     * is briefly unreachable from this host.
     *
     * @return array{
     *   configured: bool,
     *   ok: bool,
     *   message: string,
     *   naira_balance: ?float,
     *   usd_balance: ?float,
     *   naira_ledger: ?float,
     *   usd_ledger: ?float,
     *   fetched_at: ?string,
     *   stale?: bool
     * }
     */
    public function forDashboard(bool $allowStale = true): array
    {
        if (! $this->http->isConfigured()) {
            return [
                'configured' => false,
                'ok' => false,
                'message' => 'MevonPay is not configured (set MEVONPAY_BASE_URL and MEVONPAY_SECRET_KEY).',
                'naira_balance' => null,
                'usd_balance' => null,
                'naira_ledger' => null,
                'usd_ledger' => null,
                'fetched_at' => null,
            ];
        }

        $ttl = max(30, (int) config('services.mevonpay.balance_cache_seconds', 120));
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && ($cached['ok'] ?? false)) {
            return $cached;
        }

        $api = $this->http->getBalance();
        if ($api['ok'] ?? false) {
            $row = $this->extractBalanceRow($api['data'] ?? null);
            $snapshot = [
                'configured' => true,
                'ok' => true,
                'message' => (string) ($api['message'] ?? 'OK'),
                'naira_balance' => $this->money($row['bal'] ?? $row['naira_balance'] ?? null),
                'usd_balance' => $this->money($row['usd_balance'] ?? null),
                'naira_ledger' => $this->money($row['ledger_bal'] ?? $row['naira_ledger'] ?? null),
                'usd_ledger' => $this->money($row['usd_ledger_bal'] ?? $row['usd_ledger'] ?? null),
                'fetched_at' => now()->toIso8601String(),
                'stale' => false,
            ];

            Cache::put(self::CACHE_KEY, $snapshot, $ttl);
            Cache::put(self::STALE_CACHE_KEY, $snapshot, now()->addDays(7));

            return $snapshot;
        }

        if ($allowStale) {
            $stale = Cache::get(self::STALE_CACHE_KEY);
            if (is_array($stale) && (($stale['naira_balance'] ?? null) !== null || ($stale['usd_balance'] ?? null) !== null)) {
                $stale['ok'] = true;
                $stale['stale'] = true;
                $stale['message'] = 'Showing last known MevonPay balance — live fetch failed: '
                    .(string) ($api['message'] ?? 'unreachable');

                return $stale;
            }
        }

        return [
            'configured' => true,
            'ok' => false,
            'message' => (string) ($api['message'] ?? 'Could not load MevonPay balance.'),
            'naira_balance' => null,
            'usd_balance' => null,
            'naira_ledger' => null,
            'usd_ledger' => null,
            'fetched_at' => now()->toIso8601String(),
            'stale' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractBalanceRow(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['bal']) || isset($data['usd_balance']) || isset($data['ledger_bal'])) {
            return $data;
        }

        $inner = $data['data'] ?? null;
        if (is_array($inner)) {
            if (isset($inner['bal']) || isset($inner['usd_balance']) || isset($inner['ledger_bal'])) {
                return $inner;
            }
            $deeper = $inner['data'] ?? null;
            if (is_array($deeper)) {
                return $deeper;
            }
        }

        return $data;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
