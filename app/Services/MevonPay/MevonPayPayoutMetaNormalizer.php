<?php

namespace App\Services\MevonPay;

use App\Services\MavonPayTransferService;
use App\Services\Whatsapp\WhatsappBankTransferReceiptDetails;

/**
 * Builds the structured MevonPay payload stored on WhatsApp wallet transaction meta (admin "Meta raw").
 */
final class MevonPayPayoutMetaNormalizer
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $payoutResult  Normalized payout result (bucket, raw, response_message, …)
     * @return array<string, mixed>
     */
    public static function mergeIntoMeta(array $meta, array $payoutResult, ?string $sentSessionId = null): array
    {
        $meta = WhatsappBankTransferReceiptDetails::mergeIntoMeta($meta, $payoutResult, $sentSessionId);

        $bucket = (string) ($meta['payout_bucket'] ?? $payoutResult['bucket'] ?? '');
        $refunded = ! empty($meta['reversed_at']) || ! empty($meta['payout_failed']);

        $payload = self::buildPayload($payoutResult, $bucket, $refunded);
        if (isset($meta['mevonpay']) && is_array($meta['mevonpay'])) {
            $payload['initial_payout'] = $meta['mevonpay']['initial_payout'] ?? $meta['mevonpay'];
        }
        $meta['mevonpay'] = $payload;

        if (! empty($payoutResult['response_code'])) {
            $meta['payout_response_code'] = $payoutResult['response_code'];
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $payoutResult
     * @return array{
     *   status: string,
     *   message: string,
     *   api_response: array<string, mixed>,
     *   curl_error: string,
     *   http_status: int|null,
     *   payout_api: string|null
     * }
     */
    public static function buildPayload(array $payoutResult, string $bucket = '', bool $refunded = false): array
    {
        if ($bucket === '') {
            $bucket = (string) ($payoutResult['bucket'] ?? MavonPayTransferService::BUCKET_FAILED);
        }

        $responseMessage = trim((string) ($payoutResult['response_message'] ?? ''));
        $curlError = trim((string) ($payoutResult['curl_error'] ?? ''));
        $httpStatus = isset($payoutResult['http_status']) ? (int) $payoutResult['http_status'] : null;

        return [
            'status' => self::bucketToStatus($bucket),
            'message' => self::humanMessage($bucket, $responseMessage, $refunded),
            'api_response' => self::extractApiResponse($payoutResult),
            'curl_error' => $curlError,
            'http_status' => $httpStatus,
            'payout_api' => isset($payoutResult['payout_api']) ? (string) $payoutResult['payout_api'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payoutResult
     * @return array<string, mixed>
     */
    public static function extractApiResponse(array $payoutResult): array
    {
        $raw = $payoutResult['raw'] ?? null;
        $base = [];

        if (is_array($raw)) {
            if (isset($raw['api_response']) && is_array($raw['api_response'])) {
                $base = $raw['api_response'];
            } elseif (isset($raw['details']) && is_array($raw['details'])) {
                $base = $raw['details'];
            } else {
                $base = $raw;
            }
        }

        $sessionId = WhatsappBankTransferReceiptDetails::resolveSessionId($payoutResult, null);
        $reference = trim((string) ($payoutResult['reference'] ?? ''));
        $code = trim((string) ($payoutResult['response_code'] ?? ''));
        $message = trim((string) ($payoutResult['response_message'] ?? ''));

        $mapped = self::mapApiResponseFields($base);

        if (is_array($raw) && isset($raw['details']) && is_array($raw['details'])) {
            $mapped = array_merge(self::mapApiResponseFields($raw['details']), $mapped);
        }

        if ($sessionId !== '' && empty($mapped['sessionId'])) {
            $mapped['sessionId'] = $sessionId;
        }
        if ($reference !== '' && empty($mapped['reference'])) {
            $mapped['reference'] = $reference;
        }
        if ($code !== '' && empty($mapped['responseCode'])) {
            $mapped['responseCode'] = $code;
        }
        if ($message !== '' && empty($mapped['responseMessage'])) {
            $mapped['responseMessage'] = $message;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function mapApiResponseFields(array $raw): array
    {
        $aliases = [
            'sessionId' => ['sessionId', 'session_id', 'SessionId'],
            'amount' => ['amount', 'Amount'],
            'contractReference' => ['contractReference', 'contract_reference', 'ContractReference'],
            'creditAccount' => ['creditAccount', 'creditAccountNumber', 'credit_account', 'credit_account_number'],
            'creditAccountName' => ['creditAccountName', 'credit_account_name', 'CreditAccountName'],
            'debitAccountNumber' => ['debitAccountNumber', 'debit_account_number', 'debitAccount', 'DebitAccountNumber'],
            'narration' => ['narration', 'Narration'],
            'reference' => ['reference', 'Reference', 'payout_reference'],
            'responseMessage' => ['responseMessage', 'response_message', 'message', 'Message'],
            'responseCode' => ['responseCode', 'response_code', 'code', 'Code'],
            'transactionStatus' => ['transactionStatus', 'transaction_status', 'TransactionStatus'],
            'bankCode' => ['bankCode', 'bank_code', 'BankCode'],
            'bankName' => ['bankName', 'bank_name', 'BankName'],
            'paymentReference' => ['paymentReference', 'payment_reference', 'PaymentReference'],
            'debitAccountName' => ['debitAccountName', 'debit_account_name', 'DebitAccountName'],
        ];

        $out = [];
        foreach ($aliases as $canonical => $keys) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $raw)) {
                    continue;
                }
                $val = $raw[$key];
                if ($val === null || $val === '') {
                    continue;
                }
                $out[$canonical] = is_scalar($val) ? (string) $val : $val;
                break;
            }
        }

        return $out;
    }

    private static function bucketToStatus(string $bucket): string
    {
        return match ($bucket) {
            MavonPayTransferService::BUCKET_SUCCESSFUL => 'successful',
            MavonPayTransferService::BUCKET_PENDING => 'pending',
            default => 'failed',
        };
    }

    private static function humanMessage(string $bucket, string $responseMessage, bool $refunded): string
    {
        if ($bucket === MavonPayTransferService::BUCKET_FAILED && $refunded) {
            return 'Transfer failed. Funds reversed.';
        }

        if ($responseMessage !== '') {
            return $responseMessage;
        }

        return match ($bucket) {
            MavonPayTransferService::BUCKET_SUCCESSFUL => 'Transfer successful.',
            MavonPayTransferService::BUCKET_PENDING => 'Bank transfer processing.',
            default => 'Transfer failed.',
        };
    }

    /**
     * Bank session id from MevonPay raw api_response only (not payout reference / sent session).
     *
     * @param  array<string, mixed>  $payoutResult
     */
    public static function apiSessionIdFromPayoutResult(array $payoutResult): string
    {
        $raw = $payoutResult['raw'] ?? null;
        if (! is_array($raw)) {
            return '';
        }

        $candidates = [];
        if (isset($raw['api_response']) && is_array($raw['api_response'])) {
            $candidates[] = $raw['api_response'];
        }
        if (isset($raw['details']) && is_array($raw['details'])) {
            $candidates[] = $raw['details'];
        }
        $candidates[] = $raw;

        foreach ($candidates as $api) {
            foreach (['sessionId', 'session_id', 'SessionId'] as $key) {
                $v = trim((string) ($api[$key] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return '';
    }
}
