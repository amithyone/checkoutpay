<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankAccountPrefixRule;
use Illuminate\Support\Carbon;

final class BankAccountSuggestionService
{
    public function __construct(
        private BankLogoService $bankLogos,
        private NubanValidationService $nuban,
    ) {}

    /**
     * Full prefix-rules catalog for CheckoutNow offline cache
     * (GET /rentals/banks/suggestions with no account).
     *
     * Same prefix may appear under multiple banks (and in flat `rules` / `prefix_map`).
     *
     * @return array{
     *   suggestions: list<array{name: string, codes: list<string>, category: string, prefixes: list<string>}>,
     *   rules: list<array{prefix: string, code: string, name: string, codes: list<string>, category: string}>,
     *   prefix_map: array<string, list<array{code: string, name: string, codes: list<string>, category: string}>>,
     *   updated_at: string
     * }
     */
    public function rulesCatalog(): array
    {
        $directory = $this->directoryIndex();
        $legacyByNip = $this->legacyCodesByNip();
        $defaultCategory = (string) config('bank_account_prefixes.default_category', 'Fintech & Neo-Bank');

        /** @var array<string, array{name: string, codes: array<string, true>, prefixes: array<string, true>, category: string}> $groups */
        $groups = [];
        /** @var list<array{prefix: string, nip: string, name: string, category: string}> $flat */
        $flat = [];

        foreach (BankAccountPrefixRule::rulesForSuggestions() as $rule) {
            $prefix = preg_replace('/\D+/', '', (string) ($rule['prefix'] ?? '')) ?? '';
            $rawCode = trim((string) ($rule['code'] ?? ''));
            if ($prefix === '' || strlen($prefix) < 2 || $rawCode === '') {
                continue;
            }

            $nip = NigerianBankCodeNormalizer::toNipTransferCode($rawCode);
            if ($nip === '') {
                continue;
            }

            $name = trim((string) ($rule['name'] ?? ''));
            $category = trim((string) ($rule['category'] ?? '')) ?: $defaultCategory;

            if (! isset($groups[$nip])) {
                $dirCode = $directory[$nip]['code'] ?? $nip;
                if ($name === '') {
                    $name = (string) ($directory[$nip]['name'] ?? $nip);
                }

                $codes = [$dirCode => true, $nip => true, $rawCode => true];
                foreach ($legacyByNip[$nip] ?? [] as $legacy) {
                    $codes[$legacy] = true;
                }

                $groups[$nip] = [
                    'name' => $name,
                    'codes' => $codes,
                    'prefixes' => [],
                    'category' => $category,
                ];
            }

            $groups[$nip]['prefixes'][$prefix] = true;
            if (trim((string) ($rule['name'] ?? '')) !== '') {
                $groups[$nip]['name'] = trim((string) $rule['name']);
                $name = $groups[$nip]['name'];
            }
            if (trim((string) ($rule['category'] ?? '')) !== '') {
                $groups[$nip]['category'] = trim((string) $rule['category']);
                $category = $groups[$nip]['category'];
            }

            $flat[] = [
                'prefix' => $prefix,
                'nip' => $nip,
                'name' => $name !== '' ? $name : $groups[$nip]['name'],
                'category' => $category,
            ];
        }

        $suggestions = [];
        /** @var array<string, list<string>> $codesByNip */
        $codesByNip = [];
        foreach ($groups as $nip => $group) {
            $prefixes = array_keys($group['prefixes']);
            sort($prefixes, SORT_STRING);
            $codes = $this->sortedCodeList(array_keys($group['codes']));
            $codesByNip[$nip] = $codes;

            $suggestions[] = [
                'name' => $group['name'],
                'codes' => $codes,
                'category' => $group['category'],
                'prefixes' => array_map('strval', array_values($prefixes)),
            ];
        }

        usort($suggestions, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        $rules = [];
        /** @var array<string, array<string, array{code: string, name: string, codes: list<string>, category: string}>> $prefixMapBuild */
        $prefixMapBuild = [];
        foreach ($flat as $row) {
            $nip = $row['nip'];
            $codes = $codesByNip[$nip] ?? [$nip];
            // Prefer directory / NIP code for `code` (exists on GET rentals/banks); keep aliases in `codes`.
            $primaryCode = (string) ($directory[$nip]['code'] ?? $nip);
            if (! in_array($primaryCode, $codes, true)) {
                array_unshift($codes, $primaryCode);
                $codes = array_values(array_unique($codes));
            }
            $rules[] = [
                'prefix' => (string) $row['prefix'],
                'code' => $primaryCode,
                'name' => (string) $row['name'],
                'codes' => $codes,
                'category' => (string) $row['category'],
            ];
            $prefixMapBuild[$row['prefix']][$nip] = [
                'code' => $primaryCode,
                'name' => (string) $row['name'],
                'codes' => $codes,
                'category' => (string) $row['category'],
            ];
        }

        usort($rules, function (array $a, array $b) {
            $len = strlen($b['prefix']) <=> strlen($a['prefix']);
            if ($len !== 0) {
                return $len;
            }
            $p = strcmp($a['prefix'], $b['prefix']);
            if ($p !== 0) {
                return $p;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $prefixMap = [];
        ksort($prefixMapBuild, SORT_STRING);
        foreach ($prefixMapBuild as $prefix => $banks) {
            $list = array_values($banks);
            usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
            $prefixMap[(string) $prefix] = $list;
        }

        $updatedAt = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('bank_account_prefix_rules')) {
            $updatedAt = BankAccountPrefixRule::query()->max('updated_at');
        }

        return [
            'suggestions' => $suggestions,
            'rules' => $rules,
            'prefix_map' => $prefixMap,
            'updated_at' => $updatedAt
                ? Carbon::parse($updatedAt)->utc()->toIso8601String()
                : now()->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function sortedCodeList(array $codes): array
    {
        usort($codes, function (string $a, string $b) {
            $la = strlen(preg_replace('/\D+/', '', $a) ?? '');
            $lb = strlen(preg_replace('/\D+/', '', $b) ?? '');
            if ($la !== $lb) {
                return $la <=> $lb;
            }

            return strcmp($a, $b);
        });

        return array_map('strval', array_values($codes));
    }

    /**
     * @return list<array{code: string, name: string, prefix: string|null, logo_url: string|null}>
     */
    public function suggest(string $accountInput): array
    {
        $digits = preg_replace('/\D+/', '', $accountInput) ?? '';
        if (strlen($digits) < 2) {
            return [];
        }

        $limit = max(1, min(12, (int) config('bank_account_prefixes.max_suggestions', 12)));
        /** @var list<array{nip: string, prefix: string, name: string}> $ordered */
        $ordered = [];

        foreach ($this->prefixMatches($digits) as $match) {
            $ordered[] = $match;
        }

        if (strlen($digits) === 10 && $this->nuban->isConfigured()) {
            foreach ($this->nubanNipsForAccount($digits) as $nip) {
                $ordered[] = ['nip' => $nip, 'prefix' => '', 'name' => ''];
            }
        }

        $unique = [];
        foreach ($ordered as $row) {
            $nip = $row['nip'];
            if ($nip === '' || isset($unique[$nip])) {
                continue;
            }
            $unique[$nip] = $row;
            if (count($unique) >= $limit) {
                break;
            }
        }

        return $this->resolveBankRows(array_values($unique));
    }

    /**
     * @return list<array{nip: string, prefix: string, name: string}>
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
                'prefix' => $prefix,
                'name' => isset($rule['name']) ? trim((string) $rule['name']) : '',
            ];
        }

        usort($matches, fn (array $a, array $b) => $b['prefix_len'] <=> $a['prefix_len']);

        $out = [];
        foreach ($matches as $match) {
            if (isset($out[$match['nip']])) {
                continue;
            }
            $out[$match['nip']] = [
                'nip' => $match['nip'],
                'prefix' => $match['prefix'],
                'name' => $match['name'],
            ];
        }

        return array_values($out);
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
     * @param  list<array{nip: string, prefix: string, name: string}>  $matches
     * @return list<array{code: string, name: string, prefix: string|null, logo_url: string|null}>
     */
    private function resolveBankRows(array $matches): array
    {
        if ($matches === []) {
            return [];
        }

        $directory = $this->directoryIndex();
        $quickNames = $this->quickBankNamesByNip();
        $fallbackNames = $this->fallbackNamesByNip();
        $rows = [];

        foreach ($matches as $match) {
            $nip = $match['nip'];
            $prefix = $match['prefix'] !== '' ? $match['prefix'] : null;
            $ruleName = trim((string) ($match['name'] ?? ''));

            if (isset($directory[$nip])) {
                $rows[] = [
                    'code' => $directory[$nip]['code'],
                    'name' => $ruleName !== '' ? $ruleName : $directory[$nip]['name'],
                    'prefix' => $prefix,
                    'logo_url' => $directory[$nip]['logo_url'],
                ];

                continue;
            }

            $name = $ruleName !== ''
                ? $ruleName
                : ($fallbackNames[$nip] ?? $quickNames[$nip] ?? $this->lookupBankName($nip));
            if ($name === null || $name === '') {
                continue;
            }

            $rows[] = [
                'code' => $nip,
                'name' => $name,
                'prefix' => $prefix,
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
     * @return array<string, list<string>>
     */
    private function legacyCodesByNip(): array
    {
        $map = config('nigerian_bank_legacy_to_nip', []);
        if (! is_array($map)) {
            return [];
        }

        $byNip = [];
        foreach ($map as $legacy => $nipRaw) {
            $nip = NigerianBankCodeNormalizer::toNipTransferCode((string) $nipRaw);
            $legacyDigits = preg_replace('/\D+/', '', (string) $legacy) ?? '';
            if ($nip === '' || $legacyDigits === '') {
                continue;
            }
            $byNip[$nip][] = $legacyDigits;
        }

        return $byNip;
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
