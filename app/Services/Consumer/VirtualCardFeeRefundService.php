<?php

namespace App\Services\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VirtualCardFeeRefundService
{
    public function findFeeTransaction(int $walletId, string $reference): ?WhatsappWalletTransaction
    {
        return WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $walletId)
            ->where('external_reference', $reference)
            ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE)
            ->first();
    }

    /**
     * Re-debit a refunded card fee when a late webhook activates the request.
     *
     * @return array{ok: bool, message: string, collected?: bool, already_collected?: bool}
     */
    public function ensureFeeCollectedForActivation(VirtualCardRequest $row): array
    {
        $walletId = (int) $row->whatsapp_wallet_id;
        $reference = (string) $row->external_reference;
        $amount = round((float) $row->fee_ngn, 2);

        if ($reference === '' || $amount < 0.01) {
            return ['ok' => false, 'message' => 'Card request fee details are missing.'];
        }

        try {
            $collected = false;
            $alreadyCollected = false;

            DB::transaction(function () use ($walletId, $reference, $amount, &$collected, &$alreadyCollected) {
                $txn = WhatsappWalletTransaction::query()
                    ->where('whatsapp_wallet_id', $walletId)
                    ->where('external_reference', $reference)
                    ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE)
                    ->lockForUpdate()
                    ->first();

                if (! $txn) {
                    throw new \RuntimeException('fee_txn_missing');
                }

                $meta = is_array($txn->meta) ? $txn->meta : [];
                if (! ($meta['refunded'] ?? false)) {
                    $alreadyCollected = true;

                    return;
                }

                $wallet = WhatsappWallet::query()->lockForUpdate()->find($walletId);
                if (! $wallet) {
                    throw new \RuntimeException('wallet_missing');
                }

                $wallet->resetDailyTransferIfNeeded();
                $check = $wallet->canDebit($amount);
                if (! ($check['ok'] ?? false)) {
                    throw new \RuntimeException('insufficient_balance:'.(string) ($check['message'] ?? 'Insufficient balance.'));
                }

                $newBal = round((float) $wallet->balance - $amount, 2);
                $wallet->balance = $newBal;
                $wallet->daily_transfer_total = round((float) $wallet->daily_transfer_total + $amount, 2);
                $wallet->daily_transfer_for_date = now()->toDateString();
                $wallet->save();

                $meta['refunded'] = false;
                $meta['recollected_at'] = now()->toIso8601String();
                $meta['recollected_reason'] = 'webhook_activation';
                $txn->update([
                    'balance_after' => $newBal,
                    'meta' => $meta,
                ]);
                $collected = true;
            });

            if ($alreadyCollected) {
                return ['ok' => true, 'message' => 'Fee already held for this request.', 'already_collected' => true];
            }

            return ['ok' => true, 'message' => 'Refunded fee re-debited for activated card.', 'collected' => true];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($message === 'fee_txn_missing') {
                return ['ok' => false, 'message' => 'Fee transaction not found for this request.'];
            }
            if (str_starts_with($message, 'insufficient_balance:')) {
                return ['ok' => false, 'message' => substr($message, strlen('insufficient_balance:'))];
            }

            Log::error('virtual_card.fee_recollect_failed', [
                'virtual_card_request_id' => $row->id,
                'wallet_id' => $walletId,
                'reference' => $reference,
                'error' => $message,
            ]);

            return ['ok' => false, 'message' => 'Could not re-debit refunded card fee.'];
        }
    }

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
