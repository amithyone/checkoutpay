<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        /** Merchant X-API-Key routes: limit by API key (fallback IP). */
        RateLimiter::for('merchant_api', function (Request $request) {
            $key = (string) ($request->header('X-API-Key') ?? $request->input('api_key') ?? '');
            $bucket = $key !== '' ? 'key:'.sha1($key) : 'ip:'.($request->ip() ?? '0');

            return Limit::perMinute(60)->by('merchant-api:'.$bucket);
        });

        /** Payout / name-enquiry: stricter per key. */
        RateLimiter::for('merchant_payout', function (Request $request) {
            $key = (string) ($request->header('X-API-Key') ?? $request->input('api_key') ?? '');
            $bucket = $key !== '' ? 'key:'.sha1($key) : 'ip:'.($request->ip() ?? '0');

            return Limit::perMinute(12)->by('merchant-payout:'.$bucket);
        });

        RateLimiter::for('consumer_wallet_otp', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            $key = sha1(($request->ip() ?? '0').'|'.$phone);

            return Limit::perMinute(6)->by($key);
        });

        /** Authenticated consumer app (wallet, history, utility pagination). */
        RateLimiter::for('consumer_wallet', function (Request $request) {
            $perMinute = max(60, (int) config('consumer_wallet.rate_limit_per_minute', 240));
            $key = $request->user()?->id ? 'u:'.$request->user()->id : 'ip:'.($request->ip() ?? '0');

            return Limit::perMinute($perMinute)->by('consumer-wallet:'.$key);
        });

        RateLimiter::for('support-poll', function (Request $request) {
            $token = (string) $request->route('token', '');
            $userKey = $request->user()?->id ? 'u:'.$request->user()->id : null;
            $key = $userKey ?? ($token !== '' ? 't:'.$token : 'ip:'.($request->ip() ?? '0'));

            return Limit::perMinute(max(30, (int) config('support.rate_limit_poll_per_minute', 120)))
                ->by('support-poll:'.$key);
        });

        RateLimiter::for('support-write', function (Request $request) {
            $token = (string) $request->route('token', '');
            $userKey = $request->user()?->id ? 'u:'.$request->user()->id : null;
            $key = $userKey ?? ($token !== '' ? 't:'.$token : 'ip:'.($request->ip() ?? '0'));

            return Limit::perMinute(max(10, (int) config('support.rate_limit_write_per_minute', 40)))
                ->by('support-write:'.$key);
        });

        RateLimiter::for('support-start', function (Request $request) {
            $userKey = $request->user()?->id ? 'u:'.$request->user()->id : 'ip:'.($request->ip() ?? '0');

            return Limit::perMinute(max(3, (int) config('support.rate_limit_start_per_minute', 15)))
                ->by('support-start:'.$userKey);
        });

        RateLimiter::for('support-options', function (Request $request) {
            $key = $request->user()?->id ? 'u:'.$request->user()->id : 'ip:'.($request->ip() ?? '0');

            return Limit::perMinute(60)->by('support-options:'.$key);
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(10)->by('admin-login:'.($request->ip() ?? '0'));
        });

        RateLimiter::for('ops_monitor', function (Request $request) {
            $key = (string) ($request->header('X-Ops-Key') ?? $request->ip() ?? '0');

            return Limit::perMinute(60)->by('ops-monitor:'.sha1($key));
        });

        /** Namecheap → Contabo bulk sync (HMAC key bucket, not shared api:60/min). */
        RateLimiter::for('live_sync', function (Request $request) {
            $keyId = trim((string) $request->header('X-LiveSync-Key', ''));
            $bucket = $keyId !== '' ? 'key:'.$keyId : 'ip:'.($request->ip() ?? '0');
            $perMinute = max(120, (int) config('services.live_sync.rate_limit_per_minute', 600));

            return Limit::perMinute($perMinute)->by('live-sync:'.$bucket);
        });

        $this->routes(function () {
            Route::middleware(['api', 'ops.monitor', 'throttle:ops_monitor'])
                ->prefix('ops/v1')
                ->group(base_path('routes/ops.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));


            // NigTax admin: alternate paths when nginx strips /api before PHP (see routes/tax_admin_fallback.php)
            Route::middleware('api')
                ->group(base_path('routes/tax_admin_fallback.php'));

            Route::middleware('api')
                ->group(base_path('routes/nigtax_public_fallback.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            // Decoy /admin (and configured honeypot path) — after real admin routes
            Route::middleware('web')
                ->group(base_path('routes/honeypot.php'));

            Route::middleware('web')
                ->group(base_path('routes/business.php'));
        });
    }
}
