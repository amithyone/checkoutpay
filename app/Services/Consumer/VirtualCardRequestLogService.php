<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;

final class VirtualCardRequestLogService
{
    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function withMevonWebhook(array $decoded, ?string $rawBody = null, array $extra = []): array
    {
        $context = $extra;

        if ($decoded !== []) {
            $context['raw_payload'] = $decoded;
        }

        $body = trim((string) $rawBody);
        if ($body !== '') {
            $context['raw_body'] = $body;
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $api
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function withMevonApiResponse(array $api, array $extra = []): array
    {
        return array_merge($extra, [
            'provider_response' => $this->normalizeForStorage($api),
        ]);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function withMevonApiRequest(array $request, array $extra = []): array
    {
        return array_merge($extra, [
            'provider_request' => $this->normalizeForStorage($request),
        ]);
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function normalizeForStorage(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeForStorage($item);
            }

            return $normalized;
        }

        if (is_object($value)) {
            return $this->normalizeForStorage(json_decode(json_encode($value), true));
        }

        if (is_string($value) || is_numeric($value) || is_bool($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    public function info(
        string $event,
        string $message,
        ?VirtualCardRequest $request = null,
        array $context = [],
        ?int $walletId = null,
    ): VirtualCardRequestLog {
        return $this->write(VirtualCardRequestLog::LEVEL_INFO, $event, $message, $request, $context, $walletId);
    }

    public function warning(
        string $event,
        string $message,
        ?VirtualCardRequest $request = null,
        array $context = [],
        ?int $walletId = null,
    ): VirtualCardRequestLog {
        return $this->write(VirtualCardRequestLog::LEVEL_WARNING, $event, $message, $request, $context, $walletId);
    }

    public function error(
        string $event,
        string $message,
        ?VirtualCardRequest $request = null,
        array $context = [],
        ?int $walletId = null,
    ): VirtualCardRequestLog {
        return $this->write(VirtualCardRequestLog::LEVEL_ERROR, $event, $message, $request, $context, $walletId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(
        string $level,
        string $event,
        string $message,
        ?VirtualCardRequest $request,
        array $context,
        ?int $walletId,
    ): VirtualCardRequestLog {
        return VirtualCardRequestLog::query()->create([
            'virtual_card_request_id' => $request?->id,
            'whatsapp_wallet_id' => $walletId ?? $request?->whatsapp_wallet_id,
            'level' => $level,
            'event' => $event,
            'message' => mb_substr($message, 0, 500),
            'context' => $context === [] ? null : $context,
        ]);
    }
}
