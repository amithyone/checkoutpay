<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;

final class VirtualCardProviderResponseService
{
    public function extractCardId(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        $id = (string) ($data['card_id'] ?? $data['cardId'] ?? $data['card_code'] ?? $data['cardCode'] ?? $data['id'] ?? '');

        return trim($id) !== '' ? $id : null;
    }

    /**
     * @param  array{ok: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    public function applySuccess(VirtualCardRequest $row, array $api): VirtualCardRequest
    {
        $row->update([
            'status' => VirtualCardRequest::STATUS_SUBMITTED,
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
}
