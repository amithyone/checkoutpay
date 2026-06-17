<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappWalletTopupNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VirtualCardDebitRefundService
{
    public function __construct(
        private WhatsappWalletTopupNotifier $walletNotifier,
    ) {}
    /**
     * @return array{ok: bool, message: string, already_refunded?: bool}
     */
    public function refundDebit(
        int $walletId,
        string $reference,
        float $amount,
        string $reason,
        string $txnType = WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
    ): array {
        try {
            $alreadyRefunded = false;
            $balanceAfter = null;
            DB::transaction(function () use ($walletId, $reference, $amount, $reason, $txnType, &$alreadyRefunded, &$balanceAfter) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($walletId);
                $txn = WhatsappWalletTransaction::query()
                    ->where('external_reference', $reference)
                    ->where('whatsapp_wallet_id', $walletId)
                    ->where('type', $txnType)
                    ->first();
                if (! $w || ! $txn) {
                    throw new \RuntimeException('debit_txn_missing');
                }
                $meta = is_array($txn->meta) ? $txn->meta : [];
                if ($meta['refunded'] ?? false) {
                    $alreadyRefunded = true;

                    return;
                }
                $w->balance = round((float) $w->balance + $amount, 2);
                $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                $w->save();
                $balanceAfter = (float) $w->balance;
                $meta['refunded'] = true;
                $meta['refund_reason'] = $reason;
                $txn->update(['meta' => $meta]);
            });

            if ($alreadyRefunded) {
                return ['ok' => true, 'message' => 'Amount was already refunded.', 'already_refunded' => true];
            }

            if ($balanceAfter !== null) {
                $wallet = WhatsappWallet::query()->find($walletId);
                if ($wallet) {
                    $this->walletNotifier->notifyMoneyReceived($wallet, $amount, $balanceAfter, null, [
                        'credit_source' => 'refund',
                    ]);
                }
            }

            return ['ok' => true, 'message' => 'Amount refunded successfully.'];
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'debit_txn_missing') {
                return ['ok' => false, 'message' => 'Debit transaction not found for this operation.'];
            }
            Log::error('virtual_card.debit_refund_failed', ['wallet_id' => $walletId, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not refund wallet debit.'];
        }
    }
}
