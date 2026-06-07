<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;

final class VirtualCardProviderResponseService
{
    public function __construct(
        private VirtualCardStoredDetailsService $storedDetails,
    ) {}
    /**
     * MevonPay async reference (UUID) used in card.created webhooks.
     *
     * @param  array{ok?: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    public function extractProviderReference(array $api): ?string
    {
        $sources = [
            $api['data'] ?? null,
            is_array($api['raw'] ?? null) ? ($api['raw']['data'] ?? $api['raw']) : null,
        ];

        foreach ($sources as $data) {
            if (! is_array($data)) {
                continue;
            }

            foreach (['reference', 'request_id', 'requestId', 'order_id', 'order_reference', 'id'] as $key) {
                $value = trim((string) ($data[$key] ?? ''));
                if ($value !== '' && $this->looksLikeProviderReference($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    public function extractCardId(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        $id = (string) ($data['card_id'] ?? $data['cardId'] ?? $data['card_code'] ?? $data['cardCode'] ?? $data['id'] ?? '');

        return trim($id) !== '' ? $id : null;
    }

    /**
     * MevonPay may return HTTP 200 with async "processed successfully" while status is not true.
     *
     * @param  array{ok?: bool, message?: string, data?: mixed, raw?: mixed, http_status?: int}  $api
     */
    public function isCreateAccepted(array $api): bool
    {
        if ($api['ok'] ?? false) {
            return true;
        }

        if ($this->extractCardId($api['data'] ?? null) !== null) {
            return true;
        }

        $message = strtolower(trim((string) ($api['message'] ?? '')));
        $asyncPhrases = [
            'processed successfully',
            'request submitted',
            'request received',
            'being processed',
            'creation request',
            'card request',
            'submitted successfully',
        ];
        foreach ($asyncPhrases as $phrase) {
            if ($phrase !== '' && str_contains($message, $phrase)) {
                return true;
            }
        }

        $raw = $api['raw'] ?? null;
        if (is_array($raw)) {
            $nested = $raw['data'] ?? null;
            if (is_array($nested) && $this->extractCardId($nested) !== null) {
                return true;
            }
        }

        return ($api['http_status'] ?? 0) === 200 && str_contains($message, 'success');
    }

    /**
     * @param  array{ok: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    public function applyAccepted(VirtualCardRequest $row, array $api): VirtualCardRequest
    {
        $cardId = $this->extractCardId($api['data'] ?? null);
        if ($cardId === null && is_array($api['raw'] ?? null)) {
            $rawData = $api['raw']['data'] ?? null;
            $cardId = $this->extractCardId(is_array($rawData) ? $rawData : null);
        }

        if ($cardId !== null) {
            return $this->applySuccess($row, $api);
        }

        return $this->applyPreparing($row, $api);
    }

    /**
     * @param  array{ok: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    public function applyPreparing(VirtualCardRequest $row, array $api): VirtualCardRequest
    {
        $row->update([
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'provider_reference' => $this->extractProviderReference($api) ?? $row->provider_reference,
            'response_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
            'card_external_id' => null,
            'failure_reason' => null,
        ]);

        return $row->fresh();
    }

    /**
     * @param  array{ok: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    public function applySuccess(VirtualCardRequest $row, array $api): VirtualCardRequest
    {
        $row->update([
            'status' => VirtualCardRequest::STATUS_SUBMITTED,
            'provider_reference' => $this->extractProviderReference($api) ?? $row->provider_reference,
            'response_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
            'card_external_id' => $this->extractCardId($api['data'] ?? null),
            'failure_reason' => null,
        ]);

        return $row->fresh();
    }

    /**
     * @param  array{ok: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    public function applyFailure(VirtualCardRequest $row, array $api, string $reason): VirtualCardRequest
    {
        $row->update([
            'status' => VirtualCardRequest::STATUS_FAILED,
            'failure_reason' => $reason,
            'response_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : null,
        ]);

        return $row->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyWebhookReady(VirtualCardRequest $row, array $payload, ?string $cardId): VirtualCardRequest
    {
        $cardId = trim((string) ($cardId ?? '')) !== '' ? trim((string) $cardId) : $row->card_external_id;

        $updates = [
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'card_external_id' => $cardId,
            'activated_at' => $row->activated_at ?? now(),
            'failure_reason' => null,
            'response_payload' => array_merge(
                is_array($row->response_payload) ? $row->response_payload : [],
                ['webhook' => $payload],
            ),
        ];

        $mevonRequestId = $this->extractMevonRequestId($payload);
        if ($mevonRequestId !== null) {
            $updates['provider_reference'] = $mevonRequestId;
        }

        $balance = $this->storedDetails->extractBalanceFromProviderPayload($payload);
        if ($balance !== null) {
            $updates['card_balance_usd'] = $balance;
        }

        $row->update($updates);

        $fresh = $row->fresh();
        $this->storedDetails->persistFromWebhook($fresh, $payload);

        return $fresh->fresh();
    }

    /**
     * Mevon card.created.success carries REQ… in data.request_id (used by /V1/card_balance).
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractMevonRequestId(array $payload): ?string
    {
        foreach ([
            data_get($payload, 'data.request_id'),
            data_get($payload, 'data.requestId'),
            data_get($payload, 'request_id'),
            data_get($payload, 'requestId'),
        ] as $value) {
            $id = trim((string) $value);
            if ($this->looksLikeProviderReference($id) && preg_match('/^REQ\d{6,}$/i', $id) === 1) {
                return strtoupper($id);
            }
        }

        return null;
    }

    private function looksLikeProviderReference(string $value): bool
    {
        if (str_starts_with(strtoupper($value), 'VCARD-')) {
            return false;
        }

        if (preg_match('/^REQ\d{6,}$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^req_[a-z0-9]+$/i', $value) === 1) {
            return true;
        }

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }
}
