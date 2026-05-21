<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VirtualCardFeeRefundService
{
    /**
     * @return array{ok: bool, message: string, already_refunded?: bool}
     */
    public function refundFee(int $walletId, string $reference, float $amount, string $reason): array
    {
        try {
            $alreadyRefunded = false;
            DB::transaction(function () use ($walletId, $reference, $amount, $reason, &$alreadyRefunded) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($walletId);
                $txn = WhatsappWalletTransaction::query()
                    ->where('external_reference', $reference)
                    ->where('whatsapp_wallet_id', $walletId)
                    ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE)
                    ->first();
                if (! $w || ! $txn) {
                    throw new \RuntimeException('fee_txn_missing');
                }
                $meta = is_array($txn->meta) ? $txn->meta : [];
                if ($meta['refunded'] ?? false) {
                    $alreadyRefunded = true;

                    return;
                }
                $w->balance = round((float) $w->balance + $amount, 2);
                $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                $w->save();
                $meta['refunded'] = true;
                $meta['refund_reason'] = $reason;
                $txn->update(['meta' => $meta]);
            });

            if ($alreadyRefunded) {
                return ['ok' => true, 'message' => 'Fee was already refunded.', 'already_refunded' => true];
            }

            return ['ok' => true, 'message' => 'Fee refunded successfully.'];
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'fee_txn_missing') {
                return ['ok' => false, 'message' => 'Fee transaction not found for this request.'];
            }
            Log::error('virtual_card.refund_failed', ['wallet_id' => $walletId, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not refund fee.'];
        }
    }

    public function isFeeRefunded(?WhatsappWalletTransaction $txn): bool
    {
        if (! $txn) {
            return false;
        }
        $meta = is_array($txn->meta) ? $txn->meta : [];

        return (bool) ($meta['refunded'] ?? false);
    }
}
