<?php

namespace App\Services\Whatsapp;

/**
 * Normalizes MevonPay payout fields shown on WhatsApp and web bank-transfer receipts.
 */
final class WhatsappBankTransferReceiptDetails
{
    /**
     * @param  array<string, mixed>  $payoutResult
     * @return array{session_id: string, response_message: string, reference: string}
     */
    public static function fromPayoutResult(array $payoutResult, ?string $sentSessionId = null): array
    {
        $sessionId = self::resolveSessionId($payoutResult, $sentSessionId);
        $message = trim((string) ($payoutResult['response_message'] ?? ''));
        $reference = trim((string) ($payoutResult['reference'] ?? ''));

        return [
            'session_id' => $sessionId,
            'response_message' => $message,
            'reference' => $reference,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $payoutResult
     * @return array<string, mixed>
     */
    public static function mergeIntoMeta(array $meta, array $payoutResult, ?string $sentSessionId = null): array
    {
        $details = self::fromPayoutResult($payoutResult, $sentSessionId);

        if ($details['session_id'] !== '') {
            $meta['payout_session_id'] = $details['session_id'];
        }
        if ($details['response_message'] !== '') {
            $meta['payout_response_message'] = $details['response_message'];
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $payoutResult
     */
    public static function resolveSessionId(array $payoutResult, ?string $sentSessionId = null): string
    {
        if (! empty($payoutResult['session_id'])) {
            return trim((string) $payoutResult['session_id']);
        }

        $raw = $payoutResult['raw'] ?? null;
        if (is_array($raw)) {
            foreach (['sessionId', 'session_id', 'SessionId'] as $key) {
                if (! empty($raw[$key])) {
                    return trim((string) $raw[$key]);
                }
            }
        }

        $sent = trim((string) ($sentSessionId ?? ''));

        return $sent;
    }

    /**
     * Lines for WhatsApp markdown (always includes Session ID + Status labels).
     */
    public static function whatsappBlock(?string $sessionId, ?string $responseMessage): string
    {
        $sessionLine = '*Session ID:* '.self::displayValue($sessionId);
        $statusLine = '*Status:* '.self::displayValue($responseMessage);

        return "\n".$sessionLine."\n".$statusLine;
    }

    /**
     * Lines for PNG / plain-text receipts.
     *
     * @return list<string>
     */
    public static function plainLines(?string $sessionId, ?string $responseMessage): array
    {
        return [
            'Session ID: '.self::displayValue($sessionId),
            'Status: '.self::displayValue($responseMessage),
        ];
    }

    /**
     * @param  array{session_id?: string, response_message?: string, reference?: string}  $details
     * @return list<string>
     */
    public static function webReceiptRows(array $details): array
    {
        return [
            'Session ID' => self::displayValue($details['session_id'] ?? null),
            'Status' => self::displayValue($details['response_message'] ?? null),
            'Reference' => self::displayValue($details['reference'] ?? null),
        ];
    }

    private static function displayValue(?string $value): string
    {
        $t = trim((string) $value);

        return $t !== '' ? $t : '—';
    }
}
