<?php

namespace App\Services\PartnerBanks;

final class PartnerBankRegistry
{
    /**
     * @return list<string>
     */
    public function enabledSlugs(): array
    {
        $enabled = config('partner_banks.enabled', ['mevonpay']);

        return is_array($enabled) ? array_values($enabled) : ['mevonpay'];
    }

    public function defaultSlug(): string
    {
        return trim((string) config('partner_banks.default', 'mevonpay')) ?: 'mevonpay';
    }

    public function isEnabled(string $slug): bool
    {
        $slug = trim(strtolower($slug));

        return in_array($slug, array_map('strtolower', $this->enabledSlugs()), true);
    }

    public function resolveActiveSlug(): string
    {
        $default = strtolower($this->defaultSlug());
        if ($this->isEnabled($default)) {
            return $default;
        }

        $first = $this->enabledSlugs()[0] ?? 'mevonpay';

        return strtolower(trim((string) $first)) ?: 'mevonpay';
    }
}
