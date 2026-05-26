<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credits wallet balance when a bank payout failed or is reversed by admin.
 */
class WhatsappWalletBankPayoutRefundService
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function refundTransaction(
        WhatsappWalletTransaction $transaction,
        ?int $adminId = null,
        string $reason = 'admin_manual_refund',
    ): array {
        if ($transaction->type !== WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            return ['ok' => false, 'message' => 'Only bank transfer transactions can be refunded.'];
        }

        if ($transaction->isReversed()) {
            return ['ok' => false, 'message' => 'This transaction was already reversed.'];
        }

        $amount = round(abs((float) $transaction->amount), 2);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid transaction amount.'];
        }

        $refunded = false;

        DB::transaction(function () use ($transaction, $amount, $adminId, $reason, &$refunded): void {
            $txn = WhatsappWalletTransaction::query()->lockForUpdate()->find($transaction->id);
            if (! $txn || $txn->isReversed()) {
                return;
            }

            $wallet = WhatsappWallet::query()->lockForUpdate()->find($txn->whatsapp_wallet_id);
            if (! $wallet) {
                return;
            }

            $wallet->balance = round((float) $wallet->balance + $amount, 2);
            $wallet->daily_transfer_total = max(0, round((float) $wallet->daily_transfer_total - $amount, 2));
            $wallet->save();

            $meta = is_array($txn->meta) ? $txn->meta : [];
            $meta['reversed_at'] = now()->toIso8601String();
            $meta['payout_pending'] = false;
            $meta['payout_failed'] = true;
            $meta['payout_bucket'] = MavonPayTransferService::BUCKET_FAILED;
            $meta['admin_refund_reason'] = $reason;
            if ($adminId !== null) {
                $meta['admin_refund_by'] = $adminId;
            }

            $txn->update(['meta' => $meta]);
            $refunded = true;
        });

        if (! $refunded) {
            return ['ok' => false, 'message' => 'Refund could not be applied (already reversed or missing wallet).'];
        }

        Log::info('whatsapp.wallet.admin_refund', [
            'transaction_id' => $transaction->id,
            'wallet_id' => $transaction->whatsapp_wallet_id,
            'amount' => $amount,
            'admin_id' => $adminId,
            'reason' => $reason,
        ]);

        return ['ok' => true, 'message' => 'Wallet credited and transaction marked as reversed.'];
    }
}
