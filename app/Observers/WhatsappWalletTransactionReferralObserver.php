<?php

namespace App\Observers;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\WalletReferralAttributionService;
use App\Services\Consumer\WalletReferralBonusService;
use Illuminate\Support\Facades\Log;

final class WhatsappWalletTransactionReferralObserver
{
    public function created(WhatsappWalletTransaction $txn): void
    {
        try {
            $wallet = $txn->wallet ?? WhatsappWallet::query()->find($txn->whatsapp_wallet_id);
            if (! $wallet) {
                return;
            }

            if ($txn->type === WhatsappWalletTransaction::TYPE_TOPUP) {
                app(WalletReferralBonusService::class)->onTopup($wallet, $txn);

                return;
            }

            if ($txn->type === WhatsappWalletTransaction::TYPE_P2P_CREDIT) {
                $senderPhone = trim((string) ($txn->counterparty_phone_e164 ?? ''));
                if ($senderPhone !== '') {
                    $sender = WhatsappWallet::query()
                        ->where('phone_e164', $senderPhone)
                        ->where('status', WhatsappWallet::STATUS_ACTIVE)
                        ->first();
                    if ($sender) {
                        app(WalletReferralAttributionService::class)
                            ->attributeFromFirstP2pCredit($wallet, $sender);
                    }
                }
            }

            if (in_array($txn->type, WhatsappWalletTransaction::REFERRAL_COUNTED_TYPES, true)) {
                app(WalletReferralBonusService::class)->onCountedTransaction($wallet, $txn);
            }
        } catch (\Throwable $e) {
            Log::warning('wallet.referral.observer_failed', [
                'transaction_id' => $txn->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
