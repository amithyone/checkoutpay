<?php

namespace App\Services\Broadcast;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FCM proximity nudge when a Pay at Shop till is broadcasting idle/presence.
 * Copy always uses merchant_name from broadcast_terminals — never POS hostname.
 */
final class BroadcastPayAtShopProximityPushService
{
    public function __construct(
        private ConsumerWalletPushNotificationService $walletPush,
    ) {}

    /**
     * POS-triggered nudge while presence beacon is active.
     *
     * @return array{ok: bool, sent: int, skipped: int, merchant_name: string, error?: string}
     */
    public function notifyFromTerminal(string $terminalId, string $sessionKind = 'presence'): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'sent' => 0, 'skipped' => 0, 'merchant_name' => '', 'error' => 'disabled'];
        }

        $terminal = DB::table('broadcast_terminals')
            ->where('terminal_id', $terminalId)
            ->where('active', 1)
            ->first(['terminal_id', 'merchant_name', 'business_id']);

        if ($terminal === null) {
            return ['ok' => false, 'sent' => 0, 'skipped' => 0, 'merchant_name' => '', 'error' => 'Unknown terminal_id'];
        }

        if (! $this->merchantPayAtShopActive((int) ($terminal->business_id ?? 0))) {
            return [
                'ok' => false,
                'sent' => 0,
                'skipped' => 0,
                'merchant_name' => (string) $terminal->merchant_name,
                'error' => 'Pay at shop is not active for this merchant',
            ];
        }

        $merchantName = $this->sanitizeMerchantName((string) $terminal->merchant_name);
        $kind = in_array($sessionKind, ['presence', 'pos_checkout'], true) ? $sessionKind : 'presence';

        $sent = 0;
        $skipped = 0;

        foreach ($this->pushEnabledWallets() as $wallet) {
            if ($this->isOnCooldown((int) $wallet->id, $terminalId)) {
                $skipped++;

                continue;
            }

            if ($this->walletPush->notifyPayAtShopProximity($wallet, $terminalId, $merchantName, $kind)) {
                $this->markCooldown((int) $wallet->id, $terminalId);
                $sent++;
            } else {
                $skipped++;
            }
        }

        Log::info('broadcast.pay_at_shop_proximity_push', [
            'terminal_id' => $terminalId,
            'merchant_name' => $merchantName,
            'session_kind' => $kind,
            'sent' => $sent,
            'skipped' => $skipped,
        ]);

        return [
            'ok' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'merchant_name' => $merchantName,
        ];
    }

    /**
     * Single-wallet nudge (mobile reports nearby till after BLE verify).
     */
    public function notifyWallet(
        WhatsappWallet $wallet,
        string $terminalId,
        string $sessionKind = 'presence',
    ): bool {
        if (! $this->enabled()) {
            return false;
        }

        $terminal = DB::table('broadcast_terminals')
            ->where('terminal_id', $terminalId)
            ->where('active', 1)
            ->first(['terminal_id', 'merchant_name', 'business_id']);

        if ($terminal === null || ! $this->merchantPayAtShopActive((int) ($terminal->business_id ?? 0))) {
            return false;
        }

        if ($this->isOnCooldown((int) $wallet->id, $terminalId)) {
            return false;
        }

        $merchantName = $this->sanitizeMerchantName((string) $terminal->merchant_name);
        $kind = in_array($sessionKind, ['presence', 'pos_checkout'], true) ? $sessionKind : 'presence';

        if (! $this->walletPush->notifyPayAtShopProximity($wallet, $terminalId, $merchantName, $kind)) {
            return false;
        }

        $this->markCooldown((int) $wallet->id, $terminalId);

        return true;
    }

    public function buildBody(string $merchantName): string
    {
        $name = trim($merchantName) !== '' ? trim($merchantName) : 'Shop';

        return sprintf('%s is open — tap to pay', $name);
    }

    public function buildTitle(): string
    {
        return (string) config('broadcast.pay_at_shop_proximity_push_title', 'Checkout Nearby Available');
    }

    /** Never show Windows/macOS BLE local names to customers. */
    public function looksLikeComputerName(string $name): bool
    {
        $n = trim($name);
        if ($n === '' || strlen($n) > 64) {
            return false;
        }

        if (preg_match('/^(DESKTOP|LAPTOP|WIN|WORKGROUP|MACBOOK|IMAC|PC|MS-|USER-)[-A-Z0-9_]+$/i', $n)) {
            return true;
        }

        if (preg_match('/\.local$/i', $n)) {
            return true;
        }

        if (preg_match('/^[A-Z0-9][A-Z0-9-]{2,20}$/i', $n) && str_contains($n, '-') && ! str_contains($n, ' ')) {
            return true;
        }

        return false;
    }

    private function sanitizeMerchantName(string $merchantName): string
    {
        $name = trim($merchantName);

        return $this->looksLikeComputerName($name) ? 'Shop' : ($name !== '' ? $name : 'Shop');
    }

    private function enabled(): bool
    {
        return (bool) config('broadcast.pay_at_shop_proximity_push_enabled', true);
    }

    private function cooldownSeconds(): int
    {
        return max(60, (int) config('broadcast.pay_at_shop_proximity_push_cooldown_seconds', 300));
    }

    private function cacheKey(int $walletId, string $terminalId): string
    {
        return 'pay_at_shop_proximity_push:'.$walletId.':'.strtolower(trim($terminalId));
    }

    private function isOnCooldown(int $walletId, string $terminalId): bool
    {
        return Cache::has($this->cacheKey($walletId, $terminalId));
    }

    private function markCooldown(int $walletId, string $terminalId): void
    {
        Cache::put($this->cacheKey($walletId, $terminalId), 1, $this->cooldownSeconds());
    }

    private function merchantPayAtShopActive(int $businessId): bool
    {
        if ($businessId <= 0) {
            return true;
        }

        $business = DB::table('businesses')
            ->where('id', $businessId)
            ->first(['broadcast_pay_at_shop_enabled', 'broadcast_pay_at_shop_active', 'is_active']);

        return $business !== null
            && (bool) $business->is_active
            && (bool) $business->broadcast_pay_at_shop_enabled
            && (bool) $business->broadcast_pay_at_shop_active;
    }

    /**
     * @return \Illuminate\Support\Collection<int, WhatsappWallet>
     */
    private function pushEnabledWallets()
    {
        $walletIds = ConsumerWalletApiAccount::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('whatsapp_wallet_id')
            ->filter()
            ->unique()
            ->values();

        if ($walletIds->isEmpty()) {
            return collect();
        }

        return WhatsappWallet::query()
            ->whereIn('id', $walletIds)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->get();
    }
}
