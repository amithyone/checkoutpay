<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use Illuminate\Support\Carbon;
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
        $email = $this->extractWebhookEmail($payload);
        $phone = $this->extractWebhookPhone($payload);

        if ($cardId !== '') {
            $existing = VirtualCardRequest::query()
                ->where('card_external_id', $cardId)
                ->whereIn('status', [
                    VirtualCardRequest::STATUS_SUBMITTED,
                    VirtualCardRequest::STATUS_ACTIVE,
                ])
                ->first();
            if ($existing) {
                return true;
            }
        }

        if ($reference !== '' && $this->isCheckoutExternalReference($reference)) {
            $row = VirtualCardRequest::query()
                ->where('external_reference', $reference)
                ->first();
            if ($row) {
                $this->providerResponse->applyWebhookReady($row, $payload, $cardId !== '' ? $cardId : null);
                $this->logActivated($row, $payload);

                return true;
            }
        }

        $candidates = $this->openRequestCandidates($cardId);
        $row = $this->pickBestCandidate($candidates, $reference, $cardId, $email, $phone);

        if (! $row) {
            Log::warning('virtual_card.webhook.no_match', [
                'event' => data_get($payload, 'event'),
                'reference' => $reference,
                'card_id' => $cardId,
                'email' => $email,
                'phone' => $phone,
                'candidate_count' => $candidates->count(),
            ]);

            return false;
        }

        $this->providerResponse->applyWebhookReady($row, $payload, $cardId !== '' ? $cardId : null);
        $this->logActivated($row, $payload);

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VirtualCardRequest>  $candidates
     */
    private function pickBestCandidate(
        $candidates,
        string $reference,
        string $cardId,
        string $email,
        string $phone,
    ): ?VirtualCardRequest {
        if ($candidates->isEmpty()) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($reference !== '' && $candidate->external_reference === $reference) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($reference !== '' && $this->payloadContainsReference($candidate, $reference)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            $requestPayload = is_array($candidate->request_payload) ? $candidate->request_payload : [];
            $reqEmail = strtolower(trim((string) ($requestPayload['email'] ?? '')));
            $reqPhone = $this->normalizePhone((string) ($requestPayload['phoneNumber'] ?? ''));

            if ($email !== '' && $reqEmail !== '' && $email === $reqEmail) {
                return $candidate;
            }
            if ($phone !== '' && $reqPhone !== '' && $phone === $reqPhone) {
                return $candidate;
            }
        }

        if ($cardId !== '' && $candidates->count() === 1) {
            return $candidates->first();
        }

        $recent = $candidates->filter(function (VirtualCardRequest $row) {
            return $row->created_at !== null && $row->created_at->greaterThan(Carbon::now()->subHours(24));
        });

        if ($cardId !== '' && $recent->count() === 1) {
            return $recent->first();
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, VirtualCardRequest>
     */
    private function openRequestCandidates(string $cardId)
    {
        return VirtualCardRequest::query()
            ->where(function ($query) use ($cardId) {
                $query->whereIn('status', [
                    VirtualCardRequest::STATUS_PENDING,
                    VirtualCardRequest::STATUS_PREPARING,
                    VirtualCardRequest::STATUS_SUBMITTED,
                ])->orWhere(function ($failed) {
                    $failed->where('status', VirtualCardRequest::STATUS_FAILED)
                        ->where('created_at', '>=', Carbon::now()->subDays(3));
                });
            })
            ->where(function ($query) use ($cardId) {
                $query->whereNull('card_external_id');
                if ($cardId !== '') {
                    $query->orWhere('card_external_id', $cardId);
                }
            })
            ->latest('id')
            ->limit(50)
            ->get();
    }

    private function payloadContainsReference(VirtualCardRequest $row, string $reference): bool
    {
        if ($reference === '') {
            return false;
        }

        $encoded = json_encode($row->response_payload ?? []);
        if (! is_string($encoded)) {
            return false;
        }

        return str_contains($encoded, $reference);
    }

    private function isCheckoutExternalReference(string $reference): bool
    {
        return str_starts_with(strtoupper($reference), 'VCARD-');
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
            'card.created.success',
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

        if (in_array($event, $cardEvents, true)) {
            return true;
        }

        return str_contains($event, 'card')
            && (str_contains($event, 'creat') || str_contains($event, 'ready') || str_contains($event, 'success'));
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
            data_get($payload, 'data.card.id'),
            data_get($payload, 'data.id'),
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
            data_get($payload, 'data.request_id'),
            data_get($payload, 'data.transaction_reference'),
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractWebhookEmail(array $payload): string
    {
        $candidates = [
            data_get($payload, 'data.email'),
            data_get($payload, 'data.customer_email'),
            data_get($payload, 'data.customer.email'),
            data_get($payload, 'data.cardholder.email'),
            data_get($payload, 'data.user.email'),
        ];

        foreach ($candidates as $value) {
            $email = strtolower(trim((string) $value));
            if ($email !== '' && str_contains($email, '@')) {
                return $email;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractWebhookPhone(array $payload): string
    {
        $candidates = [
            data_get($payload, 'data.phone_number'),
            data_get($payload, 'data.phoneNumber'),
            data_get($payload, 'data.phone'),
            data_get($payload, 'data.customer.phone'),
            data_get($payload, 'data.cardholder.phone'),
        ];

        foreach ($candidates as $value) {
            $phone = $this->normalizePhone((string) $value);
            if ($phone !== '') {
                return $phone;
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logActivated(VirtualCardRequest $row, array $payload): void
    {
        Log::info('virtual_card.webhook.activated', [
            'virtual_card_request_id' => $row->id,
            'wallet_id' => $row->whatsapp_wallet_id,
            'card_external_id' => $row->fresh()->card_external_id,
            'event' => data_get($payload, 'event'),
        ]);
    }
}
