<?php

namespace App\Services\VtuNg;

use Illuminate\Support\Arr;

/**
 * Parse VTU.ng electricity purchase / requery / webhook payloads for status and token.
 */
final class VtuNgElectricityOrderParser
{
    /**
     * @param  array<string, mixed>|null  $apiResult  Parsed client result (`data`, `raw`, …)
     * @return array{
     *     status: string,
     *     request_id: string|null,
     *     order_id: int|string|null,
     *     electricity_token: string|null,
     *     units: string|null,
     *     customer_name: string|null,
     *     meter_number: string|null
     * }
     */
    public static function parse(?array $apiResult): array
    {
        $blob = self::mergePayload($apiResult);

        $meta = is_array($blob['meta_data'] ?? null) ? $blob['meta_data'] : [];
        if ($meta === [] && is_array($blob['meta'] ?? null)) {
            $meta = $blob['meta'];
        }

        $token = self::firstNonEmptyString([
            Arr::get($meta, 'electricity_token'),
            Arr::get($meta, 'token'),
            Arr::get($blob, 'electricity_token'),
            Arr::get($blob, 'token'),
        ]);

        $status = strtolower(trim((string) (
            $blob['status']
            ?? Arr::get($meta, 'status')
            ?? ($token !== null ? 'completed-api' : '')
        )));

        $requestId = self::firstNonEmptyString([
            $blob['request_id'] ?? null,
            $blob['reference'] ?? null,
            Arr::get($apiResult ?? [], 'raw.request_id'),
        ]);

        $orderId = $blob['order_id'] ?? $blob['transaction_id'] ?? $blob['id'] ?? null;
        if ($orderId === null || $orderId === '') {
            $orderId = null;
        }

        return [
            'status' => $status,
            'request_id' => $requestId,
            'order_id' => $orderId,
            'electricity_token' => $token,
            'units' => self::firstNonEmptyString([
                Arr::get($meta, 'units'),
                Arr::get($meta, 'unit'),
                Arr::get($blob, 'units'),
            ]),
            'customer_name' => self::firstNonEmptyString([
                Arr::get($meta, 'customer_name'),
                Arr::get($blob, 'customer_name'),
            ]),
            'meter_number' => self::firstNonEmptyString([
                Arr::get($meta, 'meter_number'),
                Arr::get($meta, 'customer_id'),
                Arr::get($blob, 'meter_number'),
                Arr::get($blob, 'customer_id'),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload  Raw webhook body
     */
    public static function parseWebhook(array $payload): array
    {
        $nested = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return self::parse([
            'data' => $nested !== [] ? $nested : $payload,
            'raw' => $payload,
        ]);
    }

    public static function isProcessingStatus(string $status): bool
    {
        if ($status === '') {
            return false;
        }

        $processing = array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            (array) config('vtu.electricity_processing_statuses', [])
        );

        return in_array($status, $processing, true);
    }

    public static function isCompletedStatus(string $status): bool
    {
        if ($status === '') {
            return false;
        }

        $completed = array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            (array) config('vtu.electricity_completed_statuses', [])
        );

        return in_array($status, $completed, true);
    }

    public static function isFailedStatus(string $status): bool
    {
        if ($status === '') {
            return false;
        }

        $failed = array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            (array) config('vtu.refund_statuses', [])
        );

        return in_array($status, $failed, true);
    }

    /**
     * Electricity orders without a token should stay pending until requery/webhook completes them.
     */
    public static function shouldStayPending(?array $parsed): bool
    {
        if ($parsed === null) {
            return true;
        }

        if (($parsed['electricity_token'] ?? null) !== null) {
            return false;
        }

        $status = (string) ($parsed['status'] ?? '');
        if (self::isFailedStatus($status)) {
            return false;
        }

        if (self::isCompletedStatus($status)) {
            return true;
        }

        if (self::isProcessingStatus($status)) {
            return true;
        }

        return $status === '';
    }

    /**
     * @param  array<string, mixed>|null  $apiResult
     * @return array<string, mixed>
     */
    private static function mergePayload(?array $apiResult): array
    {
        $out = [];
        foreach ([
            is_array($apiResult['raw'] ?? null) ? $apiResult['raw'] : [],
            is_array($apiResult['data'] ?? null) ? $apiResult['data'] : [],
            is_array($apiResult['raw']['data'] ?? null) ? $apiResult['raw']['data'] : [],
        ] as $chunk) {
            if ($chunk !== []) {
                $out = array_merge($out, $chunk);
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private static function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $value) {
            if ($value === null) {
                continue;
            }
            $s = trim((string) $value);
            if ($s !== '') {
                return $s;
            }
        }

        return null;
    }
}
