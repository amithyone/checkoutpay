<?php

namespace App\Services\WalletImport;

use App\Models\Bank;
use App\Services\NigerianBankCodeNormalizer;
use Illuminate\Support\Facades\Cache;

/**
 * Resolve messy CSV bank labels to NIP/CBN codes.
 */
final class FormCsvBankResolver
{
    public function resolve(?string $rawLabel): ?array
    {
        $raw = trim((string) $rawLabel);
        if ($raw === '') {
            return null;
        }

        $normalized = $this->normalizeLabel($raw);
        if ($normalized === '') {
            return null;
        }

        $aliases = config('wallet_import_banks.aliases', []);
        if (isset($aliases[$normalized]) && is_string($aliases[$normalized]) && $aliases[$normalized] !== '') {
            $code = NigerianBankCodeNormalizer::toNipTransferCode($aliases[$normalized]);

            return [
                'bank_code' => $code,
                'bank_label' => $raw,
                'resolved_via' => 'alias',
                'normalized' => $normalized,
            ];
        }

        // Compact form without spaces (normalize already strips most punctuation).
        $compact = preg_replace('/\s+/', '', $normalized) ?? $normalized;
        if ($compact !== $normalized && isset($aliases[$compact])) {
            $code = NigerianBankCodeNormalizer::toNipTransferCode((string) $aliases[$compact]);

            return [
                'bank_code' => $code,
                'bank_label' => $raw,
                'resolved_via' => 'alias',
                'normalized' => $compact,
            ];
        }

        $fromDb = $this->resolveFromBanksTable($raw, $normalized);
        if ($fromDb !== null) {
            return $fromDb;
        }

        return null;
    }

    public function normalizeLabel(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = str_replace(['&', '/', '.', ',', '(', ')', '-', '_'], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        // Drop trailing corporate noise.
        $s = preg_replace('/\b(plc|limited|ltd|nigeria|nig|bank|mfb)\b/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        // U.B.A / U B A → uba
        $s = preg_replace('/\b([a-z])\s+([a-z])\s+([a-z])\b/u', '$1$2$3', $s) ?? $s;
        $s = str_replace(' ', '', $s);
        // gtbank / firstbank → gtb / first (suffix "bank" glued)
        $s = preg_replace('/bank$/u', '', $s) ?? $s;
        $s = preg_replace('/mfb$/u', '', $s) ?? $s;

        return $s;
    }

    /**
     * @return array{bank_code: string, bank_label: string, resolved_via: string, normalized: string}|null
     */
    private function resolveFromBanksTable(string $raw, string $normalized): ?array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('banks')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        /** @var list<array{name: string, code: string}> $banks */
        $banks = Cache::remember('wallet_import_banks_table_v1', 3600, function () {
            return Bank::query()
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->get(['name', 'code'])
                ->map(fn (Bank $b) => [
                    'name' => (string) $b->name,
                    'code' => (string) $b->code,
                ])
                ->all();
        });

        $best = null;
        $bestScore = 0;
        foreach ($banks as $row) {
            $nameNorm = $this->normalizeLabel($row['name']);
            if ($nameNorm === '' || $row['code'] === '') {
                continue;
            }
            if ($nameNorm === $normalized) {
                return [
                    'bank_code' => NigerianBankCodeNormalizer::toNipTransferCode($row['code']),
                    'bank_label' => $raw,
                    'resolved_via' => 'banks_table_exact',
                    'normalized' => $normalized,
                ];
            }
            if (str_contains($nameNorm, $normalized) || str_contains($normalized, $nameNorm)) {
                $score = min(strlen($nameNorm), strlen($normalized));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $row;
                }
            }
        }

        if ($best !== null && $bestScore >= 4) {
            return [
                'bank_code' => NigerianBankCodeNormalizer::toNipTransferCode($best['code']),
                'bank_label' => $raw,
                'resolved_via' => 'banks_table_fuzzy',
                'normalized' => $normalized,
            ];
        }

        return null;
    }
}
