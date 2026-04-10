<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Support\Str;

/**
 * Resolve Nigerian banks, name-enquiry, and send MavonPay createtransfer for WhatsApp wallet bank payouts.
 */
class WhatsappWalletBankPayoutService
{
    public function __construct(
        private MavonPayTransferService $mavon,
        private MevonPayBankService $bankService,
    ) {}

    public function isConfigured(): bool
    {
        return $this->mavon->isConfigured();
    }

    public function isNameEnquiryAvailable(): bool
    {
        return $this->bankService->isConfigured();
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
        if ($digits !== '' && strlen($digits) <= 6 && ctype_digit($digits)) {
            $candidates = [$digits, str_pad($digits, 3, '0', STR_PAD_LEFT)];
            $stripped = ltrim($digits, '0');
            if ($stripped !== '') {
                $candidates[] = str_pad($stripped, 3, '0', STR_PAD_LEFT);
            }
            foreach (array_unique($candidates) as $code) {
                $bank = Bank::query()->where('code', $code)->first();
                if ($bank) {
                    return ['code' => (string) $bank->code, 'name' => (string) $bank->name];
                }
            }
        }

        $bank = Bank::query()->whereRaw('LOWER(name) = LOWER(?)', [$t])->first();
        if ($bank) {
            return ['code' => (string) $bank->code, 'name' => (string) $bank->name];
        }

        $like = Bank::query()->where('name', 'like', '%'.$t.'%')->orderByRaw('LENGTH(name) asc')->first();

        return $like ? ['code' => (string) $like->code, 'name' => (string) $like->name] : null;
    }

    /**
     * @return array{account_name: string, bank_code: string}|null
     */
    public function nameEnquiry(string $bankCode, string $accountNumber): ?array
    {
        if (! $this->bankService->isConfigured()) {
            return null;
        }

        $ne = $this->bankService->nameEnquiry($bankCode, $accountNumber);
        if (! is_array($ne) || empty($ne['account_name'])) {
            return null;
        }

        return [
            'account_name' => (string) $ne['account_name'],
            'bank_code' => (string) ($ne['bank_code'] ?? $bankCode),
        ];
    }

    public function isWeakVerifiedName(string $accountName): bool
    {
        $l = strtolower($accountName);

        return str_contains($l, 'timeout fallback') || str_contains($l, 'verified (mevonpay');
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
