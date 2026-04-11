<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Support\Str;

/**
 * Resolve Nigerian banks, name-enquiry, and send MavonPay createtransfer for WhatsApp wallet bank payouts.
 */
class WhatsappWalletBankPayoutService
{
    private const QUICK_BANK_MIN_SCORE = 4;

    public function __construct(
        private MavonPayTransferService $mavon,
        private MevonPayBankService $bankService,
        private NubanValidationService $nuban,
    ) {}

    public function isConfigured(): bool
    {
        return $this->mavon->isConfigured();
    }

    public function isNameEnquiryAvailable(): bool
    {
        return $this->bankService->isConfigured() || $this->nuban->isConfigured();
    }

    /**
     * @return list<array{code: string, label: string, aliases: list<string>}>
     */
    public function quickBanks(): array
    {
        $rows = config('whatsapp_wallet_quick_banks', []);

        return is_array($rows) ? $rows : [];
    }

    public function quickBankPageSize(): int
    {
        return (int) config('whatsapp.wallet.bank_picker_page_size', 8);
    }

    /**
     * @return list<array{code: string, label: string, aliases: list<string>}>
     */
    public function quickBankPageSlice(int $page): array
    {
        $all = $this->quickBanks();
        $size = $this->quickBankPageSize();
        $page = max(0, $page);
        $offset = $page * $size;

        return array_slice($all, $offset, $size);
    }

    public function quickBankPageCount(): int
    {
        $all = $this->quickBanks();
        $size = $this->quickBankPageSize();
        if ($all === [] || $size < 1) {
            return 1;
        }

        return (int) ceil(count($all) / $size);
    }

    public function quickBankLastPageIndex(): int
    {
        return max(0, $this->quickBankPageCount() - 1);
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public function resolveQuickBankGlobalNumber(int $globalIndex1based): ?array
    {
        $all = $this->quickBanks();
        if ($globalIndex1based < 1 || $globalIndex1based > count($all)) {
            return null;
        }
        $row = $all[$globalIndex1based - 1];

        return $this->resolvedBankPair((string) $row['code'], (string) $row['label']);
    }

    public function transferBankPickerMessage(int $page): string
    {
        $total = count($this->quickBanks());
        if ($total === 0) {
            return "*Bank transfer — pick bank*\n\n".
                "Type the *bank name* (e.g. *Access Bank*) or the *bank code* from your app (usually 3–6 digits).\n\n".
                '*BACK* — cancel';
        }

        $last = $this->quickBankLastPageIndex();
        $page = max(0, min($page, $last));
        $slice = $this->quickBankPageSlice($page);
        $n = count($slice);
        $start = $page * $this->quickBankPageSize() + 1;
        $end = $start + $n - 1;

        $lines = [
            '*Bank transfer — pick bank*',
            '',
            'Reply with the *list number* (e.g. *5*), type part of the bank name (e.g. *Access* or *GTBank*),',
            'or send the *3–6 digit bank code* from your banking app.',
            '',
        ];

        foreach ($slice as $i => $row) {
            $num = $start + $i;
            $lines[] = '*'.$num.'* — '.$row['label'];
        }

        $lines[] = '';
        $lines[] = 'Showing *'.$start.'–'.$end.'* of *'.$total.'* popular banks.';
        if ($page < $last) {
            $lines[] = '*MORE* — next banks';
        }
        if ($page > 0) {
            $lines[] = '*PREV* — previous banks';
        }
        $lines[] = '';
        $lines[] = '*BACK* — cancel';

        return implode("\n", $lines);
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public function resolveBankFromUserInput(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $t) ?? '';
        if ($digits !== '' && strlen($digits) >= 3 && strlen($digits) <= 8 && ctype_digit($digits)) {
            $candidates = array_unique(array_filter([
                $digits,
                str_pad($digits, 3, '0', STR_PAD_LEFT),
                str_pad($digits, 6, '0', STR_PAD_LEFT),
            ]));
            $stripped = ltrim($digits, '0');
            if ($stripped !== '' && $stripped !== $digits) {
                $candidates[] = str_pad($stripped, 3, '0', STR_PAD_LEFT);
                $candidates[] = str_pad($stripped, 6, '0', STR_PAD_LEFT);
            }
            foreach ($candidates as $code) {
                $bank = Bank::query()->where('code', $code)->first();
                if ($bank) {
                    return $this->resolvedBankPair((string) $bank->code, (string) $bank->name);
                }
            }
            $nipTry = NigerianBankCodeNormalizer::toNipTransferCode($digits);
            if ($nipTry !== '') {
                $bank = Bank::query()->where('code', $nipTry)->first();
                if ($bank) {
                    return $this->resolvedBankPair((string) $bank->code, (string) $bank->name);
                }
            }
        }

        $normalized = $this->normalizeBankSearchText($t);

        $quick = $this->quickBanks();
        if ($quick !== []) {
            $bestRow = null;
            $bestScore = 0;
            foreach ($quick as $row) {
                $label = (string) ($row['label'] ?? '');
                $aliases = isset($row['aliases']) && is_array($row['aliases']) ? $row['aliases'] : [];
                $hay = $this->normalizeBankSearchText($label.' '.implode(' ', $aliases));
                $score = $this->bankNameMatchScore($normalized, $hay);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRow = $row;
                }
            }
            if ($bestRow !== null && $bestScore >= self::QUICK_BANK_MIN_SCORE) {
                return $this->resolvedBankPair((string) $bestRow['code'], (string) $bestRow['label']);
            }
        }

        $bank = Bank::query()->whereRaw('LOWER(name) = LOWER(?)', [$t])->first();
        if ($bank) {
            return $this->resolvedBankPair((string) $bank->code, (string) $bank->name);
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/', $normalized) ?: [],
            fn (string $w) => strlen($w) > 2
        ));

        if ($tokens !== []) {
            $query = Bank::query()->where(function ($q) use ($tokens) {
                foreach ($tokens as $tok) {
                    $q->orWhere('name', 'like', '%'.$tok.'%');
                }
            });
            $best = null;
            $bestScore = 0;
            foreach ($query->limit(40)->get() as $candidate) {
                $hay = $this->normalizeBankSearchText((string) $candidate->name);
                $score = $this->bankNameMatchScore($normalized, $hay);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                }
            }
            if ($best !== null && $bestScore >= self::QUICK_BANK_MIN_SCORE) {
                return $this->resolvedBankPair((string) $best->code, (string) $best->name);
            }
        }

        $like = Bank::query()->where('name', 'like', '%'.$t.'%')->orderByRaw('LENGTH(name) asc')->first();

        return $like ? $this->resolvedBankPair((string) $like->code, (string) $like->name) : null;
    }

    /**
     * @return array{code: string, name: string}
     */
    private function resolvedBankPair(string $code, string $name): array
    {
        return [
            'code' => NigerianBankCodeNormalizer::toNipTransferCode($code),
            'name' => $name,
        ];
    }

    private function normalizeBankSearchText(string $text): string
    {
        $s = strtolower(trim($text));
        $s = preg_replace('/[^a-z0-9\s]/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }

    private function bankNameMatchScore(string $needleNorm, string $corpusNorm): int
    {
        if ($needleNorm === '' || $corpusNorm === '') {
            return 0;
        }
        if ($needleNorm === $corpusNorm) {
            return 1000;
        }
        if (str_contains($corpusNorm, $needleNorm)) {
            return 500 + min(strlen($needleNorm), 40);
        }

        $score = 0;
        foreach (preg_split('/\s+/', $needleNorm) ?: [] as $tok) {
            if (strlen($tok) < 2) {
                continue;
            }
            if (str_contains($corpusNorm, $tok)) {
                $score += strlen($tok);
            }
        }

        return $score;
    }

    /**
     * Resolve account holder name via MevonPay (with bank-code variants), then NUBAN — same strategy as admin account validation.
     *
     * @return array{account_name: string, bank_code: string}|null
     */
    public function nameEnquiry(string $bankCode, string $accountNumber): ?array
    {
        $bankCode = NigerianBankCodeNormalizer::toNipTransferCode($bankCode);

        $acct = preg_replace('/\D/', '', $accountNumber) ?? '';
        if (strlen($acct) !== 10) {
            return null;
        }

        if ($this->bankService->isConfigured()) {
            foreach ($this->mevonNameEnquiryBankCodeCandidates($bankCode) as $code) {
                $ne = $this->bankService->nameEnquiry($code, $acct);
                if (! is_array($ne) || ($ne['account_name'] ?? '') === '') {
                    continue;
                }
                $name = trim((string) $ne['account_name']);
                if ($name === '' || $this->isWeakVerifiedName($name)) {
                    continue;
                }

                return [
                    'account_name' => $name,
                    'bank_code' => (string) ($ne['bank_code'] ?? $code),
                ];
            }
        }

        if ($this->nuban->isConfigured()) {
            $n = $this->nuban->validate($acct, $bankCode);
            if (is_array($n) && ! empty($n['account_name'])) {
                $name = trim((string) $n['account_name']);
                if ($name !== '' && ! $this->isWeakVerifiedName($name)) {
                    return [
                        'account_name' => $name,
                        'bank_code' => (string) ($n['bank_code'] ?? $bankCode),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * MevonPay/NIBSS sometimes expect 3-digit legacy codes; fintechs use 6-digit. Try a small set of variants.
     *
     * @return list<string>
     */
    private function mevonNameEnquiryBankCodeCandidates(string $bankCode): array
    {
        $raw = trim($bankCode);
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return $raw !== '' ? [$raw] : [];
        }

        $out = [$raw, $digits, str_pad($digits, 3, '0', STR_PAD_LEFT), str_pad($digits, 6, '0', STR_PAD_LEFT)];
        $stripped = ltrim($digits, '0');
        if ($stripped !== '' && $stripped !== $digits) {
            $out[] = $stripped;
            $out[] = str_pad($stripped, 6, '0', STR_PAD_LEFT);
            $out[] = str_pad($stripped, 3, '0', STR_PAD_LEFT);
        }

        $out = array_values(array_unique(array_filter($out, fn (string $c) => $c !== '')));

        return array_slice($out, 0, 8);
    }

    public function isWeakVerifiedName(string $accountName): bool
    {
        $l = strtolower($accountName);

        return str_contains($l, 'timeout fallback')
            || str_contains($l, 'verified (mevonpay')
            || str_contains($l, 'n/a')
            || $l === 'null'
            || str_contains($l, 'could not verify');
    }

    /**
     * @return array{bucket: string, response_code: ?string, response_message: ?string, reference: ?string, raw: mixed}
     */
    public function sendTransfer(
        float $amount,
        string $bankCode,
        string $bankName,
        string $accountNumber,
        string $accountName,
        string $reference
    ): array {
        $bankCode = NigerianBankCodeNormalizer::toNipTransferCode($bankCode);

        $sessionId = 'WAW'.now()->format('YmdHis').Str::upper(Str::random(4));

        return $this->mavon->createTransfer([
            'amount' => $amount,
            'bankCode' => $bankCode,
            'bankName' => $bankName,
            'creditAccountName' => $accountName,
            'creditAccountNumber' => $accountNumber,
            'narration' => 'WhatsApp wallet bank transfer',
            'reference' => $reference,
            'sessionId' => $sessionId,
        ]);
    }

    public function makeWalletPayoutReference(): string
    {
        return 'waw_'.Str::lower(Str::random(14));
    }
}
