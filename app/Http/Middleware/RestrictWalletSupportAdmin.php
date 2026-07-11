<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wallet support can only use a fixed set of admin routes (view + limited support actions).
 */
class RestrictWalletSupportAdmin
{
    /**
     * @var list<string>
     */
    private array $allowedRoutePatterns = [
        'admin.dashboard',
        'admin.logout',
        'admin.sidebar-menu-order.*',
        'admin.app-sessions.*',
        'admin.whatsapp-wallet.index',
        'admin.whatsapp-wallet.wallets.index',
        'admin.whatsapp-wallet.wallets.show',
        'admin.whatsapp-wallet.wallets.push',
        'admin.whatsapp-wallet.wallets.devices.*',
        'admin.whatsapp-wallet.wallets.step-up.clear',
        'admin.whatsapp-wallet.transactions.index',
        'admin.whatsapp-wallet.transactions.show',
        'admin.whatsapp-wallet.transactions.p2p',
        'admin.whatsapp-wallet.transactions.pending',
        'admin.whatsapp-wallet.transactions.failed',
        'admin.whatsapp-wallet.transactions.check-status',
        'admin.whatsapp-wallet.transactions.check-electricity-status',
        'admin.whatsapp-wallet.money-requests.index',
        'admin.whatsapp-wallet.save-together.index',
        'admin.support.*',
        'admin.virtual-cards.index',
        'admin.virtual-cards.show',
        'admin.virtual-cards.stats',
        'admin.virtual-cards.logs',
        'admin.virtual-cards.users',
        'admin.virtual-cards.rate-tracker',
        'admin.virtual-cards.rate-tracker.data',
        'admin.businesses-kyc.index',
        'admin.businesses.show',
        'admin.businesses.verification.download',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof Admin || ! $admin->isWalletSupport()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName === null || ! $this->isAllowed($routeName)) {
            abort(403, 'Wallet support can view wallet tools and tickets, but cannot manage account or settings changes.');
        }

        return $next($request);
    }

    private function isAllowed(string $routeName): bool
    {
        foreach ($this->allowedRoutePatterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
