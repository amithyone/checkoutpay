<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use Illuminate\Support\Facades\Log;

final class VirtualCardRequestSupersedeService
{
    public function supersedeStaleAttempts(VirtualCardRequest $winner): int
    {
        if (! $this->isOperableWinner($winner)) {
            return 0;
        }

        $reason = 'Superseded — card issued on request #'.$winner->id;

        $count = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $winner->whatsapp_wallet_id)
            ->where('id', '!=', $winner->id)
            ->where(function ($query) {
                $query->whereIn('status', [
                    VirtualCardRequest::STATUS_FAILED,
                    VirtualCardRequest::STATUS_PENDING,
                    VirtualCardRequest::STATUS_PREPARING,
                ])->orWhere(function ($submitted) {
                    $submitted->where('status', VirtualCardRequest::STATUS_SUBMITTED)
                        ->where(function ($cardId) {
                            $cardId->whereNull('card_external_id')
                                ->orWhere('card_external_id', '');
                        });
                });
            })
            ->update([
                'status' => VirtualCardRequest::STATUS_FAILED,
                'failure_reason' => $reason,
            ]);

        if ($count > 0) {
            Log::info('virtual_card.requests_superseded', [
                'winner_id' => $winner->id,
                'wallet_id' => $winner->whatsapp_wallet_id,
                'superseded_count' => $count,
            ]);
        }

        return $count;
    }

    private function isOperableWinner(VirtualCardRequest $row): bool
    {
        return trim((string) $row->card_external_id) !== ''
            && in_array($row->status, [
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_ACTIVE,
            ], true);
    }
}
