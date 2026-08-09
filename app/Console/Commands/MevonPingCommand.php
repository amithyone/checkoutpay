<?php

namespace App\Console\Commands;

use App\Services\MevonPay\MevonPayHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MevonPingCommand extends Command
{
    protected $signature = 'mevon:ping {--va : Also try a dry-run connectivity check to createdynamic}';

    protected $description = 'Probe MevonPay connectivity (DNS, balance, optional VA endpoint)';

    public function handle(MevonPayHttpClient $http): int
    {
        $base = rtrim((string) config('services.mevonpay.base_url', ''), '/');
        $this->line('base_url='.($base !== '' ? $base : '(empty)'));
        $this->line('configured='.($http->isConfigured() ? 'yes' : 'no'));

        if (! $http->isConfigured()) {
            return self::FAILURE;
        }

        $host = parse_url($base, PHP_URL_HOST) ?: 'mevonpay.com.ng';
        $ips = gethostbynamel($host) ?: [];
        $this->line('dns='.($ips !== [] ? implode(',', $ips) : 'FAILED'));

        $t0 = microtime(true);
        $balance = $http->getBalance();
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $this->line('balance_ok='.(($balance['ok'] ?? false) ? 'yes' : 'no').' latency_ms='.$ms);
        $this->line('balance_message='.($balance['message'] ?? ''));
        if (($balance['ok'] ?? false) && is_array($balance['data'] ?? null)) {
            $d = $balance['data'];
            $this->line('NGN='.($d['bal'] ?? 'n/a').' USD='.($d['usd_balance'] ?? 'n/a'));
        }

        if ($this->option('va')) {
            $url = $base.'/V1/createdynamic';
            $t1 = microtime(true);
            try {
                $resp = Http::withHeaders([
                    'Authorization' => trim((string) config('services.mevonpay.secret_key')),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                    ->timeout((int) config('services.mevonpay.timeout_seconds', 20))
                    ->connectTimeout((int) config('services.mevonpay.connect_timeout_seconds', 3))
                    ->post($url, ['amount' => 100, 'currency' => 'NGN']);
                $vaMs = (int) round((microtime(true) - $t1) * 1000);
                $this->line('createdynamic_http='.$resp->status().' latency_ms='.$vaMs);
            } catch (\Throwable $e) {
                $vaMs = (int) round((microtime(true) - $t1) * 1000);
                $this->error('createdynamic_failed latency_ms='.$vaMs.' error='.$e->getMessage());
            }
        }

        return ($balance['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
