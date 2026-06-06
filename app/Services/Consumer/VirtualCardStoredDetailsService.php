<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;

final class VirtualCardStoredDetailsService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function extractFromWebhook(array $payload): ?array
    {
        $data = $payload['data'] ?? $payload;
        if (! is_array($data)) {
            return null;
        }

        $cardNumber = trim((string) ($data['card_number'] ?? $data['cardNumber'] ?? $data['pan'] ?? ''));
        $cvv = trim((string) ($data['cvv'] ?? $data['cvv2'] ?? ''));
        $expiry = trim((string) ($data['expiry'] ?? $data['expiry_date'] ?? ''));
        $expiry = str_replace('\\/', '/', $expiry);

        if ($cardNumber === '' && $cvv === '' && $expiry === '') {
            return null;
        }

        $billing = $data['billing_address'] ?? $data['billing'] ?? null;
        $balance = $data['balance'] ?? $data['card_balance'] ?? $data['available_balance'] ?? null;

        return [
            'card_number' => $cardNumber,
            'cvv' => $cvv,
            'expiry' => $expiry,
            'last_four' => trim((string) ($data['last4'] ?? $data['last_four'] ?? '')),
            'card_name' => trim((string) ($data['card_name'] ?? $data['name_on_card'] ?? '')),
            'brand' => strtolower(trim((string) ($data['card_brand'] ?? $data['brand'] ?? 'visa'))),
            'card_type' => trim((string) ($data['card_type'] ?? 'virtual')),
            'card_external_id' => trim((string) ($data['card_id'] ?? $data['cardId'] ?? $data['card_code'] ?? '')),
            'billing_address' => is_array($billing) ? $billing : null,
            'balance_usd' => is_numeric($balance) ? round((float) $balance, 2) : null,
            'provider_reference' => trim((string) ($data['reference'] ?? $data['request_id'] ?? '')),
            'synced_at' => now()->toIso8601String(),
            'source' => 'webhook',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistFromWebhook(VirtualCardRequest $row, array $payload): void
    {
        $extracted = $this->extractFromWebhook($payload);
        if ($extracted === null) {
            return;
        }

        $updates = [
            'card_details_payload' => $extracted,
        ];

        if ($extracted['balance_usd'] !== null) {
            $updates['card_balance_usd'] = $extracted['balance_usd'];
        }

        if ($extracted['card_name'] !== '' && trim((string) $row->card_name) === '') {
            $updates['card_name'] = $extracted['card_name'];
        }

        $row->update($updates);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveForRequest(VirtualCardRequest $row): ?array
    {
        $stored = $row->card_details_payload;
        if (is_array($stored) && $this->hasSensitiveFields($stored)) {
            return $stored;
        }

        $response = is_array($row->response_payload) ? $row->response_payload : [];
        $webhook = $response['webhook'] ?? null;
        if (is_array($webhook)) {
            $extracted = $this->extractFromWebhook($webhook);
            if ($extracted !== null) {
                $this->persistFromWebhook($row, $webhook);

                return $row->fresh()->card_details_payload;
            }
        }

        $fromLogs = $this->resolveFromActivationLogs($row);
        if ($fromLogs !== null) {
            $this->persistExtracted($row, $fromLogs);

            return $row->fresh()->card_details_payload;
        }

        return null;
    }

    public function backfillRequest(VirtualCardRequest $row): bool
    {
        if ($this->resolveForRequest($row) !== null) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFromActivationLogs(VirtualCardRequest $row): ?array
    {
        $logs = VirtualCardRequestLog::query()
            ->where('virtual_card_request_id', $row->id)
            ->whereIn('event', ['webhook_activated', 'webhook_received'])
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($logs as $log) {
            $context = is_array($log->context) ? $log->context : [];
            $payload = $context['raw_payload'] ?? null;
            if (! is_array($payload)) {
                continue;
            }

            $extracted = $this->extractFromWebhook($payload);
            if ($extracted !== null) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function persistExtracted(VirtualCardRequest $row, array $extracted): void
    {
        $updates = ['card_details_payload' => $extracted];
        if (($extracted['balance_usd'] ?? null) !== null) {
            $updates['card_balance_usd'] = $extracted['balance_usd'];
        }

        $row->update($updates);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function hasSensitiveFields(array $stored): bool
    {
        return trim((string) ($stored['card_number'] ?? '')) !== ''
            || trim((string) ($stored['cvv'] ?? '')) !== ''
            || trim((string) ($stored['expiry'] ?? '')) !== '';
    }
}
