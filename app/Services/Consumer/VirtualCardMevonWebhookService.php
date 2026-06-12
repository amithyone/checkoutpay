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

    public const RESULT_FEE_COLLECTION_FAILED = 'fee_collection_failed';

    public const RESULT_TOPUP_SUCCESS = 'topup_success';

    public const RESULT_SPEND_SUCCESS = 'spend_success';

    public function __construct(
        private VirtualCardProviderResponseService $providerResponse,
        private VirtualCardRequestLogService $cardLogs,
        private VirtualCardFeeRefundService $feeRefunds,
        private VirtualCardRequestSupersedeService $supersede,
        private ConsumerVirtualCardService $cards,
        private VirtualCardNotificationService $cardNotifier,
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
     * @param  array{raw_body?: string|null}  $ingress
     */
    public function handleWebhook(array $payload, array $ingress = []): string
    {
        $event = $this->extractWebhookEvent($payload);
        if ($this->isTopupEvent($event)) {
            return $this->handleTopupWebhook($payload, isset($ingress['raw_body']) ? (string) $ingress['raw_body'] : null);
        }

        if ($this->isSpendEvent($event)) {
            return $this->handleSpendWebhook($payload, isset($ingress['raw_body']) ? (string) $ingress['raw_body'] : null);
        }

        if (! $this->isCardEvent($payload)) {
            return self::RESULT_NOT_CARD;
        }

        $cardId = $this->extractWebhookCardId($payload);
        $reference = $this->extractWebhookReference($payload);
        $email = $this->extractWebhookEmail($payload);
        $phone = $this->extractWebhookPhone($payload);
        $rawBody = isset($ingress['raw_body']) ? (string) $ingress['raw_body'] : null;

        $this->cardLogs->info('webhook_received', 'MevonPay card webhook received', null, $this->cardLogs->withMevonWebhook($payload, $rawBody, [
            'event' => $this->extractWebhookEvent($payload),
            'reference' => $reference,
            'card_id' => $cardId,
            'email' => $email,
            'phone' => $phone,
        ]));

        if ($cardId !== '') {
            $existing = VirtualCardRequest::query()
                ->where('card_external_id', $cardId)
                ->whereIn('status', [
                    VirtualCardRequest::STATUS_SUBMITTED,
                    VirtualCardRequest::STATUS_ACTIVE,
                ])
                ->first();
            if ($existing) {
                $this->cardLogs->info('webhook_already_active', 'Card already active for this provider card_id', $existing, $this->cardLogs->withMevonWebhook($payload, $rawBody, [
                    'card_id' => $cardId,
                ]));

                return self::RESULT_ALREADY_ACTIVE;
            }
        }

        if ($reference !== '' && ! $this->isCheckoutExternalReference($reference)) {
            $row = $this->findRequestByProviderReference($reference);
            if ($row) {
                return $this->activateFromWebhook($row, $payload, $cardId, $rawBody);
            }
        }

        if ($reference !== '' && $this->isCheckoutExternalReference($reference)) {
            $row = VirtualCardRequest::query()
                ->where('external_reference', $reference)
                ->first();
            if ($row) {
                return $this->activateFromWebhook($row, $payload, $cardId, $rawBody);
            }
        }

        $candidates = $this->openRequestCandidates($cardId);
        $row = $this->pickBestCandidate($candidates, $reference, $cardId, $email, $phone);

        if (! $row && $cardId !== '') {
            $row = $this->fallbackLatestOpenRequest($cardId);
        }

        if (! $row) {
            $context = $this->cardLogs->withMevonWebhook($payload, $rawBody, [
                'event' => $this->extractWebhookEvent($payload),
                'reference' => $reference,
                'card_id' => $cardId,
                'email' => $email,
                'phone' => $phone,
                'candidate_count' => $candidates->count(),
            ]);
            Log::warning('virtual_card.webhook.no_match', $context);
            $this->cardLogs->warning('webhook_no_match', 'Card webhook received but no virtual card request matched', null, $context);

            return self::RESULT_NO_MATCH;
        }

        return $this->activateFromWebhook($row, $payload, $cardId, $rawBody);
    }

    private function isTopupEvent(string $event): bool
    {
        return in_array($event, [
            'card.topup.success',
            'card.topup',
            'card_topup',
            'virtual_card.topup',
        ], true) || (str_contains($event, 'card') && str_contains($event, 'topup'));
    }

    private function handleTopupWebhook(array $payload, ?string $rawBody = null): string
    {
        $data = $payload['data'] ?? $payload;
        if (! is_array($data)) {
            return self::RESULT_NO_MATCH;
        }

        $cardCode = trim((string) ($data['card_code'] ?? $data['card_id'] ?? $data['cardCode'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $newBalance = $data['new_balance'] ?? $data['balance'] ?? null;

        if ($cardCode === '') {
            return self::RESULT_NO_MATCH;
        }

        if ($reference !== '' && \Illuminate\Support\Facades\Cache::has('vcard:topup:processed:'.$reference)) {
            $this->cardLogs->info('webhook_topup_ignored', 'Topup webhook ignored (already processed in cache)', null, [
                'reference' => $reference,
                'card_code' => $cardCode,
            ]);

            return self::RESULT_TOPUP_SUCCESS;
        }

        $card = VirtualCardRequest::query()
            ->where('card_external_id', $cardCode)
            ->orWhere('card_details_payload->card_code', $cardCode)
            ->first();

        if (! $card) {
            $this->cardLogs->warning('webhook_topup_no_match', 'Card topup webhook matched no card request', null, [
                'reference' => $reference,
                'card_code' => $cardCode,
            ]);

            return self::RESULT_NO_MATCH;
        }

        $lastPayload = $card->last_operation_payload;
        if (is_array($lastPayload)) {
            $encoded = json_encode($lastPayload);
            if (is_string($encoded) && $reference !== '' && str_contains($encoded, $reference)) {
                if ($reference !== '') {
                    \Illuminate\Support\Facades\Cache::put('vcard:topup:processed:'.$reference, true, now()->addDays(30));
                }
                $this->cardLogs->info('webhook_topup_ignored', 'Topup webhook ignored (already processed in sync)', $card, [
                    'reference' => $reference,
                    'card_code' => $cardCode,
                ]);

                return self::RESULT_TOPUP_SUCCESS;
            }
        }

        if ($newBalance !== null && is_numeric($newBalance)) {
            $this->cards->updateReconciledBalance($card, round((float) $newBalance, 2));
        }

        if ($reference !== '') {
            \Illuminate\Support\Facades\Cache::put('vcard:topup:processed:'.$reference, true, now()->addDays(30));
        }

        $this->cardLogs->info('webhook_topup_success', 'Card topup confirmed from MevonPay webhook', $card, [
            'reference' => $reference,
            'card_code' => $cardCode,
            'new_balance' => $newBalance,
        ], $card->whatsapp_wallet_id);

        return self::RESULT_TOPUP_SUCCESS;
    }

    private function isSpendEvent(string $event): bool
    {
        if ($event === '' || str_contains($event, 'topup') || str_contains($event, 'creat')) {
            return false;
        }

        $spendEvents = [
            'card.spend',
            'card.spend.success',
            'card.transaction',
            'card.transaction.success',
            'card.debit',
            'card_debit',
            'virtual_card.spend',
        ];

        if (in_array($event, $spendEvents, true)) {
            return true;
        }

        return str_contains($event, 'card')
            && (str_contains($event, 'spend') || str_contains($event, 'debit') || str_contains($event, 'transaction') || str_contains($event, 'purchase'));
    }

    private function handleSpendWebhook(array $payload, ?string $rawBody = null): string
    {
        $data = $payload['data'] ?? $payload;
        if (! is_array($data)) {
            return self::RESULT_NO_MATCH;
        }

        $cardCode = trim((string) ($data['card_code'] ?? $data['card_id'] ?? $data['cardCode'] ?? ''));
        $newBalance = $data['new_balance'] ?? $data['balance'] ?? null;
        $txnRow = $this->cards->mevonTransactionRowFromWebhookPayload($payload);
        $dedupeKeys = $txnRow !== [] ? $this->cards->mevonTransactionDedupeKeys($txnRow) : [];

        foreach ($dedupeKeys as $dedupeKey) {
            if (\Illuminate\Support\Facades\Cache::has('vcard:spend:processed:'.$dedupeKey)) {
                $this->cardLogs->info('webhook_spend_ignored', 'Spend webhook ignored (duplicate transaction)', null, [
                    'dedupe_key' => $dedupeKey,
                    'card_code' => $cardCode,
                ]);

                return self::RESULT_SPEND_SUCCESS;
            }
        }

        if ($cardCode === '') {
            return self::RESULT_NO_MATCH;
        }

        $card = VirtualCardRequest::query()
            ->where('card_external_id', $cardCode)
            ->orWhere('card_details_payload->card_code', $cardCode)
            ->first();

        if (! $card) {
            $this->cardLogs->warning('webhook_spend_no_match', 'Card spend webhook matched no card request', null, [
                'card_code' => $cardCode,
                'dedupe_key' => $dedupeKeys !== [] ? $dedupeKeys[0] : null,
            ]);

            return self::RESULT_NO_MATCH;
        }

        if ($txnRow !== [] && $this->cards->mevonTransactionAlreadyRecorded($card, $txnRow)) {
            $this->cardLogs->info('webhook_spend_ignored', 'Spend webhook ignored (transaction already recorded on card)', $card, [
                'dedupe_keys' => $dedupeKeys,
                'card_code' => $cardCode,
            ]);

            return self::RESULT_SPEND_SUCCESS;
        }

        if ($newBalance !== null && is_numeric($newBalance)) {
            $this->cards->updateReconciledBalance($card, round((float) $newBalance, 2));
        } else {
            $this->cards->reconcileCardBalance($card);
        }

        foreach ($dedupeKeys as $dedupeKey) {
            \Illuminate\Support\Facades\Cache::put('vcard:spend:processed:'.$dedupeKey, true, now()->addDays(30));
        }

        if ($txnRow !== []) {
            $this->cards->recordMevonTransactionKeys($card, [$txnRow]);
        }

        $this->cardLogs->info('webhook_spend_success', 'Card spend/debit webhook processed', $card->fresh(), [
            'dedupe_keys' => $dedupeKeys !== [] ? $dedupeKeys : null,
            'card_code' => $cardCode,
            'new_balance' => $newBalance,
        ], $card->whatsapp_wallet_id);

        return self::RESULT_SPEND_SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function activateFromWebhook(VirtualCardRequest $row, array $payload, string $cardId, ?string $rawBody = null): string
    {
        $wasFailed = $row->status === VirtualCardRequest::STATUS_FAILED;
        $collection = $this->feeRefunds->ensureFeeCollectedForActivation($row);

        if (! ($collection['ok'] ?? false)) {
            $context = $this->cardLogs->withMevonWebhook($payload, $rawBody, [
                'virtual_card_request_id' => $row->id,
                'was_failed' => $wasFailed,
                'collection_message' => (string) ($collection['message'] ?? ''),
            ]);
            Log::warning('virtual_card.webhook.fee_collection_failed', $context);
            $this->cardLogs->error(
                'webhook_fee_collection_failed',
                'Card webhook matched but refunded fee could not be re-debited',
                $row,
                $context,
                $row->whatsapp_wallet_id,
            );

            return self::RESULT_FEE_COLLECTION_FAILED;
        }

        if ($collection['collected'] ?? false) {
            $this->cardLogs->info('webhook_fee_recollected', 'Refunded card fee re-debited before webhook activation', $row, [
                'fee_ngn' => $row->fee_ngn,
                'reference' => $row->external_reference,
            ], $row->whatsapp_wallet_id);
        }

        $this->providerResponse->applyWebhookReady($row, $payload, $cardId !== '' ? $cardId : null);
        $fresh = $row->fresh();
        $this->cards->syncProviderCardCode($fresh);
        $fresh = $fresh->fresh();

        $wallet = $fresh->wallet;
        if ($wallet) {
            $this->cards->refreshProviderCardBalance($wallet);
            $fresh = $fresh->fresh();
        }

        $superseded = $this->supersede->supersedeStaleAttempts($fresh);

        $this->cardLogs->info('webhook_activated', 'Card activated from MevonPay webhook', $fresh, $this->cardLogs->withMevonWebhook($payload, $rawBody, [
            'superseded_attempts' => $superseded,
            'card_id' => $fresh->card_external_id,
            'was_failed' => $wasFailed,
            'fee_recollected' => (bool) ($collection['collected'] ?? false),
            'event' => $this->extractWebhookEvent($payload),
        ]), $fresh->whatsapp_wallet_id);

        Log::info('virtual_card.webhook.activated', [
            'virtual_card_request_id' => $fresh->id,
            'wallet_id' => $fresh->whatsapp_wallet_id,
            'card_external_id' => $fresh->card_external_id,
            'event' => $this->extractWebhookEvent($payload),
            'was_failed' => $wasFailed,
            'fee_recollected' => (bool) ($collection['collected'] ?? false),
        ]);

        if ($wallet) {
            $this->cardNotifier->notifyCardReadyIfNeeded($wallet->fresh(), $fresh->fresh());
        }

        return self::RESULT_ACTIVATED;
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

    private function findRequestByProviderReference(string $reference): ?VirtualCardRequest
    {
        $row = VirtualCardRequest::query()
            ->where('provider_reference', $reference)
            ->first();
        if ($row) {
            return $row;
        }

        return VirtualCardRequest::query()
            ->where(function ($query) use ($reference) {
                $query->where('response_payload', 'like', '%'.$reference.'%')
                    ->orWhere('request_payload', 'like', '%'.$reference.'%');
            })
            ->whereIn('status', [
                VirtualCardRequest::STATUS_PENDING,
                VirtualCardRequest::STATUS_PREPARING,
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_FAILED,
                VirtualCardRequest::STATUS_ACTIVE,
            ])
            ->latest('id')
            ->first();
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
            data_get($payload, 'request_id'),
            data_get($payload, 'requestId'),
            data_get($payload, 'data.reference'),
            data_get($payload, 'data.external_reference'),
            data_get($payload, 'data.customer_reference'),
            data_get($payload, 'data.order_reference'),
            data_get($payload, 'data.order_id'),
            data_get($payload, 'data.request_id'),
            data_get($payload, 'data.requestId'),
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
