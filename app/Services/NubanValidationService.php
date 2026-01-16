<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NubanValidationService
{
    private const API_KEY = 'NUBAN-RCWHXKRD3766';
    private const BASE_URL = 'https://app.nuban.com.ng/api';
    private const POSSIBLE_BANKS_URL = 'https://app.nuban.com.ng/possible-banks';

    /**
     * Validate account number using only account number
     * Returns account details if valid, null otherwise
     *
     * @param string $accountNumber
     * @return array|null
     */
    public function validateAccountNumber(string $accountNumber): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::BASE_URL . '/' . self::API_KEY, [
                'acc_no' => $accountNumber,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if account is valid
                if (isset($data['account_name']) && !empty($data['account_name'])) {
                    return [
                        'account_number' => $accountNumber,
                        'account_name' => $data['account_name'] ?? null,
                        'bank_name' => $data['bank_name'] ?? null,
                        'bank_code' => $data['bank_code'] ?? null,
                        'valid' => true,
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('NUBAN validation error', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate account number with bank code (faster)
     *
     * @param string $accountNumber
     * @param string $bankCode
     * @return array|null
     */
    public function validateAccountNumberWithBankCode(string $accountNumber, string $bankCode): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::BASE_URL . '/' . self::API_KEY, [
                'bank_code' => $bankCode,
                'acc_no' => $accountNumber,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['account_name']) && !empty($data['account_name'])) {
                    return [
                        'account_number' => $accountNumber,
                        'account_name' => $data['account_name'] ?? null,
                        'bank_name' => $data['bank_name'] ?? null,
                        'bank_code' => $data['bank_code'] ?? $bankCode,
                        'valid' => true,
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('NUBAN validation error with bank code', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get possible banks for an account number
     *
     * @param string $accountNumber
     * @return array
     */
    public function getPossibleBanks(string $accountNumber): array
    {
        try {
            $response = Http::timeout(10)->get(self::POSSIBLE_BANKS_URL . '/' . self::API_KEY, [
                'acc_no' => $accountNumber,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['banks'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('NUBAN possible banks error', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Validate and get account name
     * This is the main method to use for validation
     *
     * @param string $accountNumber
     * @param string|null $bankCode
     * @return array|null Returns account details or null if invalid
     */
    public function validate(string $accountNumber, ?string $bankCode = null): ?array
    {
        // Remove any spaces or dashes from account number
        $accountNumber = preg_replace('/[^0-9]/', '', $accountNumber);

        // Validate account number length (should be 10 digits)
        if (strlen($accountNumber) !== 10) {
            return null;
        }

        if ($bankCode) {
            return $this->validateAccountNumberWithBankCode($accountNumber, $bankCode);
        }

        return $this->validateAccountNumber($accountNumber);
    }
}
