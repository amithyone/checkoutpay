<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class VirtualCardMevonWebhookService
{
    public const RESULT_NOT_CARD = 'not_card';

    public const RESULT_ACTIVATED = 'activated';

    public const RESULT_ALREADY_ACTIVE = 'already_active';

    public const RESULT_NO_MATCH = 'no_match';

    public function __construct(
        private VirtualCardProviderResponseService $providerResponse,
        private VirtualCardRequestLogService $cardLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function tryFulfillFromWebhook(array $payload): bool
    {
        return in_array($this->handleWebhook($payload), [
            self::RESULT_ACTIVATED,
            self::RESULT_ALREADY_ACTIVE,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): string
    {
        if (! $this->isCardEvent($payload)) {
            return self::RESULT_NOT_CARD;
        }

        $cardId = $this->extractWebhookCardId($payload);
        $reference = $this->extractWebhookReference($payload);
        $email = $this->extractWebhookEmail($payload);
        $phone = $this->extractWebhookPhone($payload);

        $this->cardLogs->info('webhook_received', 'MevonPay card webhook received', null, [
            'event' => $this->extractWebhookEvent($payload),
            'reference' => $reference,
            'card_id' => $cardId,
            'email' => $email,
            'phone' => $phone,
        ]);

        if ($cardId !== '') {
            $existing = VirtualCardRequest::query()
                ->where('card_external_id', $cardId)
                ->whereIn('status', [
                    VirtualCardRequest::STATUS_SUBMITTED,
                    VirtualCardRequest::STATUS_ACTIVE,
                ])
                ->first();
            if ($existing) {
                $this->cardLogs->info('webhook_already_active', 'Card already active for this provider card_id', $existing, [
                    'card_id' => $cardId,
                ]);

                return self::RESULT_ALREADY_ACTIVE;
            }
        }

        if ($reference !== '' && ! $this->isCheckoutExternalReference($reference)) {
            $row = VirtualCardRequest::query()
                ->where('provider_reference', $reference)
                ->first();
            if ($row) {
                $this->activateFromWebhook($row, $payload, $cardId);

                return self::RESULT_ACTIVATED;
            }
        }

        if ($reference !== '' && $this->isCheckoutExternalReference($reference)) {
            $row = VirtualCardRequest::query()
                ->where('external_reference', $reference)
                ->first();
            if ($row) {
                $this->activateFromWebhook($row, $payload, $cardId);

                return self::RESULT_ACTIVATED;
            }
        }

        $candidates = $this->openRequestCandidates($cardId);
        $row = $this->pickBestCandidate($candidates, $reference, $cardId, $email, $phone);

        if (! $row && $cardId !== '') {
            $row = $this->fallbackLatestOpenRequest($cardId);
        }

        if (! $row) {
            $context = [
                'event' => $this->extractWebhookEvent($payload),
                'reference' => $reference,
                'card_id' => $cardId,
                'email' => $email,
                'phone' => $phone,
                'candidate_count' => $candidates->count(),
                'payload_keys' => array_keys($payload),
            ];
            Log::warning('virtual_card.webhook.no_match', $context);
            $this->cardLogs->warning('webhook_no_match', 'Card webhook received but no virtual card request matched', null, $context);

            return self::RESULT_NO_MATCH;
        }

        $this->activateFromWebhook($row, $payload, $cardId);

        return self::RESULT_ACTIVATED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function activateFromWebhook(VirtualCardRequest $row, array $payload, string $cardId): void
    {
        $wasFailed = $row->status === VirtualCardRequest::STATUS_FAILED;
        $this->providerResponse->applyWebhookReady($row, $payload, $cardId !== '' ? $cardId : null);
        $fresh = $row->fresh();

        $this->cardLogs->info('webhook_activated', 'Card activated from MevonPay webhook', $fresh, [
            'card_id' => $fresh->card_external_id,
            'was_failed' => $wasFailed,
            'event' => $this->extractWebhookEvent($payload),
        ], $fresh->whatsapp_wallet_id);

        Log::info('virtual_card.webhook.activated', [
            'virtual_card_request_id' => $fresh->id,
            'wallet_id' => $fresh->whatsapp_wallet_id,
            'card_external_id' => $fresh->card_external_id,
            'event' => $this->extractWebhookEvent($payload),
            'was_failed' => $wasFailed,
        ]);
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
            if ($reference !== '' && $candidate->provider_reference === $reference) {
                return $candidate;
            }
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
            return $row->created_at !== null && $row->created_at->greaterThan(Carbon::now()->subDays(7));
        });

        if ($cardId !== '' && $recent->count() === 1) {
            return $recent->first();
        }

        return null;
    }

    private function fallbackLatestOpenRequest(string $cardId): ?VirtualCardRequest
    {
        $recent = VirtualCardRequest::query()
            ->whereNull('card_external_id')
            ->where(function ($query) {
                $query->whereIn('status', [
                    VirtualCardRequest::STATUS_PENDING,
                    VirtualCardRequest::STATUS_PREPARING,
                    VirtualCardRequest::STATUS_SUBMITTED,
                ])->orWhere(function ($failed) {
                    $failed->where('status', VirtualCardRequest::STATUS_FAILED)
                        ->where('created_at', '>=', Carbon::now()->subDays(30));
                })->orWhere(function ($active) {
                    $active->where('status', VirtualCardRequest::STATUS_ACTIVE)
                        ->whereNull('card_external_id');
                });
            })
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->latest('id')
            ->limit(2)
            ->get();

        if ($recent->count() === 1 && $cardId !== '') {
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
                        ->where('created_at', '>=', Carbon::now()->subDays(30));
                })->orWhere(function ($active) {
                    $active->where('status', VirtualCardRequest::STATUS_ACTIVE)
                        ->whereNull('card_external_id');
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
        $event = $this->extractWebhookEvent($payload);
        if ($event === '') {
            return $this->extractWebhookCardId($payload) !== ''
                && $this->extractWebhookReference($payload) !== '';
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
            && (str_contains($event, 'creat') || str_contains($event, 'ready') || str_contains($event, 'success') || str_contains($event, 'active'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookEvent(array $payload): string
    {
        $candidates = [
            data_get($payload, 'event'),
            data_get($payload, 'eventType'),
            data_get($payload, 'type'),
            data_get($payload, 'action'),
            data_get($payload, 'data.event'),
            data_get($payload, 'data.type'),
            data_get($payload, 'data.action'),
        ];

        foreach ($candidates as $value) {
            $event = strtolower(trim((string) $value));
            if ($event !== '') {
                return $event;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractWebhookCardId(array $payload): string
    {
        $candidates = [
            data_get($payload, 'card_id'),
            data_get($payload, 'cardId'),
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
            data_get($payload, 'reference'),
            data_get($payload, 'data.reference'),
            data_get($payload, 'data.external_reference'),
            data_get($payload, 'data.customer_reference'),
            data_get($payload, 'data.order_reference'),
            data_get($payload, 'data.order_id'),
            data_get($payload, 'data.request_id'),
            data_get($payload, 'data.transaction_reference'),
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
            data_get($payload, 'email'),
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
            data_get($payload, 'phone_number'),
            data_get($payload, 'phoneNumber'),
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

}
