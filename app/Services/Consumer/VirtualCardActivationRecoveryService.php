<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;

final class VirtualCardActivationRecoveryService
{
    public function __construct(
        private VirtualCardMevonWebhookService $webhooks,
        private VirtualCardProviderResponseService $providerResponse,
    ) {}

    public function canRetrySync(VirtualCardRequest $request): bool
    {
        if (trim((string) ($request->card_external_id ?? '')) !== '') {
            return false;
        }

        return in_array($request->status, [
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_PREPARING,
            VirtualCardRequest::STATUS_SUBMITTED,
            VirtualCardRequest::STATUS_FAILED,
        ], true);
    }

    /**
     * @return array{ok: bool, message: string, result: ?string, activated: bool}
     */
    public function retrySync(VirtualCardRequest $request): array
    {
        $request = $request->fresh();
        if ($request === null) {
            return [
                'ok' => false,
                'message' => 'Virtual card request not found.',
                'result' => null,
                'activated' => false,
            ];
        }

        if (! $this->canRetrySync($request)) {
            return [
                'ok' => false,
                'message' => 'This card request is not waiting for activation sync.',
                'result' => null,
                'activated' => false,
            ];
        }

        $payload = $this->findReplayableCardCreatedWebhook($request);
        if ($payload === null) {
            return [
                'ok' => false,
                'message' => 'No stored MevonPay card-created webhook found for this request yet. Try again in a minute, or contact support if it stays stuck.',
                'result' => 'no_payload',
                'activated' => false,
            ];
        }

        $result = $this->webhooks->handleWebhook($payload);
        $request->refresh();

        $activated = in_array($result, [
            VirtualCardMevonWebhookService::RESULT_ACTIVATED,
            VirtualCardMevonWebhookService::RESULT_ALREADY_ACTIVE,
        ], true) || $request->status === VirtualCardRequest::STATUS_ACTIVE;

        return [
            'ok' => $activated,
            'message' => $this->messageForResult($result, $request),
            'result' => $result,
            'activated' => $activated,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findReplayableCardCreatedWebhook(VirtualCardRequest $request): ?array
    {
        $logs = VirtualCardRequestLog::query()
            ->whereIn('event', ['webhook_received', 'webhook_no_match'])
            ->where(function ($query) use ($request) {
                $query->where('virtual_card_request_id', $request->id)
                    ->orWhere('whatsapp_wallet_id', $request->whatsapp_wallet_id);
            })
            ->latest('id')
            ->limit(200)
            ->get();

        foreach ($logs as $log) {
            $payload = $this->extractWebhookPayloadFromLog($log, $request);
            if ($payload === null) {
                continue;
            }

            if (! $this->isCardCreatedWebhook($payload)) {
                continue;
            }

            if ((int) $log->virtual_card_request_id === (int) $request->id) {
                return $payload;
            }

            if ($this->webhookMatchesRequest($payload, $request)) {
                return $payload;
            }
        }

        $response = is_array($request->response_payload) ? $request->response_payload : [];
        $storedWebhook = $response['webhook'] ?? null;
        if (is_array($storedWebhook) && $this->isCardCreatedWebhook($storedWebhook) && $this->webhookMatchesRequest($storedWebhook, $request)) {
            return $storedWebhook;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractWebhookPayloadFromLog(VirtualCardRequestLog $log, VirtualCardRequest $request): ?array
    {
        $context = is_array($log->context) ? $log->context : [];

        foreach ([$context['raw_payload'] ?? null, $context] as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            if ($this->looksLikeWebhookPayload($candidate)) {
                return $candidate;
            }
        }

        $rawBody = trim((string) ($context['raw_body'] ?? ''));
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded) && $this->looksLikeWebhookPayload($decoded)) {
                return $decoded;
            }
        }

        if ((int) $log->virtual_card_request_id === (int) $request->id && isset($context['raw_payload']) && is_array($context['raw_payload'])) {
            return $context['raw_payload'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function looksLikeWebhookPayload(array $payload): bool
    {
        if ($this->isCardCreatedWebhook($payload)) {
            return true;
        }

        return trim((string) data_get($payload, 'event', '')) !== ''
            || trim((string) data_get($payload, 'data.card_id', '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCardCreatedWebhook(array $payload): bool
    {
        $event = strtolower(trim((string) data_get($payload, 'event', data_get($payload, 'data.event', ''))));
        if ($event === '') {
            return trim((string) data_get($payload, 'data.card_id', '')) !== ''
                && trim((string) data_get($payload, 'data.reference', data_get($payload, 'data.request_id', ''))) !== '';
        }

        foreach ([
            'card.created',
            'card.created.success',
            'card_created',
            'card.create',
            'virtual_card.created',
            'virtual_card_created',
            'card.success',
        ] as $cardEvent) {
            if ($event === $cardEvent || str_contains($event, 'card.created')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhookMatchesRequest(array $payload, VirtualCardRequest $request): bool
    {
        $providerReference = trim((string) ($request->provider_reference ?? ''));
        $externalReference = trim((string) ($request->external_reference ?? ''));
        $cardName = strtolower(trim((string) ($request->card_name ?? '')));

        $webhookReference = trim((string) data_get($payload, 'data.reference', data_get($payload, 'reference', '')));
        $webhookRequestId = trim((string) data_get(
            $payload,
            'data.request_id',
            data_get($payload, 'data.requestId', data_get($payload, 'request_id', ''))
        ));

        foreach ($this->requestReferenceCandidates($request) as $candidate) {
            if ($candidate !== '' && (
                strcasecmp($candidate, $webhookReference) === 0
                || strcasecmp($candidate, $webhookRequestId) === 0
            )) {
                return true;
            }
        }

        if ($externalReference !== '' && strcasecmp($externalReference, $webhookReference) === 0) {
            return true;
        }

        if ($providerReference !== '' && (
            strcasecmp($providerReference, $webhookReference) === 0
            || strcasecmp($providerReference, $webhookRequestId) === 0
        )) {
            return true;
        }

        $webhookCardName = strtolower(trim((string) data_get($payload, 'data.card_name', data_get($payload, 'data.cardName', ''))));
        if ($cardName === '' || $webhookCardName === '' || $cardName !== $webhookCardName) {
            return false;
        }

        $requestPayload = is_array($request->request_payload) ? $request->request_payload : [];
        $requestEmail = strtolower(trim((string) ($requestPayload['email'] ?? '')));
        $webhookEmail = strtolower(trim((string) data_get($payload, 'data.email', '')));
        if ($requestEmail !== '' && $webhookEmail !== '' && $requestEmail !== $webhookEmail) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function requestReferenceCandidates(VirtualCardRequest $request): array
    {
        $candidates = [];

        foreach ([
            $request->provider_reference,
            $request->external_reference,
        ] as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $candidates[] = $text;
            }
        }

        $response = is_array($request->response_payload) ? $request->response_payload : [];
        foreach ([
            data_get($response, 'data.request_id'),
            data_get($response, 'data.reference'),
            data_get($response, 'request_id'),
        ] as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $candidates[] = $text;
            }
        }

        $req = $this->providerResponse->extractMevonRequestId($response);
        if ($req !== null) {
            $candidates[] = $req;
        }

        return array_values(array_unique($candidates));
    }

    private function messageForResult(string $result, VirtualCardRequest $request): string
    {
        if ($request->status === VirtualCardRequest::STATUS_ACTIVE) {
            return 'Your Dollar Virtual Card is ready.';
        }

        return match ($result) {
            VirtualCardMevonWebhookService::RESULT_ACTIVATED => 'Your Dollar Virtual Card is ready.',
            VirtualCardMevonWebhookService::RESULT_ALREADY_ACTIVE => 'Your Dollar Virtual Card is already active.',
            VirtualCardMevonWebhookService::RESULT_FEE_COLLECTION_FAILED => 'We found your card but could not collect the setup fee. Please contact support.',
            VirtualCardMevonWebhookService::RESULT_NO_MATCH => 'We could not match the stored webhook to your card request. Please contact support.',
            default => 'Card activation sync did not complete. Please try again or contact support.',
        };
    }
}
