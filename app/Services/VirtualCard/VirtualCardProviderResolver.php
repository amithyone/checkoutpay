<?php

namespace App\Services\VirtualCard;

use App\Contracts\VirtualCard\VirtualCardProviderContract;
use App\Models\Setting;

final class VirtualCardProviderResolver
{
    public const PROVIDER_MEVONPAY = 'mevonpay';

    public const PROVIDER_CASHWYRE = 'cashwyre';

    public function __construct(
        private MevonPayVirtualCardProvider $mevonPay,
        private CashwyreVirtualCardProvider $cashwyre,
    ) {}

    public function activeKey(): string
    {
        $key = (string) Setting::get('virtual_card_provider', self::PROVIDER_MEVONPAY);
        if (! in_array($key, [self::PROVIDER_MEVONPAY, self::PROVIDER_CASHWYRE], true)) {
            return self::PROVIDER_MEVONPAY;
        }

        return $key;
    }

    public function active(): VirtualCardProviderContract
    {
        return $this->forKey($this->activeKey());
    }

    public function forKey(string $key): VirtualCardProviderContract
    {
        return match ($key) {
            self::PROVIDER_CASHWYRE => $this->cashwyre,
            default => $this->mevonPay,
        };
    }

    /**
     * @return array<string, VirtualCardProviderContract>
     */
    public function all(): array
    {
        return [
            self::PROVIDER_MEVONPAY => $this->mevonPay,
            self::PROVIDER_CASHWYRE => $this->cashwyre,
        ];
    }
}
