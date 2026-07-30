<?php

namespace App\Services\Broadcast;

use Illuminate\Support\Facades\DB;

/**
 * Accept bank_name_hash from POS when it matches the terminal primary name
 * or any known alternate slug (e.g. CheckoutPay vs RUBIES MFB during rollout).
 */
class BroadcastBankNameHashMatcher
{
    public static function hashBankName(string $bankName): string
    {
        return 'sha256:'.hash('sha256', strtolower(trim($bankName)));
    }

    /**
     * @return array{matched: bool, matched_bank_name: ?string, acceptable_hashes: list<string>}
     */
    public function evaluate(string $receivedHash, object $terminal): array
    {
        $acceptable = $this->acceptableBankNames($terminal);
        $hashes = [];
        $hashToName = [];

        foreach ($acceptable as $name) {
            $hash = self::hashBankName($name);
            $hashes[] = $hash;
            $hashToName[$hash] = $name;
        }

        if ($receivedHash !== '' && isset($hashToName[$receivedHash])) {
            return [
                'matched' => true,
                'matched_bank_name' => $hashToName[$receivedHash],
                'acceptable_hashes' => array_values(array_unique($hashes)),
            ];
        }

        return [
            'matched' => false,
            'matched_bank_name' => null,
            'acceptable_hashes' => array_values(array_unique($hashes)),
        ];
    }

    /**
     * @return list<string>
     */
    public function acceptableBankNames(object $terminal): array
    {
        $names = [];

        if (! empty($terminal->bank_name)) {
            $names[] = (string) $terminal->bank_name;
        }

        if (! empty($terminal->business_id)) {
            $business = DB::table('businesses')
                ->where('id', $terminal->business_id)
                ->first(['bank_name', 'rubies_business_bank_name']);

            if ($business) {
                if (! empty($business->bank_name)) {
                    $names[] = (string) $business->bank_name;
                }
                if (! empty($business->rubies_business_bank_name)) {
                    $names[] = (string) $business->rubies_business_bank_name;
                }
            }

            $accountBank = DB::table('account_numbers')
                ->where('business_id', $terminal->business_id)
                ->where('is_active', 1)
                ->where('is_pool', 0)
                ->where('is_external', 0)
                ->orderBy('id')
                ->value('bank_name');
            if (is_string($accountBank) && $accountBank !== '') {
                $names[] = $accountBank;
            }
        }

        $bankCode = (string) ($terminal->recipient_bank_code ?? '');
        foreach ($this->aliasesForBankCode($bankCode) as $alias) {
            $names[] = $alias;
        }

        // CheckoutPay-branded terminals: POS SDK default is often "CheckoutPay" or "kuda".
        if (str_starts_with((string) ($terminal->terminal_id ?? ''), 'CP-')) {
            $names[] = 'CheckoutPay';
            $names[] = 'checkoutpay';
        }

        $names = array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => trim($name),
            $names,
        ))));

        return $names;
    }

    /**
     * @return list<string>
     */
    private function aliasesForBankCode(string $bankCode): array
    {
        $aliases = config('broadcast.bank_name_aliases', []);

        return is_array($aliases[$bankCode] ?? null) ? $aliases[$bankCode] : [];
    }
}
