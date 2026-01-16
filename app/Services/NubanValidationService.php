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

            $statusCode = $response->status();
            $responseBody = $response->body();
            $data = $response->json();

            Log::info('NUBAN API Response (account only)', [
                'account_number' => $accountNumber,
                'status_code' => $statusCode,
                'response' => $data,
            ]);

            if ($response->successful()) {
                // Check if API returned an error
                if (isset($data['error']) && $data['error'] === true) {
                    Log::warning('NUBAN API returned error', [
                        'account_number' => $accountNumber,
                        'message' => $data['message'] ?? 'Unknown error',
                    ]);
                    return null;
                }

                // Handle different response structures
                $accountName = $data['account_name'] ?? $data['name'] ?? $data['accountName'] ?? null;
                $bankName = $data['bank_name'] ?? $data['bankName'] ?? $data['bank'] ?? null;
                $bankCode = $data['bank_code'] ?? $data['bankCode'] ?? $data['code'] ?? null;
                
                // Check if account is valid (account name exists and is not empty)
                if (!empty($accountName)) {
                    return [
                        'account_number' => $accountNumber,
                        'account_name' => $accountName,
                        'bank_name' => $bankName,
                        'bank_code' => $bankCode,
                        'valid' => true,
                    ];
                }
            }

            // Log failed response for debugging
            Log::warning('NUBAN validation failed', [
                'account_number' => $accountNumber,
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('NUBAN validation error', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

            $statusCode = $response->status();
            $responseBody = $response->body();
            $data = $response->json();

            Log::info('NUBAN API Response (with bank code)', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'status_code' => $statusCode,
                'response' => $data,
            ]);

            if ($response->successful()) {
                // Check if API returned an error
                if (isset($data['error']) && $data['error'] === true) {
                    Log::warning('NUBAN API returned error (with bank code)', [
                        'account_number' => $accountNumber,
                        'bank_code' => $bankCode,
                        'message' => $data['message'] ?? 'Unknown error',
                    ]);
                    return null;
                }

                // Handle different response structures
                $accountName = $data['account_name'] ?? $data['name'] ?? $data['accountName'] ?? null;
                $bankName = $data['bank_name'] ?? $data['bankName'] ?? $data['bank'] ?? null;
                $returnedBankCode = $data['bank_code'] ?? $data['bankCode'] ?? $data['code'] ?? $bankCode;
                
                // Check if account is valid (account name exists and is not empty)
                if (!empty($accountName)) {
                    return [
                        'account_number' => $accountNumber,
                        'account_name' => $accountName,
                        'bank_name' => $bankName,
                        'bank_code' => $returnedBankCode,
                        'valid' => true,
                    ];
                }
            }

            // Log failed response for debugging
            Log::warning('NUBAN validation failed (with bank code)', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('NUBAN validation error with bank code', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

        // If bank code is provided, try with that bank first
        if ($bankCode) {
            $result = $this->validateAccountNumberWithBankCode($accountNumber, $bankCode);
            if ($result && $result['valid']) {
                return $result;
            }

            // If validation failed with provided bank code, try possible banks
            Log::info('Validation failed with provided bank code, trying possible banks', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);
        }

        // Try without bank code first
        $result = $this->validateAccountNumber($accountNumber);
        if ($result && $result['valid']) {
            return $result;
        }

        // If that fails, try possible banks endpoint
        $possibleBanks = $this->getPossibleBanks($accountNumber);
        if (!empty($possibleBanks)) {
            // Try validating with each possible bank
            foreach ($possibleBanks as $bank) {
                $bankCodeToTry = $bank['bank_code'] ?? $bank['destbankcode'] ?? null;
                if ($bankCodeToTry) {
                    $result = $this->validateAccountNumberWithBankCode($accountNumber, $bankCodeToTry);
                    if ($result && $result['valid']) {
                        Log::info('Validation succeeded with possible bank', [
                            'account_number' => $accountNumber,
                            'bank_code' => $bankCodeToTry,
                            'bank_name' => $bank['name'] ?? null,
                        ]);
                        return $result;
                    }
                }
            }
        }

        return null;
    }
}
