<?php

namespace App\Providers;

use App\Services\Admin\AdminSidebarMenu;
use App\Support\SiteBranding;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        $this->configurePublicUrlFromRequest();
        $this->configureSiteBranding();

        // Register Telegram notification channel
        $this->app->make(ChannelManager::class)->extend('telegram', function ($app) {
            return new TelegramChannel();
        });

        $this->registerSqlQueryFirewall();

        View::composer('layouts.admin', function ($view): void {
            $admin = auth('admin')->user();
            $view->with(
                'adminSidebarMenu',
                $admin ? app(AdminSidebarMenu::class)->itemsFor($admin) : []
            );
        });

        View::composer([
            'rentals.index',
            'rentals.show',
            'tickets.index',
            'memberships.index',
        ], function ($view): void {
            try {
                $default = '#000000';
                $rentalsColor = (string) \App\Models\Setting::get('rentals_accent_color', $default);
                $view->with([
                    'rentalsColor' => $rentalsColor,
                    'ticketsColor' => (string) \App\Models\Setting::get('tickets_accent_color', $rentalsColor),
                    'membershipsColor' => (string) \App\Models\Setting::get('memberships_accent_color', $rentalsColor),
                ]);
            } catch (\Throwable) {
                $view->with([
                    'rentalsColor' => '#000000',
                    'ticketsColor' => '#000000',
                    'membershipsColor' => '#000000',
                ]);
            }
        });

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

    /**
     * When APP_URL is still localhost on a live host (common after cPanel deploys),
     * generate route()/url() links from the domain the visitor actually used.
     */
    protected function configureSiteBranding(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        try {
            $name = SiteBranding::name();
            Config::set('app.name', $name);
            Config::set('mail.from.name', $name);
            View::share('siteName', $name);
        } catch (\Throwable) {
            // DB unavailable during early boot / migrate
        }
    }

    protected function configurePublicUrlFromRequest(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        if (! $this->app->bound('request')) {
            return;
        }

        $configured = rtrim((string) config('app.url'), '/');
        if ($configured === '') {
            return;
        }

        $configuredHost = strtolower((string) parse_url($configured, PHP_URL_HOST));
        $isLocalConfigured = in_array($configuredHost, ['', 'localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($configuredHost, '.local')
            || str_ends_with($configuredHost, '.test');

        if (! $isLocalConfigured) {
            return;
        }

        $request = request();
        $requestHost = strtolower($request->getHost());
        if ($requestHost === '' || in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true)) {
            return;
        }

        $scheme = $request->header('X-Forwarded-Proto') === 'https' || $request->isSecure()
            ? 'https'
            : $request->getScheme();

        URL::forceRootUrl($scheme.'://'.$requestHost);
        if ($scheme === 'https') {
            URL::forceScheme('https');
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
