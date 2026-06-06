<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;

final class VirtualCardRequestLogService
{
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
