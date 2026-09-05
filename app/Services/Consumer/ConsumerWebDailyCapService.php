<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ConsumerWebDailyCapService
{
    public function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.web_daily_transfer_cap_enabled', true);
    }

    public function capNgn(): float
    {
        return max(0.0, (float) config('consumer_wallet.web_daily_transfer_cap_ngn', 10000));
    }

    public function isWebRequest(Request $request): bool
    {
        $ctx = app(ConsumerAppSessionService::class)->clientContextFromRequest($request);

        return ($ctx['platform'] ?? null) === 'web';
    }

    /**
     * @return array{applies: bool, cap: float, used: float, remaining: float}
     */
    public function meta(ConsumerWalletApiAccount $account, Request $request): array
    {
        $applies = $this->isEnabled() && $this->isWebRequest($request);
        $used = $this->usedToday($account);

        return [
            'applies' => $applies,
            'cap' => $this->capNgn(),
            'used' => $used,
            'remaining' => max(0.0, $this->capNgn() - $used),
        ];
    }

    public function rejectIfExceeded(ConsumerWalletApiAccount $account, Request $request, float $amount): ?JsonResponse
    {
        if (! $this->isEnabled() || ! $this->isWebRequest($request)) {
            return null;
        }

        $this->resetIfNeeded($account);
        $cap = $this->capNgn();
        $used = $this->usedToday($account);
        if ($used + $amount <= $cap + 0.0001) {
            return null;
        }

        $remaining = max(0.0, $cap - $used);

        return response()->json([
            'success' => false,
            'message' => 'Web wallet daily send limit is ₦'.number_format($cap, 2).
                ' (₦'.number_format($remaining, 2).' left today). Use the CheckoutNow phone app for higher limits.',
            'code' => 'web_daily_cap',
            'data' => [
                'web_daily_transfer_cap' => $cap,
                'web_daily_transfer_used' => $used,
                'web_daily_transfer_remaining' => $remaining,
            ],
        ], 403);
    }

    public function record(ConsumerWalletApiAccount $account, Request $request, float $amount): void
    {
        if ($amount <= 0 || ! $this->isEnabled() || ! $this->isWebRequest($request) || ! $this->hasColumns()) {
            return;
        }

        $this->resetIfNeeded($account);
        $account->web_daily_transfer_total = round(((float) $account->web_daily_transfer_total) + $amount, 2);
        $account->save();
    }

    public function usedToday(ConsumerWalletApiAccount $account): float
    {
        if (! $this->hasColumns()) {
            return 0.0;
        }

        $this->resetIfNeeded($account);

        return (float) ($account->web_daily_transfer_total ?? 0);
    }

    private function resetIfNeeded(ConsumerWalletApiAccount $account): void
    {
        if (! $this->hasColumns()) {
            return;
        }

        $today = Carbon::now((string) config('app.timezone', 'Africa/Lagos'))->toDateString();
        $for = $account->web_daily_transfer_for_date;
        $forStr = $for instanceof Carbon ? $for->toDateString() : (is_string($for) ? $for : null);

        if ($forStr !== $today) {
            $account->web_daily_transfer_total = 0;
            $account->web_daily_transfer_for_date = $today;
            $account->save();
        }
    }

    private function hasColumns(): bool
    {
        return Schema::hasColumn('consumer_wallet_api_accounts', 'web_daily_transfer_total')
            && Schema::hasColumn('consumer_wallet_api_accounts', 'web_daily_transfer_for_date');
    }
}
