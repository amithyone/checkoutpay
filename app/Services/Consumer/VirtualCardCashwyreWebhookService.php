<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use App\Services\VirtualCard\VirtualCardProviderResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class VirtualCardCashwyreWebhookService
{
    public const RESULT_NOT_CARD = 'not_card';

    public const RESULT_ACTIVATED = 'activated';

    public const RESULT_ALREADY_ACTIVE = 'already_active';

    public const RESULT_NO_MATCH = 'no_match';

    public const RESULT_FAILED = 'failed';

    public const RESULT_FEE_COLLECTION_FAILED = 'fee_collection_failed';

    public const RESULT_TOPUP_SUCCESS = 'topup_success';

    public const RESULT_WITHDRAW_SUCCESS = 'withdraw_success';

    public function __construct(
        private VirtualCardProviderResponseService $providerResponse,
        private VirtualCardRequestLogService $cardLogs,
        private VirtualCardFeeRefundService $feeRefunds,
        private VirtualCardRequestSupersedeService $supersede,
        private ConsumerVirtualCardService $cards,
        private VirtualCardNotificationService $cardNotifier,
        private VirtualCardStoredDetailsService $storedDetails,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{raw_body?: string|null}  $ingress
     */
    public function handleWebhook(array $payload, array $ingress = []): string
    {
        $event = $this->extractWebhookEvent($payload);
        $rawBody = isset($ingress['raw_body']) ? (string) $ingress['raw_body'] : null;

        if ($event === '') {
            return self::RESULT_NOT_CARD;
        }

        if ($this->isCreateSuccessEvent($event)) {
            return $this->handleCreateSuccessWebhook($payload, $rawBody);
        }

        if ($this->isCreateFailedEvent($event)) {
            return $this->handleCreateFailedWebhook($payload, $rawBody);
        }

        if ($this->isTopupEvent($event)) {
            return $this->handleTopupWebhook($payload, $rawBody);
        }

        if ($this->isWithdrawSuccessEvent($event)) {
            return $this->handleWithdrawWebhook($payload, $rawBody);
        }

        if ($this->isWithdrawFailedEvent($event)) {
            $this->cardLogs->warning('webhook_withdraw_failed', 'Cashwyre card withdrawal failed webhook received', null, [
                'event' => $event,
                'payload' => $payload,
            ]);

            return self::RESULT_NOT_CARD;
        }

        return self::RESULT_NOT_CARD;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractWebhookEvent(array $payload): string
    {
        return strtolower(trim((string) (
            $payload['eventType']
            ?? $payload['event_type']
            ?? $payload['event']
            ?? ''
        )));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCreateSuccessWebhook(array $payload, ?string $rawBody): string
    {
        $eventData = $this->eventData($payload);
        $cardId = $this->extractCardId($eventData);
        $reference = $this->extractReference($eventData, $payload);
        $email = strtolower(trim((string) ($eventData['CustomerEmail'] ?? $eventData['customerEmail'] ?? $eventData['email'] ?? '')));

        $this->cardLogs->info('webhook_received', 'Cashwyre card created webhook received', null, [
            'event' => $this->extractWebhookEvent($payload),
            'reference' => $reference,
            'card_id' => $cardId,
            'email' => $email,
            'raw_body' => $rawBody,
        ]);

        if ($cardId !== '') {
            $existing = VirtualCardRequest::query()
                ->where('provider', VirtualCardProviderResolver::PROVIDER_CASHWYRE)
                ->where('card_external_id', $cardId)
                ->where('status', VirtualCardRequest::STATUS_ACTIVE)
                ->first();
            if ($existing) {
                return self::RESULT_ALREADY_ACTIVE;
            }
        }

        $row = $this->findMatchingRequest($reference, $cardId, $email);
        if (! $row) {
            Log::warning('virtual_card.cashwyre.webhook.no_match', [
                'reference' => $reference,
                'card_id' => $cardId,
                'email' => $email,
            ]);

            return self::RESULT_NO_MATCH;
        }

        return $this->activateFromWebhook($row, $payload, $cardId, $rawBody);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCreateFailedWebhook(array $payload, ?string $rawBody): string
    {
        $eventData = $this->eventData($payload);
        $reference = $this->extractReference($eventData, $payload);
        $row = $this->findMatchingRequest($reference, $this->extractCardId($eventData), '');

        if (! $row) {
            return self::RESULT_NO_MATCH;
        }

        $reason = trim((string) ($payload['message'] ?? 'Cashwyre card creation failed'));
        $this->feeRefunds->refundFee((int) $row->whatsapp_wallet_id, (string) $row->external_reference, (float) $row->fee_ngn, $reason);
        $this->providerResponse->applyFailure($row, $payload, $reason);
        $this->cardLogs->error('webhook_create_failed', 'Cashwyre card creation failed; fee refunded', $row->fresh(), [
            'event' => $this->extractWebhookEvent($payload),
            'reason' => $reason,
            'raw_body' => $rawBody,
        ], $row->whatsapp_wallet_id);

        return self::RESULT_FAILED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleTopupWebhook(array $payload, ?string $rawBody): string
    {
        $eventData = $this->eventData($payload);
        $cardCode = $this->extractCardId($eventData);
        $reference = $this->extractReference($eventData, $payload);
        $newBalance = $eventData['CardBalance'] ?? $eventData['cardBalance'] ?? null;

        if ($cardCode === '') {
            return self::RESULT_NO_MATCH;
        }

        if ($reference !== '' && Cache::has('vcard:topup:processed:'.$reference)) {
            return self::RESULT_TOPUP_SUCCESS;
        }

        $card = VirtualCardRequest::query()
            ->where('provider', VirtualCardProviderResolver::PROVIDER_CASHWYRE)
            ->where(function ($query) use ($cardCode) {
                $query->where('card_external_id', $cardCode)
                    ->orWhere('card_details_payload->card_code', $cardCode);
            })
            ->first();

        if (! $card) {
            return self::RESULT_NO_MATCH;
        }

        if ($newBalance !== null && is_numeric($newBalance)) {
            $card->update(['card_balance_usd' => round((float) $newBalance, 2)]);
        }

        $this->storedDetails->persistFromWebhook($card->fresh(), $payload);

        if ($reference !== '') {
            Cache::put('vcard:topup:processed:'.$reference, true, now()->addDays(30));
        }

        $this->cardLogs->info('webhook_topup_success', 'Cashwyre card topup webhook processed', $card->fresh(), [
            'card_code' => $cardCode,
            'reference' => $reference,
            'raw_body' => $rawBody,
        ], $card->whatsapp_wallet_id);

        return self::RESULT_TOPUP_SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleWithdrawWebhook(array $payload, ?string $rawBody): string
    {
        $eventData = $this->eventData($payload);
        $cardCode = $this->extractCardId($eventData);
        $newBalance = $eventData['CardBalance'] ?? $eventData['cardBalance'] ?? null;

        if ($cardCode === '') {
            return self::RESULT_NO_MATCH;
        }

        $card = VirtualCardRequest::query()
            ->where('provider', VirtualCardProviderResolver::PROVIDER_CASHWYRE)
            ->where('card_external_id', $cardCode)
            ->first();

        if (! $card) {
            return self::RESULT_NO_MATCH;
        }

        if ($newBalance !== null && is_numeric($newBalance)) {
            $card->update(['card_balance_usd' => round((float) $newBalance, 2)]);
        }

        $this->storedDetails->persistFromWebhook($card->fresh(), $payload);
        $this->cardLogs->info('webhook_withdraw_success', 'Cashwyre card withdraw webhook processed', $card->fresh(), [
            'card_code' => $cardCode,
            'raw_body' => $rawBody,
        ], $card->whatsapp_wallet_id);

        return self::RESULT_WITHDRAW_SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function activateFromWebhook(VirtualCardRequest $row, array $payload, string $cardId, ?string $rawBody): string
    {
        $wasFailed = $row->status === VirtualCardRequest::STATUS_FAILED;
        $collection = $this->feeRefunds->ensureFeeCollectedForActivation($row);

        if (! ($collection['ok'] ?? false)) {
            $this->cardLogs->error(
                'webhook_fee_collection_failed',
                'Cashwyre card webhook matched but fee could not be collected',
                $row,
                ['was_failed' => $wasFailed, 'collection_message' => (string) ($collection['message'] ?? '')],
                $row->whatsapp_wallet_id,
            );

            return self::RESULT_FEE_COLLECTION_FAILED;
        }

        $normalizedPayload = $this->normalizeWebhookForStorage($payload);
        $this->providerResponse->applyWebhookReady($row, $normalizedPayload, $cardId !== '' ? $cardId : null);
        $fresh = $row->fresh();
        $this->cards->syncProviderCardCode($fresh);
        $fresh = $fresh->fresh();

        $wallet = $fresh->wallet;
        if ($wallet) {
            $this->cards->refreshProviderCardBalance($wallet);
            $fresh = $fresh->fresh();
        }

        $superseded = $this->supersede->supersedeStaleAttempts($fresh);

        $this->cardLogs->info('webhook_activated', 'Card activated from Cashwyre webhook', $fresh, [
            'superseded_attempts' => $superseded,
            'card_id' => $fresh->card_external_id,
            'was_failed' => $wasFailed,
            'event' => $this->extractWebhookEvent($payload),
            'raw_body' => $rawBody,
        ], $fresh->whatsapp_wallet_id);

        if ($wallet) {
            $this->cardNotifier->notifyCardReadyIfNeeded($wallet->fresh(), $fresh->fresh());
        }

        return self::RESULT_ACTIVATED;
    }

    private function findMatchingRequest(string $reference, string $cardId, string $email): ?VirtualCardRequest
    {
        $base = VirtualCardRequest::query()
            ->where('provider', VirtualCardProviderResolver::PROVIDER_CASHWYRE)
            ->whereIn('status', [
                VirtualCardRequest::STATUS_PENDING,
                VirtualCardRequest::STATUS_PREPARING,
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_FAILED,
            ]);

        if ($reference !== '') {
            $byRef = (clone $base)
                ->where(function ($query) use ($reference) {
                    $query->where('external_reference', $reference)
                        ->orWhere('provider_reference', $reference);
                })
                ->latest('id')
                ->first();
            if ($byRef) {
                return $byRef;
            }
        }

        if ($cardId !== '') {
            $byCard = (clone $base)
                ->where(function ($query) use ($cardId) {
                    $query->whereNull('card_external_id')
                        ->orWhere('card_external_id', '')
                        ->orWhere('card_external_id', $cardId);
                })
                ->latest('id')
                ->first();
            if ($byCard) {
                return $byCard;
            }
        }

        if ($email !== '') {
            $candidates = (clone $base)->latest('id')->limit(20)->get();
            foreach ($candidates as $candidate) {
                $requestPayload = is_array($candidate->request_payload) ? $candidate->request_payload : [];
                $reqEmail = strtolower(trim((string) ($requestPayload['email'] ?? '')));
                if ($reqEmail !== '' && $reqEmail === $email) {
                    return $candidate;
                }
            }
        }

        return (clone $base)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function eventData(array $payload): array
    {
        $data = $payload['eventData'] ?? $payload['data'] ?? $payload;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @param  array<string, mixed>  $payload
     */
    private function extractReference(array $eventData, array $payload): string
    {
        foreach ([
            $eventData['Reference'] ?? null,
            $eventData['reference'] ?? null,
            $payload['requestId'] ?? null,
            $payload['request_id'] ?? null,
        ] as $value) {
            $ref = trim((string) $value);
            if ($ref !== '') {
                return $ref;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function extractCardId(array $eventData): string
    {
        foreach ([
            $eventData['cardCode'] ?? null,
            $eventData['CardCode'] ?? null,
            $eventData['code'] ?? null,
            $eventData['Code'] ?? null,
            $eventData['card_id'] ?? null,
            $eventData['cardId'] ?? null,
            $eventData['id'] ?? null,
        ] as $value) {
            $id = trim((string) $value);
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeWebhookForStorage(array $payload): array
    {
        if (isset($payload['eventData']) && is_array($payload['eventData'])) {
            return array_merge($payload, ['data' => $payload['eventData']]);
        }

        return $payload;
    }

    private function isCreateSuccessEvent(string $event): bool
    {
        return in_array($event, ['virtualcard.created.success', 'virtualcard.create.success'], true);
    }

    private function isCreateFailedEvent(string $event): bool
    {
        return in_array($event, ['virtualcard.created.failed', 'virtualcard.create.failed'], true);
    }

    private function isTopupEvent(string $event): bool
    {
        return $event === 'virtualcard.topup.success'
            || str_contains($event, 'topup') && str_contains($event, 'success');
    }

    private function isWithdrawSuccessEvent(string $event): bool
    {
        return in_array($event, [
            'virtualcard.withdraw.success',
            'virtualcard.withdrawal.success',
        ], true);
    }

    private function isWithdrawFailedEvent(string $event): bool
    {
        return in_array($event, [
            'virtualcard.withdraw.failed',
            'virtualcard.withdrawal.failed',
        ], true);
    }
}
