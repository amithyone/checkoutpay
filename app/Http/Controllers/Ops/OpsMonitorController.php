<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\MevonPayDiscrepancyAlert;
use App\Models\MevonPayLedgerEntry;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\WithdrawalRequest;
use App\Services\MevonPay\MevonPayBalanceMonitorService;
use App\Services\Quarantine\QuarantineService;
use App\Support\SiteBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OpsMonitorController extends Controller
{
    public function __construct(
        private QuarantineService $quarantine,
        private MevonPayBalanceMonitorService $mevonBalance,
    ) {}

    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'role' => $this->hostRole(),
            'hostname' => gethostname() ?: null,
            'app_url' => (string) config('app.url'),
            'app_name' => SiteBranding::name(),
            'app_version' => (string) (config('app.version') ?: env('APP_VERSION', '')),
            'git_sha' => $this->gitSha(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function security(): JsonResponse
    {
        $status = $this->quarantine->status();
        $dbHost = (string) config('database.connections.'.config('database.default', 'mysql').'.host', '');
        $dbName = (string) config('database.connections.'.config('database.default', 'mysql').'.database', '');
        $allowedHosts = config('checkout.quarantine.allowed_db_hosts', []);
        $allowedDb = strtolower((string) config('checkout.quarantine.allowed_db_database', ''));

        $hostAllowed = is_array($allowedHosts)
            && in_array(strtolower($dbHost), array_map('strtolower', $allowedHosts), true);
        $databaseAllowed = $allowedDb === '' || strtolower($dbName) === $allowedDb;

        return response()->json([
            'ok' => true,
            'role' => $this->hostRole(),
            'quarantine' => [
                'enabled' => (bool) ($status['enabled'] ?? false),
                'active' => (bool) ($status['active'] ?? false),
                'reasons' => array_values($status['reasons'] ?? []),
                'tripped_at' => $status['lock']['tripped_at'] ?? null,
            ],
            'db' => [
                'host' => $this->maskHost($dbHost),
                'database' => $dbName !== '' ? $dbName : null,
                'host_allowed' => $hostAllowed,
                'database_allowed' => $databaseAllowed,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function health(): JsonResponse
    {
        $lastCron = null;
        try {
            $lastCron = Setting::get('last_cron_run', null);
        } catch (Throwable) {
            $lastCron = null;
        }

        $queueDepth = null;
        try {
            $queueDepth = Queue::size();
        } catch (Throwable) {
            $queueDepth = null;
        }

        return response()->json([
            'ok' => true,
            'status' => 'ok',
            'role' => $this->hostRole(),
            'service' => SiteBranding::name(),
            'last_cron_run' => $lastCron,
            'queue_depth' => $queueDepth,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function activity(): JsonResponse
    {
        $events = [];

        $status = $this->quarantine->status();
        if (! empty($status['active'])) {
            $events[] = [
                'id' => 'quarantine-active',
                'type' => 'quarantine',
                'severity' => 'critical',
                'title' => 'Quarantine active',
                'detail' => implode(', ', $status['reasons'] ?? []) ?: 'lock engaged',
                'at' => $status['lock']['tripped_at'] ?? now()->toIso8601String(),
            ];
        }

        $events = array_merge(
            $events,
            $this->collectPayInEvents(),
            $this->collectPayoutEvents(),
            $this->collectLedgerEvents(),
            $this->collectAccountCreateEvents(),
            $this->collectMevonVarianceEvents(),
        );

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return response()->json([
            'ok' => true,
            'role' => $this->hostRole(),
            'events' => array_slice($events, 0, 40),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function balances(): JsonResponse
    {
        if ($this->hostRole() !== 'primary') {
            return response()->json([
                'ok' => true,
                'available' => false,
                'role' => 'relay',
                'message' => 'Mevon balances are Contabo-primary only.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        try {
            $summary = $this->mevonBalance->summary();
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'available' => false,
                'role' => 'primary',
                'message' => 'Balance snapshot unavailable',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        }

        return response()->json([
            'ok' => true,
            'available' => true,
            'role' => 'primary',
            'active' => (bool) ($summary['active'] ?? false),
            'expected_balance' => $summary['expected_balance'] ?? null,
            'live_naira_balance' => $summary['live_naira_balance'] ?? null,
            'variance_amount' => $summary['variance_amount'] ?? null,
            'within_tolerance' => (bool) ($summary['within_tolerance'] ?? true),
            'tolerance' => $summary['tolerance'] ?? null,
            'last_checked_at' => $summary['last_checked_at'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return list<array{id: string, type: string, severity: string, title: string, detail: string, at: ?string}>
     */
    private function collectPayInEvents(): array
    {
        $events = [];
        try {
            if (! Schema::hasTable((new Payment)->getTable())) {
                return [];
            }

            $rows = Payment::query()
                ->where('status', Payment::STATUS_APPROVED)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'amount', 'received_amount', 'account_number', 'status', 'matched_at', 'created_at', 'payment_source']);

            foreach ($rows as $row) {
                $amount = $row->received_amount ?? $row->amount;
                $events[] = [
                    'id' => 'pay-'.$row->id,
                    'type' => 'pay_in',
                    'severity' => 'info',
                    'title' => 'Pay-in approved',
                    'detail' => sprintf(
                        'NGN %s · acct %s%s',
                        $this->formatMoney($amount),
                        $this->maskAccount((string) $row->account_number),
                        $row->payment_source ? ' · '.$row->payment_source : ''
                    ),
                    'at' => optional($row->matched_at ?? $row->created_at)?->toIso8601String(),
                ];
            }
        } catch (Throwable) {
            // fail soft
        }

        return $events;
    }

    /**
     * @return list<array{id: string, type: string, severity: string, title: string, detail: string, at: ?string}>
     */
    private function collectPayoutEvents(): array
    {
        $events = [];
        try {
            if (! Schema::hasTable((new WithdrawalRequest)->getTable())) {
                return [];
            }

            $rows = WithdrawalRequest::query()
                ->orderByDesc('id')
                ->limit(20)
                ->get([
                    'id',
                    'amount',
                    'account_number',
                    'status',
                    'payout_status',
                    'payout_provider',
                    'source',
                    'processed_at',
                    'payout_succeeded_at',
                    'payout_attempted_at',
                    'created_at',
                ]);

            foreach ($rows as $row) {
                $bucket = (string) ($row->payout_status ?: $row->status);
                $severity = in_array(strtolower($bucket), ['failed', 'rejected'], true) ? 'high' : 'info';
                $events[] = [
                    'id' => 'wd-'.$row->id,
                    'type' => 'payout',
                    'severity' => $severity,
                    'title' => 'Payout '.$bucket,
                    'detail' => sprintf(
                        'NGN %s · acct %s · %s',
                        $this->formatMoney($row->amount),
                        $this->maskAccount((string) $row->account_number),
                        trim(($row->payout_provider ?: 'manual').($row->source ? ' / '.$row->source : ''))
                    ),
                    'at' => optional(
                        $row->payout_succeeded_at
                        ?? $row->processed_at
                        ?? $row->payout_attempted_at
                        ?? $row->created_at
                    )?->toIso8601String(),
                ];
            }
        } catch (Throwable) {
            // fail soft
        }

        return $events;
    }

    /**
     * @return list<array{id: string, type: string, severity: string, title: string, detail: string, at: ?string}>
     */
    private function collectLedgerEvents(): array
    {
        $events = [];
        try {
            if (! Schema::hasTable((new MevonPayLedgerEntry)->getTable())) {
                return [];
            }

            $rows = MevonPayLedgerEntry::query()
                ->orderByDesc('id')
                ->limit(25)
                ->get();

            foreach ($rows as $row) {
                $isOut = $row->direction === MevonPayLedgerEntry::DIRECTION_OUTBOUND;
                $events[] = [
                    'id' => 'ledger-'.$row->id,
                    'type' => $isOut ? 'payout' : 'pay_in',
                    'severity' => 'info',
                    'title' => ($isOut ? 'Mevon out · ' : 'Mevon in · ').$row->flowTypeLabel(),
                    'detail' => sprintf(
                        'NGN %s · acct %s%s',
                        $this->formatMoney($row->gross_amount),
                        $this->maskAccount((string) $row->account_number),
                        $row->payout_bucket ? ' · '.$row->payout_bucket : ''
                    ),
                    'at' => optional($row->occurred_at ?? $row->created_at)?->toIso8601String(),
                ];
            }
        } catch (Throwable) {
            // fail soft
        }

        return $events;
    }

    /**
     * @return list<array{id: string, type: string, severity: string, title: string, detail: string, at: ?string}>
     */
    private function collectAccountCreateEvents(): array
    {
        $events = [];

        try {
            if (Schema::hasTable((new AccountNumber)->getTable())) {
                $rows = AccountNumber::query()
                    ->orderByDesc('id')
                    ->limit(15)
                    ->get(['id', 'account_number', 'bank_name', 'external_provider', 'is_external', 'is_pool', 'created_at']);

                foreach ($rows as $row) {
                    $kind = $row->is_external
                        ? ((string) ($row->external_provider ?: 'external').' VA')
                        : ($row->is_pool ? 'pool account' : 'account');
                    $events[] = [
                        'id' => 'acct-'.$row->id,
                        'type' => 'account_create',
                        'severity' => 'info',
                        'title' => 'Account created',
                        'detail' => sprintf(
                            '%s · %s%s',
                            $kind,
                            $this->maskAccount((string) $row->account_number),
                            $row->bank_name ? ' · '.$row->bank_name : ''
                        ),
                        'at' => optional($row->created_at)?->toIso8601String(),
                    ];
                }
            }
        } catch (Throwable) {
            // fail soft
        }

        try {
            if (Schema::hasTable((new Business)->getTable())
                && Schema::hasColumn((new Business)->getTable(), 'rubies_business_account_created_at')) {
                $rows = Business::query()
                    ->whereNotNull('rubies_business_account_created_at')
                    ->orderByDesc('rubies_business_account_created_at')
                    ->limit(10)
                    ->get(['id', 'rubies_business_account_created_at']);

                foreach ($rows as $row) {
                    $events[] = [
                        'id' => 'biz-rubies-'.$row->id,
                        'type' => 'account_create',
                        'severity' => 'info',
                        'title' => 'Business Rubies VA provisioned',
                        'detail' => 'business #'.$row->id,
                        'at' => optional($row->rubies_business_account_created_at)?->toIso8601String(),
                    ];
                }
            }
        } catch (Throwable) {
            // fail soft
        }

        return $events;
    }

    /**
     * @return list<array{id: string, type: string, severity: string, title: string, detail: string, at: ?string}>
     */
    private function collectMevonVarianceEvents(): array
    {
        if ($this->hostRole() !== 'primary') {
            return [];
        }

        $events = [];
        try {
            if (! Schema::hasTable((new MevonPayDiscrepancyAlert)->getTable())) {
                return [];
            }

            $alerts = MevonPayDiscrepancyAlert::query()
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            foreach ($alerts as $alert) {
                $variance = (float) $alert->variance_amount;
                $events[] = [
                    'id' => 'mevon-alert-'.$alert->id,
                    'type' => 'mevon_variance',
                    'severity' => abs($variance) > (float) $alert->tolerance ? 'high' : 'info',
                    'title' => 'Mevon balance variance',
                    'detail' => sprintf(
                        'variance=%s expected=%s live=%s (%s)',
                        $alert->variance_amount,
                        $alert->expected_balance,
                        $alert->live_balance,
                        $alert->triggerLabel()
                    ),
                    'at' => optional($alert->checked_at ?? $alert->created_at)?->toIso8601String(),
                ];
            }
        } catch (Throwable) {
            // fail soft
        }

        return $events;
    }

    private function hostRole(): string
    {
        return (string) config('checkout.ops_monitor.host_role', 'primary');
    }

    private function formatMoney(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    private function maskAccount(string $account): string
    {
        $account = preg_replace('/\s+/', '', trim($account)) ?? '';
        if ($account === '') {
            return '—';
        }
        if (strlen($account) <= 4) {
            return $account[0].'***';
        }

        return substr($account, 0, 2).'***'.substr($account, -2);
    }

    private function maskHost(string $host): ?string
    {
        $host = trim($host);
        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $parts = explode('.', $host);
            if (count($parts) === 4) {
                return $parts[0].'.*.*.'.$parts[3];
            }

            return substr($host, 0, 4).'…';
        }

        if (strlen($host) <= 4) {
            return $host[0].'***';
        }

        return substr($host, 0, 2).'***'.substr($host, -2);
    }

    private function gitSha(): ?string
    {
        $envSha = trim((string) env('GIT_SHA', env('APP_GIT_SHA', '')));
        if ($envSha !== '') {
            return substr($envSha, 0, 12);
        }

        $head = base_path('.git/HEAD');
        if (! is_file($head)) {
            return null;
        }

        $raw = trim((string) @file_get_contents($head));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'ref:')) {
            $ref = trim(substr($raw, 4));
            $refFile = base_path('.git/'.$ref);
            if (is_file($refFile)) {
                return substr(trim((string) @file_get_contents($refFile)), 0, 12);
            }

            return null;
        }

        return substr($raw, 0, 12);
    }
}
