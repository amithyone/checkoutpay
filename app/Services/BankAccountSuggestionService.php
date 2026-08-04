<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankAccountPrefixRule;

final class BankAccountSuggestionService
{
    public function __construct(
        private BankLogoService $bankLogos,
        private NubanValidationService $nuban,
    ) {}

    /**
     * @return list<array{code: string, name: string, logo_url: string|null}>
     */
    public function suggest(string $accountInput): array
    {
        $digits = preg_replace('/\D+/', '', $accountInput) ?? '';
        if (strlen($digits) < 2) {
            return [];
        }

        $limit = max(1, min(12, (int) config('bank_account_prefixes.max_suggestions', 12)));
        $orderedNips = [];

        foreach ($this->prefixMatches($digits) as $nip) {
            $orderedNips[] = $nip;
        }

        if (strlen($digits) === 10 && $this->nuban->isConfigured()) {
            foreach ($this->nubanNipsForAccount($digits) as $nip) {
                $orderedNips[] = $nip;
            }
        }

        $uniqueNips = [];
        foreach ($orderedNips as $nip) {
            if ($nip === '' || isset($uniqueNips[$nip])) {
                continue;
            }
            $uniqueNips[$nip] = true;
            if (count($uniqueNips) >= $limit) {
                break;
            }
        }

        return $this->resolveBankRows(array_keys($uniqueNips));
    }

    /**
     * @return list<string> NIP codes, longest prefix match first.
     */
    private function prefixMatches(string $digits): array
    {
        $rules = BankAccountPrefixRule::rulesForSuggestions();
        if ($rules === []) {
            return [];
        }

        $matches = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $prefix = preg_replace('/\D+/', '', (string) ($rule['prefix'] ?? '')) ?? '';
            $code = (string) ($rule['code'] ?? '');
            if ($prefix === '' || strlen($prefix) < 2 || $code === '') {
                continue;
            }
            if (! str_starts_with($digits, $prefix)) {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            if ($nip === '') {
                continue;
            }
            $matches[] = [
                'prefix_len' => strlen($prefix),
                'nip' => $nip,
                'name' => isset($rule['name']) ? trim((string) $rule['name']) : '',
            ];
        }

        usort($matches, fn (array $a, array $b) => $b['prefix_len'] <=> $a['prefix_len']);

        $out = [];
        foreach ($matches as $match) {
            $out[$match['nip']] = true;
        }

        return array_keys($out);
    }

    /**
     * @return list<string>
     */
    private function nubanNipsForAccount(string $accountNumber): array
    {
        $banks = $this->nuban->getPossibleBanks($accountNumber);
        if (! is_array($banks) || $banks === []) {
            return [];
        }

        $out = [];
        foreach ($banks as $bank) {
            if (! is_array($bank)) {
                continue;
            }
            $raw = $bank['bankCode'] ?? $bank['bank_code'] ?? $bank['code'] ?? $bank['destbankcode'] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode((string) $raw);
            if ($nip !== '') {
                $out[] = $nip;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $nips
     * @return list<array{code: string, name: string, logo_url: string|null}>
     */
    private function resolveBankRows(array $nips): array
    {
        if ($nips === []) {
            return [];
        }

        $directory = $this->directoryIndex();
        $quickNames = $this->quickBankNamesByNip();
        $fallbackNames = $this->fallbackNamesByNip();
        $rows = [];

        foreach ($nips as $nip) {
            if (isset($directory[$nip])) {
                $rows[] = $directory[$nip];

                continue;
            }

            $name = $fallbackNames[$nip] ?? $quickNames[$nip] ?? $this->lookupBankName($nip);
            if ($name === null || $name === '') {
                continue;
            }

            $rows[] = [
                'code' => $nip,
                'name' => $name,
                'logo_url' => $this->lookupLogoUrl($nip),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{code: string, name: string, logo_url: string|null}>
     */
    private function directoryIndex(): array
    {
        static $index = null;
        if (is_array($index)) {
            return $index;
        }

        $index = [];
        foreach ($this->bankLogos->listForApi() as $row) {
            $code = (string) ($row['code'] ?? '');
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            if ($nip === '') {
                continue;
            }
            $index[$nip] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? ''),
                'logo_url' => $row['logo_url'] ?? null,
            ];
        }

        return $index;
    }

    /**
     * @return array<string, string>
     */
    private function quickBankNamesByNip(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = [];
        foreach (config('whatsapp_wallet_quick_banks', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = (string) ($row['code'] ?? '');
            $label = trim((string) ($row['label'] ?? ''));
            if ($code === '' || $label === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            if ($nip !== '') {
                $map[$nip] = $label;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function fallbackNamesByNip(): array
    {
        $map = [];
        foreach (BankAccountPrefixRule::rulesForSuggestions() as $rule) {
            $code = (string) ($rule['code'] ?? '');
            $name = trim((string) ($rule['name'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            if ($nip !== '') {
                $map[$nip] = $name;
            }
        }

        return $map;
    }

    private function lookupBankName(string $nip): ?string
    {
        $bank = Bank::query()->where('code', $nip)->first(['name']);

        return $bank ? (string) $bank->name : null;
    }

    private function lookupLogoUrl(string $nip): ?string
    {
        $bank = Bank::query()
            ->where('code', $nip)
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->first(['logo_path']);

        return $bank instanceof Bank ? $bank->logoUrl() : null;
    }
}
