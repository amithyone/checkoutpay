<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\MevonPayDiscrepancyAlert;
use App\Models\Setting;
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

        if ($this->hostRole() === 'primary') {
            try {
                if (Schema::hasTable((new MevonPayDiscrepancyAlert)->getTable())) {
                    $alerts = MevonPayDiscrepancyAlert::query()
                        ->orderByDesc('id')
                        ->limit(20)
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
                }
            } catch (Throwable) {
                // Activity must stay read-only and fail soft.
            }
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return response()->json([
            'ok' => true,
            'role' => $this->hostRole(),
            'events' => array_slice($events, 0, 25),
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

    private function hostRole(): string
    {
        return (string) config('checkout.ops_monitor.host_role', 'primary');
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
