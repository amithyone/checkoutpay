<?php

namespace App\Services\Vtu;

use App\Contracts\Vtu\VtuProviderContract;
use App\Models\Setting;

final class VtuProviderResolver
{
    public const PROVIDER_VTU_NG = 'vtu_ng';

    public const PROVIDER_MEVONPAY = 'mevonpay';

    public function __construct(
        private VtuNgProvider $vtuNg,
        private MevonPayVtuProvider $mevonPay,
    ) {}

    public function activeKey(): string
    {
        $key = (string) Setting::get('vtu_provider', self::PROVIDER_VTU_NG);
        if (! in_array($key, [self::PROVIDER_VTU_NG, self::PROVIDER_MEVONPAY], true)) {
            return self::PROVIDER_VTU_NG;
        }

        return $key;
    }

    public function active(): VtuProviderContract
    {
        return $this->forKey($this->activeKey());
    }

    public function forKey(string $key): VtuProviderContract
    {
        return match ($key) {
            self::PROVIDER_MEVONPAY => $this->mevonPay,
            default => $this->vtuNg,
        };
    }

    /**
     * @return array<string, VtuProviderContract>
     */
    public function all(): array
    {
        return [
            self::PROVIDER_VTU_NG => $this->vtuNg,
            self::PROVIDER_MEVONPAY => $this->mevonPay,
        ];
    }
}
