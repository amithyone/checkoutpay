<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use App\Notifications\Channels\TelegramChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Telegram notification channel
        $this->app->make(ChannelManager::class)->extend('telegram', function ($app) {
            return new TelegramChannel();
        });

        $this->registerSqlQueryFirewall();

        // Warm up critical caches on application boot (for fast server)
        // This ensures caches are populated immediately, reducing first-request latency
        if (app()->environment('production')) {
            try {
                $this->warmUpCaches();
            } catch (\Exception $e) {
                // Silently fail - don't break app if cache warm-up fails
                Log::warning('Cache warm-up failed on boot', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function registerSqlQueryFirewall(): void
    {
        if (!config('security.query_firewall.enabled', true)) {
            return;
        }

        $runningInConsole = $this->app->runningInConsole();
        $blockInConsole = config('security.query_firewall.block_in_console', false);

        if ($runningInConsole && !$blockInConsole) {
            return;
        }

        $patterns = config('security.query_firewall.patterns', []);

        DB::listen(function (QueryExecuted $query) use ($patterns): void {
            $sql = strtolower(trim($query->sql));

            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $sql) !== 1) {
                    continue;
                }

                Log::critical('Blocked dangerous SQL statement by query firewall', [
                    'sql' => $query->sql,
                    'connection' => $query->connectionName,
                    'ip' => request()?->ip(),
                    'url' => request()?->fullUrl(),
                ]);

                throw new \RuntimeException('Blocked dangerous SQL statement.');
            }
        });
    }

    /**
     * Warm up critical application caches
     */
    protected function warmUpCaches(): void
    {
        // Pre-load homepage page data
        \App\Models\Page::getBySlug('home');

        // Pre-load critical settings
        \App\Models\Setting::get('site_favicon');
        \App\Models\Setting::get('site_logo');
        \App\Models\Setting::get('site_name');

        // Pre-load account number service caches
        // We'll populate cache keys directly to avoid calling protected methods
        try {
            $cache = \Illuminate\Support\Facades\Cache::getStore();
            
            // Warm up pool accounts cache
            if (!\Illuminate\Support\Facades\Cache::has(\App\Services\AccountNumberService::CACHE_KEY_POOL_ACCOUNTS)) {
                \App\Models\AccountNumber::pool()
                    ->active()
                    ->orderBy('id')
                    ->get();
            }

            // Warm up pending accounts cache (will be populated on first use)
            // This is done by the service internally, so we just ensure cache is ready
        } catch (\Exception $e) {
            // Silently fail - don't break app if cache warm-up fails
        }
    }
}
