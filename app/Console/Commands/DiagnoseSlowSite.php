<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DiagnoseSlowSite extends Command
{
    protected $signature = 'performance:diagnose';
    protected $description = 'Diagnose why the site is slow';

    public function handle()
    {
        $this->info('🔍 Diagnosing Site Performance Issues...');
        $this->newLine();

        // 1. Check database connection
        $this->info('1. Testing Database Connection...');
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbTime = (microtime(true) - $start) * 1000;
            if ($dbTime > 100) {
                $this->error("   ⚠️  Database connection slow: {$dbTime}ms");
            } else {
                $this->info("   ✅ Database connection: {$dbTime}ms");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Database connection failed: " . $e->getMessage());
        }

        // 2. Check cache
        $this->newLine();
        $this->info('2. Testing Cache Performance...');
        $start = microtime(true);
        $testKey = 'diagnostic_test_' . time();
        Cache::put($testKey, 'test', 60);
        $value = Cache::get($testKey);
        $cacheTime = (microtime(true) - $start) * 1000;
        Cache::forget($testKey);
        
        if ($cacheTime > 50) {
            $this->error("   ⚠️  Cache is slow: {$cacheTime}ms");
            $this->warn("   💡 Consider using Redis instead of database cache");
        } else {
            $this->info("   ✅ Cache performance: {$cacheTime}ms");
        }
        $this->info("   Cache driver: " . config('cache.default'));

        // 3. Check account number assignment
        $this->newLine();
        $this->info('3. Testing Account Number Assignment...');
        $start = microtime(true);
        try {
            $service = app(\App\Services\AccountNumberService::class);
            $count = $service->getAvailablePoolCount();
            $time = (microtime(true) - $start) * 1000;
            
            if ($time > 100) {
                $this->error("   ⚠️  Account number service slow: {$time}ms");
            } else {
                $this->info("   ✅ Account number service: {$time}ms");
            }
            $this->info("   Available pool accounts: {$count}");
        } catch (\Exception $e) {
            $this->error("   ❌ Error: " . $e->getMessage());
        }

        // 4. Check pending payments query
        $this->newLine();
        $this->info('4. Testing Pending Payments Query...');
        $start = microtime(true);
        try {
            $pendingCount = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count();
            $time = (microtime(true) - $start) * 1000;
            
            if ($time > 200) {
                $this->error("   ⚠️  Pending payments query slow: {$time}ms");
                $this->warn("   💡 Check if index exists: payments(status, account_number, expires_at)");
            } else {
                $this->info("   ✅ Pending payments query: {$time}ms");
            }
            $this->info("   Pending payments: {$pendingCount}");
        } catch (\Exception $e) {
            $this->error("   ❌ Error: " . $e->getMessage());
        }

        // 5. Check database indexes
        $this->newLine();
        $this->info('5. Checking Database Indexes...');
        try {
            $indexes = DB::select("SHOW INDEX FROM payments WHERE Key_name = 'idx_status_account_expires'");
            if (empty($indexes)) {
                $this->error("   ❌ Missing index: idx_status_account_expires");
                $this->warn("   💡 Run: php artisan migrate");
            } else {
                $this->info("   ✅ Index exists: idx_status_account_expires");
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Could not check indexes: " . $e->getMessage());
        }

        // 6. Check cache keys
        $this->newLine();
        $this->info('6. Checking Cache Keys...');
        $cacheKeys = [
            'account_number_service:pending_accounts',
            'account_number_service:last_used_account',
            'account_number_service:pool_accounts',
            'page_home',
            'setting_site_logo',
        ];
        
        foreach ($cacheKeys as $key) {
            $exists = Cache::has($key);
            if ($exists) {
                $this->info("   ✅ Cache key exists: {$key}");
            } else {
                $this->warn("   ⚠️  Cache key missing: {$key}");
            }
        }

        // 7. Check recent slow requests
        $this->newLine();
        $this->info('7. Recent Slow Requests (last hour)...');
        $this->call('performance:analyze-slow', [
            '--hours' => 1,
            '--top' => 5,
            '--min-duration' => 500,
        ]);

        // 8. Recommendations
        $this->newLine();
        $this->info('📋 Recommendations:');
        $this->line('   1. Clear all caches: php artisan cache:clear');
        $this->line('   2. Run migrations: php artisan migrate');
        $this->line('   3. Check server resources: top, free -h');
        $this->line('   4. Consider Redis for cache: CACHE_STORE=redis');
        $this->line('   5. Check logs: tail -f storage/logs/laravel.log | grep slow_request');

        return 0;
    }
}
