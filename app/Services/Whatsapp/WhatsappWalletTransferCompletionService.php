<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;
use App\Services\VtuNg\VtuNgApiClient;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes WhatsApp wallet bank / P2P transfers after PIN, email OTP, or web PIN confirmation.
 */
class WhatsappWalletTransferCompletionService
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletBankPayoutService $bankPayout,
        private WhatsappWalletTier1TopupVaService $tier1TopupVa,
        private WhatsappWalletTopupNotifier $walletNotifier,
        private VtuNgApiClient $vtuApi,
    ) {}

    private function waBrand(): string
    {
        return (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
    }

    private function pinDeleteReminderSuffix(bool $userTypedPinInChat): string
    {
        if (! $userTypedPinInChat) {
            return '';
        }

        return "\n\n*Security:* Long-press your PIN message and tap *Delete* so it is not left in this chat.";
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function sendWalletSubmenu(string $instance, string $phone, WhatsappWallet $wallet): void
    {
        $bal = '₦'.number_format((float) $wallet->balance, 2);
        $t1max = number_format((float) config('whatsapp.wallet.tier1_max_balance', 50000), 0);
        $pinLine = $wallet->hasPin()
            ? 'Your wallet PIN is set (needed for transfers).'
            : '*REGISTER* — set a 4-digit wallet PIN (required before *2* Transfer).';

        $vaBlock = '';
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $vaBlock = "\n*Tier 2 account*\n".
                'Bank: *'.($wallet->mevon_bank_name ?? 'Rubies MFB')."*\n".
                'Account: *'.$wallet->mevon_virtual_account_number."*\n";
        }

        $tier1VaNote = '';
        if ((int) $wallet->tier === WhatsappWallet::TIER_WHATSAPP_ONLY && $this->tier1TopupVa->isAvailable()) {
            $tier1VaNote = "\nTier 1: *1* gives a *new temporary* pay-in account each time.\n";
        }

        $brand = $this->waBrand();
        $bankNote = $this->bankPayout->isConfigured()
            ? "Bank sends use *{$brand}*: we only keep the debit when the transfer is *confirmed successful* — failed or *pending* responses refund your wallet."
            : "Bank sends are recorded on your balance; connect *{$brand}* for live payouts.";

        $vtuLine = $this->vtuApi->isConfigured()
            ? "*5* — Airtime / Data / Electricity (VTU.ng)\n"
            : '';

        $this->client->sendText(
            $instance,
            $phone,
            "*WhatsApp wallet*\n".
            "Balance: *{$bal}*\n".
            $vaBlock.
            "\n".
            "*1* — Receive / Top up\n".
            "*2* — Transfer to bank (… amount → email code or secure link / PIN)\n".
            "*3* — Tier 2 (*UPGRADE*): permanent bank account (KYC)\n".
            "*4* — Send to another *WhatsApp* number (P2P; they must open *WALLET* once)\n".
            $vtuLine.
            "\n".
            "{$pinLine}\n\n".
            "Tier 1 cap: ₦{$t1max} balance & same daily send limit until upgraded.\n".
            $tier1VaNote.
            "{$bankNote}\n\n".
            '*BACK* or *MENU* — leave wallet  *STOP* — pause bot'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function completeBankTransfer(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        bool $userTypedPinInChat
    ): void {
        $amount = isset($ctx['amount']) && is_numeric($ctx['amount']) ? (float) $ctx['amount'] : 0.0;
        $acct = isset($ctx['dest_acct']) && is_string($ctx['dest_acct']) ? $ctx['dest_acct'] : '';
        $bankName = isset($ctx['dest_bank']) && is_string($ctx['dest_bank']) ? $ctx['dest_bank'] : '';
        $bankCode = isset($ctx['dest_bank_code']) && is_string($ctx['dest_bank_code']) ? $ctx['dest_bank_code'] : '';
        $beneficiary = isset($ctx['dest_acct_name']) && is_string($ctx['dest_acct_name']) ? trim($ctx['dest_acct_name']) : '';

        if ($amount < 1 || strlen($acct) !== 10 || $bankCode === '' || $beneficiary === '') {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendWalletSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        if ($this->bankPayout->isConfigured()) {
            $this->completeBankTransferWithMavon(
                $session,
                $instance,
                $phone,
                $wallet,
                $amount,
                $acct,
                $bankName,
                $bankCode,
                $beneficiary,
                $userTypedPinInChat
            );

            return;
        }

        try {
            DB::transaction(function () use ($wallet, $amount, $acct, $bankName, $bankCode, $beneficiary) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($amount);
                if (! $w->hasPin() || ! $check['ok']) {
                    throw new \RuntimeException('cannot_debit');
                }
                $newBal = round((float) $w->balance - $amount, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->pin_failed_attempts = 0;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiary,
                    'meta' => [
                        'bank_name' => $bankName,
                        'channel' => 'whatsapp_menu',
                        'payout_mode' => 'ledger_only',
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.transfer_failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            $this->client->sendText(
                $instance,
                $phone,
                'Transfer could not be completed. Check balance and limits, then try again.'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );

            return;
        }

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();
        $this->client->sendText(
            $instance,
            $phone,
            "*Transfer recorded*\n\n".
            '₦'.number_format($amount, 2)." → {$bankName} / {$acct} ({$beneficiary}).\n".
            '*'.$this->waBrand().'* bank payouts are not enabled — this is ledger-only until support turns them on.'."\n\n".
            'New balance: *₦'.number_format((float) $wallet->balance, 2).'*'.
            $this->pinDeleteReminderSuffix($userTypedPinInChat)
        );
        $this->sendWalletSubmenu($instance, $phone, $wallet);
    }

    private function completeBankTransferWithMavon(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        float $amount,
        string $acct,
        string $bankName,
        string $bankCode,
        string $beneficiary,
        bool $userTypedPinInChat
    ): void {
        $reference = $this->bankPayout->makeWalletPayoutReference();

        try {
            DB::transaction(function () use ($wallet, $amount, $acct, $bankName, $bankCode, $beneficiary, $reference) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($amount);
                if (! $w->hasPin() || ! $check['ok']) {
                    throw new \RuntimeException('cannot_debit');
                }
                $newBal = round((float) $w->balance - $amount, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->pin_failed_attempts = 0;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiary,
                    'external_reference' => $reference,
                    'meta' => [
                        'bank_name' => $bankName,
                        'channel' => 'whatsapp_menu',
                        'payout_pending' => true,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.transfer_debit_failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            $this->client->sendText(
                $instance,
                $phone,
                'Transfer could not be completed. Check balance and limits, then try again.'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );

            return;
        }

        $result = $this->bankPayout->sendTransfer($amount, $bankCode, $bankName, $acct, $beneficiary, $reference);
        $bucket = $result['bucket'] ?? MavonPayTransferService::BUCKET_FAILED;

        DB::transaction(function () use ($wallet, $amount, $reference, $bucket, $result) {
            $txn = WhatsappWalletTransaction::query()
                ->where('external_reference', $reference)
                ->where('whatsapp_wallet_id', $wallet->id)
                ->first();
            if (! $txn) {
                Log::error('whatsapp.wallet.payout_txn_missing', ['reference' => $reference]);

                return;
            }

            $meta = array_merge(is_array($txn->meta) ? $txn->meta : [], [
                'payout_bucket' => $bucket,
                'payout_response_code' => $result['response_code'] ?? null,
                'payout_response_message' => $result['response_message'] ?? null,
            ]);

            $refund = $bucket === MavonPayTransferService::BUCKET_FAILED
                || $bucket === MavonPayTransferService::BUCKET_PENDING;

            if ($refund) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if ($w) {
                    $w->balance = round((float) $w->balance + $amount, 2);
                    $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                    $w->save();
                }
                $meta['reversed_at'] = now()->toIso8601String();
                $meta['payout_pending'] = false;
                $meta['payout_failed'] = true;
                if ($bucket === MavonPayTransferService::BUCKET_PENDING) {
                    $meta['whatsapp_refund_reason'] = 'provider_pending_not_confirmed';
                }
            } else {
                $meta['payout_pending'] = false;
                $meta['payout_reference'] = $result['reference'] ?? $reference;
            }

            $txn->update(['meta' => $meta]);
        });

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();

        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $this->client->sendText(
                $instance,
                $phone,
                "*Bank transfer sent*\n\n".
                '₦'.number_format($amount, 2)." → {$bankName} / {$acct}\n".
                'Ref: *'.($result['reference'] ?? $reference)."*\n\n".
                'Balance: *₦'.number_format((float) $wallet->balance, 2).'*'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );
        } else {
            $detail = $bucket === MavonPayTransferService::BUCKET_PENDING
                ? '*'.$this->waBrand().'* returned *pending* (not a final success). Transfers only complete when the bank confirms — your wallet has been *refunded*.'
                : ($result['response_message'] ?? 'The bank could not accept this transfer.');
            $this->client->sendText(
                $instance,
                $phone,
                "*Bank transfer not completed*\n\n".
                $detail."\n\n".
                "Your wallet was *refunded*.\n\n".
                'Balance: *₦'.number_format((float) $wallet->balance, 2).'*'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );
        }

        $this->sendWalletSubmenu($instance, $phone, $wallet);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function completeP2pTransfer(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $ctx,
        bool $userTypedPinInChat
    ): void {
        $recipient = isset($ctx['p2p_recipient_e164']) && is_string($ctx['p2p_recipient_e164'])
            ? $ctx['p2p_recipient_e164']
            : '';
        $amount = isset($ctx['p2p_amount']) && is_numeric($ctx['p2p_amount']) ? (float) $ctx['p2p_amount'] : 0.0;

        if ($recipient === '' || $amount < 1) {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendWalletSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        try {
            DB::transaction(function () use ($wallet, $recipient, $amount, $phone) {
                $recvId = WhatsappWallet::query()->where('phone_e164', $recipient)->value('id');
                $ids = array_values(array_unique(array_filter([$wallet->id, $recvId])));
                if (count($ids) < 2) {
                    throw new \RuntimeException('recipient_missing');
                }
                sort($ids, SORT_NUMERIC);
                $locked = [];
                foreach ($ids as $id) {
                    $w = WhatsappWallet::query()->lockForUpdate()->find($id);
                    if (! $w) {
                        throw new \RuntimeException('wallet_missing');
                    }
                    $locked[$id] = $w;
                }

                $sender = $locked[$wallet->id] ?? null;
                $recv = null;
                foreach ($locked as $w) {
                    if ((string) $w->phone_e164 === $recipient) {
                        $recv = $w;

                        break;
                    }
                }
                if (! $sender || ! $recv) {
                    throw new \RuntimeException('pair_missing');
                }

                $sender->resetDailyTransferIfNeeded();
                $recv->resetDailyTransferIfNeeded();

                if (! $sender->hasPin() || ! $sender->canDebit($amount)['ok'] || ! $recv->canCredit($amount)['ok']) {
                    throw new \RuntimeException('limits');
                }

                $newSenderBal = round((float) $sender->balance - $amount, 2);
                $newRecvBal = round((float) $recv->balance + $amount, 2);

                $sender->balance = $newSenderBal;
                $sender->daily_transfer_total = round((float) $sender->daily_transfer_total + $amount, 2);
                $sender->daily_transfer_for_date = now()->toDateString();
                $sender->pin_failed_attempts = 0;
                $sender->save();

                $recv->balance = $newRecvBal;
                $recv->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $sender->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                    'amount' => $amount,
                    'balance_after' => $newSenderBal,
                    'counterparty_phone_e164' => $recipient,
                    'meta' => ['channel' => 'whatsapp_menu'],
                ]);

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $recv->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                    'amount' => $amount,
                    'balance_after' => $newRecvBal,
                    'counterparty_phone_e164' => $phone,
                    'meta' => ['channel' => 'whatsapp_menu'],
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.p2p_failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            $this->client->sendText(
                $instance,
                $phone,
                'Send failed (limits or availability). Check balance and try again.'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );

            return;
        }

        $recvNotify = WhatsappWallet::query()->where('phone_e164', $recipient)->first();
        if ($recvNotify) {
            $this->walletNotifier->notifyP2pReceived(
                $instance,
                $recvNotify->fresh(),
                $amount,
                (float) $recvNotify->balance,
                $phone
            );
        }

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();
        $this->client->sendText(
            $instance,
            $phone,
            "*Sent*\n\n".
            '₦'.number_format($amount, 2).' → WhatsApp *'.$recipient."*\n\n".
            'Your new balance: *₦'.number_format((float) $wallet->balance, 2).'*'.
            $this->pinDeleteReminderSuffix($userTypedPinInChat)
        );
        $this->sendWalletSubmenu($instance, $phone, $wallet);
    }
}
