<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use Illuminate\Support\Facades\Log;

final class VirtualCardMevonWebhookService
{
    public function __construct(
        private VirtualCardProviderResponseService $providerResponse,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function tryFulfillFromWebhook(array $payload): bool
    {
        if (! $this->isCardEvent($payload)) {
            return false;
        }

        $cardId = $this->extractWebhookCardId($payload);
        $reference = $this->extractWebhookReference($payload);
        $email = strtolower(trim((string) data_get($payload, 'data.email', data_get($payload, 'data.customer_email', ''))));
        $phone = $this->normalizePhone((string) data_get($payload, 'data.phone_number', data_get($payload, 'data.phoneNumber', '')));

        $query = VirtualCardRequest::query()
            ->whereIn('status', [
                VirtualCardRequest::STATUS_PENDING,
                VirtualCardRequest::STATUS_PREPARING,
                VirtualCardRequest::STATUS_SUBMITTED,
            ]);

        if ($reference !== '') {
            $query->where('external_reference', $reference);
        } elseif ($cardId !== '') {
            $query->where(function ($q) use ($cardId) {
                $q->where('card_external_id', $cardId)
                    ->orWhereNull('card_external_id');
            });
        } else {
            $query->whereNull('card_external_id');
        }

        $candidates = $query->latest('id')->limit(20)->get();
        $row = null;

        foreach ($candidates as $candidate) {
            if ($reference !== '' && $candidate->external_reference === $reference) {
                $row = $candidate;
                break;
            }

            $requestPayload = is_array($candidate->request_payload) ? $candidate->request_payload : [];
            $reqEmail = strtolower(trim((string) ($requestPayload['email'] ?? '')));
            $reqPhone = $this->normalizePhone((string) ($requestPayload['phoneNumber'] ?? ''));

            if ($email !== '' && $reqEmail !== '' && $email === $reqEmail) {
                $row = $candidate;
                break;
            }
            if ($phone !== '' && $reqPhone !== '' && $phone === $reqPhone) {
                $row = $candidate;
                break;
            }
        }

        if (! $row && $candidates->count() === 1) {
            $row = $candidates->first();
        }

        if (! $row) {
            Log::warning('virtual_card.webhook.no_match', [
                'event' => data_get($payload, 'event'),
                'reference' => $reference,
                'card_id' => $cardId,
            ]);

            return false;
        }

        $this->providerResponse->applyWebhookReady($row, $payload, $cardId !== '' ? $cardId : null);

        Log::info('virtual_card.webhook.activated', [
            'virtual_card_request_id' => $row->id,
            'wallet_id' => $row->whatsapp_wallet_id,
            'card_external_id' => $row->fresh()->card_external_id,
            'event' => data_get($payload, 'event'),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCardEvent(array $payload): bool
    {
        $event = strtolower(trim((string) data_get($payload, 'event', data_get($payload, 'eventType', ''))));
        if ($event === '') {
            return false;
        }

        $cardEvents = [
            'card.created',
            'card_created',
            'card.create',
            'virtual_card.created',
            'virtual_card_created',
            'card.success',
            'card.ready',
            'card.active',
            'card_creation.success',
            'card_creation_success',
        ];

        foreach ($cardEvents as $match) {
            if ($event === $match || str_contains($event, 'card') && (str_contains($event, 'creat') || str_contains($event, 'ready') || str_contains($event, 'success'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractWebhookCardId(array $payload): string
    {
        $candidates = [
            data_get($payload, 'data.card_id'),
            data_get($payload, 'data.cardId'),
            data_get($payload, 'data.card_code'),
            data_get($payload, 'data.cardCode'),
            data_get($payload, 'data.id'),
            data_get($payload, 'data.card.id'),
        ];

        foreach ($candidates as $value) {
            $id = trim((string) $value);
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractWebhookReference(array $payload): string
    {
        $candidates = [
            data_get($payload, 'data.reference'),
            data_get($payload, 'data.external_reference'),
            data_get($payload, 'data.customer_reference'),
            data_get($payload, 'data.order_reference'),
            data_get($payload, 'data.order_id'),
            data_get($payload, 'reference'),
        ];

        foreach ($candidates as $value) {
            $ref = trim((string) $value);
            if ($ref !== '') {
                return $ref;
            }
        }

        return '';
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
            return substr($digits, -11);
        }

        return strlen($digits) >= 11 ? substr($digits, -11) : $digits;
    }
}
