<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the banks table aligned with MevonPay getBankList when possible, with optional static fallback.
 */
class BankDirectorySyncService
{
    public function __construct(
        private MevonPayBankService $mevonBanks,
    ) {}

    /**
     * @return array{source: string, upserted: int, message: string}
     */
    public function sync(bool $useFallbackIfApiFails = true): array
    {
        $rows = $this->mevonBanks->getBankList();
        if (is_array($rows) && $rows !== []) {
            $n = $this->upsertFromApiRows($rows);

            return [
                'source' => 'mevonpay_api',
                'upserted' => $n,
                'message' => "Synced {$n} banks from MevonPay getBankList.",
            ];
        }

        if (! $useFallbackIfApiFails) {
            return [
                'source' => 'none',
                'upserted' => 0,
                'message' => 'MevonPay bank list unavailable and fallback disabled.',
            ];
        }

        $n = $this->upsertFromCheckoutConfigBanks(config('banks', []));
        $extra = config('nigerian_banks_fallback', []);
        if (is_array($extra) && $extra !== []) {
            $n += $this->upsertFromExplicitFallbackRows($extra);
        }

        Log::info('banks.sync_fallback', ['upserted' => $n]);

        return [
            'source' => 'config_banks_php',
            'upserted' => $n,
            'message' => $n > 0
                ? "MevonPay list failed; upserted {$n} banks from config/banks.php (+ optional nigerian_banks_fallback)."
                : 'MevonPay list failed; config/banks.php had no rows to import.',
        ];
    }

    /**
     * @param  list<mixed>  $rows
     */
    private function upsertFromCheckoutConfigBanks(array $rows): int
    {
        $n = 0;
        foreach ($rows as $bank) {
            if (! is_array($bank)) {
                continue;
            }
            $code = $bank['code'] ?? null;
            $name = $bank['bank_name'] ?? $bank['name'] ?? null;
            if ($code === null || $name === null) {
                continue;
            }
            $code = preg_replace('/\D/', '', (string) $code) ?? '';
            $name = trim((string) $name);
            if ($code === '' || $name === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            Bank::query()->updateOrCreate(
                ['code' => $nip],
                ['name' => $name],
            );
            $n++;
        }

        return $n;
    }

    /**
     * @param  list<mixed>  $rows
     */
    private function upsertFromExplicitFallbackRows(array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = isset($row['code']) ? preg_replace('/\D/', '', (string) $row['code']) : '';
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            Bank::query()->updateOrCreate(
                ['code' => $nip],
                ['name' => $name],
            );
            $n++;
        }

        return $n;
    }

    /**
     * @param  list<mixed>  $rows
     */
    private function upsertFromApiRows(array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = $row['bankCode'] ?? $row['bank_code'] ?? $row['code'] ?? $row['BankCode'] ?? null;
            $name = $row['bankName'] ?? $row['bank_name'] ?? $row['name'] ?? $row['BankName'] ?? null;
            if ($code === null || $name === null) {
                continue;
            }
            $code = preg_replace('/\D/', '', (string) $code) ?? '';
            $name = trim((string) $name);
            if ($code === '' || $name === '') {
                continue;
            }
            $nip = NigerianBankCodeNormalizer::toNipTransferCode($code);
            Bank::query()->updateOrCreate(
                ['code' => $nip],
                ['name' => $name],
            );
            $n++;
        }

        return $n;
    }
}
