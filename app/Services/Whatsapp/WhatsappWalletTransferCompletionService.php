<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPay\MevonPayPayoutMetaNormalizer;
use App\Services\MavonPayTransferService;
use App\Services\Payout\BankPayoutNarration;
use App\Services\Vtu\VtuProviderResolver;
use App\Services\WhatsappWalletBankPayoutService;
use Carbon\Carbon;
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
        private WhatsappWalletPendingP2pService $pendingP2p,
        private VtuProviderResolver $vtuResolver,
        private WhatsappCrossBorderP2pFxService $crossBorderFx,
        private WhatsappWalletCountryResolver $walletCountry,
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

    private function transferNoticeTimeLine(?Carbon $at = null): string
    {
        $at ??= now();

        return $at->copy()->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A').
            ' ('.(string) config('app.timezone').')';
    }

    private function maskPhoneTail(string $e164Digits): string
    {
        $d = preg_replace('/\D/', '', $e164Digits) ?? '';
        if (strlen($d) < 9) {
            return $e164Digits;
        }

        return substr($d, 0, 5).' •••• '.substr($d, -4);
    }

    private function accountLast4(string $acct): string
    {
        $digits = preg_replace('/\D/', '', $acct) ?? '';

        return strlen($digits) >= 4 ? substr($digits, -4) : $digits;
    }

    private function forwardableReceiptFooter(): string
    {
        return WhatsappWalletAppLinkCopy::receiptFooter();
    }

    /**
     * Optional PNG receipt (no balance). Failure is logged only.
     */
    private function maybeSendBankTransferReceiptImage(
        string $instance,
        string $phone,
        string $brand,
        string $beneficiary,
        string $bankName,
        string $acct,
        float $amount,
        string $reference,
        string $whenLine,
        ?string $sessionId = null,
        ?string $responseMessage = null,
    ): bool {
        if (! config('whatsapp.wallet.send_transfer_receipt_image', true)) {
            return false;
        }
        $png = WhatsappTransferReceiptImage::bankTransferPngBytes(
            $brand,
            $beneficiary,
            $bankName,
            $this->accountLast4($acct),
            $amount,
            $reference,
            $whenLine,
            $sessionId,
            $responseMessage,
        );
        if ($png === null) {
            return false;
        }
        $this->client->sendMedia(
            $instance,
            $phone,
            'image',
            'image/png',
            base64_encode($png),
            WhatsappWalletAppLinkCopy::imageReceiptCaption(),
            'transfer-receipt.png'
        );

        return true;
    }

    private function maybeSendP2pReceiptImage(
        string $instance,
        string $phone,
        string $brand,
        string $toMasked,
        float $amount,
        string $whenLine,
        string $receiptId,
        string $debitCurrency = 'NGN',
        ?string $recipientCreditLine = null,
    ): bool {
        if (! config('whatsapp.wallet.send_transfer_receipt_image', true)) {
            return false;
        }
        $png = WhatsappTransferReceiptImage::p2pSentPngBytes($brand, $toMasked, $amount, $whenLine, $receiptId, $debitCurrency, $recipientCreditLine);
        if ($png === null) {
            return false;
        }
        $this->client->sendMedia(
            $instance,
            $phone,
            'image',
            'image/png',
            base64_encode($png),
            WhatsappWalletAppLinkCopy::imageReceiptCaption(),
            'p2p-receipt.png'
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function sendWalletSubmenu(string $instance, string $phone, WhatsappWallet $wallet): void
    {
        $wallet = $wallet->fresh();
        if ($wallet->needsQuickWalletSetup()) {
            $this->client->sendText(
                $instance,
                $phone,
                WhatsappWalletOnboardingCopy::compactWalletSubmenuBody($wallet)
            );

            return;
        }

        $ngRails = $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164);
        $cur = $this->walletCountry->currencyForPhoneE164((string) $wallet->phone_e164);
        $bal = WhatsappWalletMoneyFormatter::format((float) $wallet->balance, $cur);
        $t1max = number_format((float) config('whatsapp.wallet.tier1_max_balance', 50000), 0);
        $isTier2 = $wallet->isTier2();
        $pinSection = $wallet->hasPin()
            ? ''
            : "*REGISTER* — PIN link first.\n\n";

        $vaBlock = '';
        if ($ngRails && $wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $vaBlock = "\n*Pay-in:* ".($wallet->mevon_bank_name ?? 'Rubies MFB').' *'.$wallet->mevon_virtual_account_number."*\n";
        }

        $tier1VaNote = '';
        if ($ngRails && ! $isTier2 && (int) $wallet->tier === WhatsappWallet::TIER_WHATSAPP_ONLY && $this->tier1TopupVa->isAvailable()) {
            $tier1VaNote = "Tier 1: *1* = new temp account each time.\n";
        }

        $upgradeLine = ($ngRails && ! $isTier2)
            ? "*3* Upgrade (permanent VA)\n"
            : '';

        $tier1HeadsUp = '';
        if (! $isTier2) {
            $tier1HeadsUp = $ngRails
                ? "Max ₦{$t1max} until *3* upgrade.\n"
                : "NG pay-in only · use *4* to receive.\n";
        }

        $brand = $this->waBrand();
        $bankNote = '';
        if ($ngRails) {
            $bankNote = $this->bankPayout->isConfigured()
                ? "*2* bank: {$brand} — debit only when payout succeeds."
                : "*2* bank: ledger until {$brand} live.";
        }

        $vtuLine = ($ngRails && $this->vtuResolver->active()->isConfigured())
            ? "*5* Bills (airtime / data / power)\n"
            : '';

        $settingsLine = $isTier2
            ? "*7* Settings (email code on/off)\n"
            : '';

        $line1 = $ngRails ? "*1* Add / receive\n" : '';
        $line2 = $ngRails ? "*2* Bank send\n" : '';
        $p2pTip = $ngRails
            ? "Paste *080…* / *234…* anytime for *4*.\n"
            : "*4* WhatsApp send (intl OK).\n";

        $casualLine = $ngRails
            ? "Or: *send 5k to Name Opay*\n\n"
            : '';

        $this->client->sendText(
            $instance,
            $phone,
            "*Wallet* *{$bal}*\n".
            $vaBlock.
            "\n".
            $line1.
            $line2.
            $upgradeLine.
            "*4* WhatsApp send\n".
            $p2pTip.
            $vtuLine.
            "*6* History (*MORE* / *PREV*)\n".
            $settingsLine.
            "\n".
            $pinSection.
            $tier1HeadsUp.
            ($tier1VaNote !== '' ? $tier1VaNote."\n" : '').
            ($bankNote !== '' ? $bankNote."\n\n" : '').
            $casualLine.
            WhatsappWalletAppLinkCopy::menuFooter()
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
    ): WalletTransferCompletionResult {
        $amount = isset($ctx['amount']) && is_numeric($ctx['amount']) ? (float) $ctx['amount'] : 0.0;
        $acct = isset($ctx['dest_acct']) && is_string($ctx['dest_acct']) ? $ctx['dest_acct'] : '';
        $bankName = isset($ctx['dest_bank']) && is_string($ctx['dest_bank']) ? $ctx['dest_bank'] : '';
        $bankCode = isset($ctx['dest_bank_code']) && is_string($ctx['dest_bank_code']) ? $ctx['dest_bank_code'] : '';
        $beneficiary = isset($ctx['dest_acct_name']) && is_string($ctx['dest_acct_name']) ? trim($ctx['dest_acct_name']) : '';
        $payoutAmount = isset($ctx['payout_amount']) && is_numeric($ctx['payout_amount'])
            ? round((float) $ctx['payout_amount'], 2)
            : $amount;
        $selfFee = isset($ctx['self_transfer_fee']) && is_numeric($ctx['self_transfer_fee'])
            ? round((float) $ctx['self_transfer_fee'], 2)
            : 0.0;
        $isSelf = ! empty($ctx['is_self_transfer']);

        if ($amount < 1 || strlen($acct) !== 10 || $bankCode === '' || $beneficiary === '') {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendWalletSubmenu($instance, $phone, $wallet->fresh());

            return WalletTransferCompletionResult::failed('Invalid transfer details.');
        }

        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->client->sendText(
                $instance,
                $phone,
                'Bank transfers are only for *Nigeria* wallet numbers. Use *4* for WhatsApp sends.'
            );
            $this->sendWalletSubmenu($instance, $phone, $wallet->fresh());

            return WalletTransferCompletionResult::failed('Bank transfers are only for Nigeria wallet numbers.');
        }

        if ($this->bankPayout->isConfigured()) {
            return $this->completeBankTransferWithMavon(
                $session,
                $instance,
                $phone,
                $wallet,
                $amount,
                $payoutAmount,
                $acct,
                $bankName,
                $bankCode,
                $beneficiary,
                $userTypedPinInChat,
                $isSelf,
                $selfFee
            );
        }

        try {
            DB::transaction(function () use ($wallet, $amount, $acct, $bankName, $bankCode, $beneficiary, $isSelf, $selfFee, $payoutAmount) {
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
                    'meta' => $this->bankTransferMeta($bankName, 'whatsapp_menu', 'ledger_only', $isSelf, $selfFee, $payoutAmount),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.transfer_failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            $this->client->sendText(
                $instance,
                $phone,
                '❌ Transfer could not be completed. Check balance and limits, then try again.'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );

            return WalletTransferCompletionResult::failed('Transfer could not be completed.');
        }

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();
        $when = $this->transferNoticeTimeLine();
        $tail = $this->accountLast4($acct);
        $brand = $this->waBrand();
        $ledgerRef = 'ledger-'.now()->format('Ymd-His');
        $ledgerStatus = 'Ledger-only (live bank payout not enabled)';
        $receiptOk = $this->maybeSendBankTransferReceiptImage(
            $instance,
            $phone,
            $brand,
            $beneficiary,
            $bankName,
            $acct,
            $payoutAmount,
            $ledgerRef,
            $when,
            null,
            $ledgerStatus,
        );
        $receiptBlock = WhatsappBankTransferReceiptDetails::whatsappBlock(null, $ledgerStatus);
        $pin = $this->pinDeleteReminderSuffix($userTypedPinInChat);
        $amountLine = $isSelf && $selfFee > 0
            ? '*You pay:* ₦'.number_format($amount, 2)."\n*Recipient gets:* ₦".number_format($payoutAmount, 2)."\n*Fee:* ₦".number_format($selfFee, 2)."\n"
            : '*Amount:* ₦'.number_format($amount, 2)."\n";
        if (! $receiptOk) {
            $this->client->sendText(
                $instance,
                $phone,
                "✅ *Transfer recorded*\n\n".
                "*To:* {$beneficiary}\n".
                "*Bank:* {$bankName}\n".
                "*Account:* ****{$tail}\n".
                $amountLine.
                "*Time:* {$when}\n".
                $receiptBlock.
                "\n\n🏦 *{$brand}* bank payouts are not on yet — this is *ledger-only* until support enables live sends.".
                $this->forwardableReceiptFooter().
                $pin
            );
        } elseif ($pin !== '') {
            $this->client->sendText($instance, $phone, ltrim($pin, "\n\n"));
        }
        $this->sendWalletSubmenu($instance, $phone, $wallet);

        $receipt = [
            'session_id' => '',
            'response_message' => $ledgerStatus,
            'reference' => $ledgerRef,
        ];

        return WalletTransferCompletionResult::success((float) $wallet->balance, 'Transfer recorded.', $receipt);
    }

    /**
     * @return array<string, mixed>
     */
    private function bankTransferMeta(
        string $bankName,
        string $channel,
        ?string $payoutMode,
        bool $isSelf,
        float $selfFee,
        float $payoutAmount,
    ): array {
        $meta = [
            'bank_name' => $bankName,
            'channel' => $channel,
        ];
        if ($payoutMode !== null) {
            $meta['payout_mode'] = $payoutMode;
        }
        if ($isSelf) {
            $meta['self_transfer'] = true;
            $meta['self_transfer_fee'] = $selfFee;
            $meta['payout_amount'] = $payoutAmount;
        }

        return $meta;
    }

    private function completeBankTransferWithMavon(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        float $amount,
        float $payoutAmount,
        string $acct,
        string $bankName,
        string $bankCode,
        string $beneficiary,
        bool $userTypedPinInChat,
        bool $isSelf = false,
        float $selfFee = 0.0,
    ): WalletTransferCompletionResult {
        $reference = $this->bankPayout->makeWalletPayoutReference();

        try {
            DB::transaction(function () use ($wallet, $amount, $payoutAmount, $acct, $bankName, $bankCode, $beneficiary, $reference, $isSelf, $selfFee) {
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

                $meta = $this->bankTransferMeta($bankName, 'whatsapp_menu', null, $isSelf, $selfFee, $payoutAmount);
                $meta['payout_pending'] = true;

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
                    'meta' => $meta,
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.transfer_debit_failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            $this->client->sendText(
                $instance,
                $phone,
                '❌ Transfer could not be completed. Check balance and limits, then try again.'.
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );

            return WalletTransferCompletionResult::failed('Transfer could not be completed.');
        }

        $walletFresh = $wallet->fresh();
        $txnRow = WhatsappWalletTransaction::query()
            ->where('external_reference', $reference)
            ->where('whatsapp_wallet_id', $wallet->id)
            ->first();

        $result = $this->bankPayout->sendTransfer(
            $payoutAmount,
            $bankCode,
            $bankName,
            $acct,
            $beneficiary,
            $reference,
            BankPayoutNarration::forWhatsapp(),
            $walletFresh,
            $txnRow?->id,
        );
        $bucket = $result['bucket'] ?? MavonPayTransferService::BUCKET_FAILED;

        DB::transaction(function () use ($wallet, $amount, $reference, $bucket, $result) {
            $txn = WhatsappWalletTransaction::query()
                ->where('external_reference', $reference)
                ->where('whatsapp_wallet_id', $wallet->id)
                ->first();
            if (! $txn) {
                Log::error('whatsapp.wallet.payout_txn_missing', ['reference' => $reference, 'wallet_id' => $wallet->id]);
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if ($w) {
                    $w->balance = round((float) $w->balance + $amount, 2);
                    $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                    $w->save();
                }

                return;
            }

            $meta = MevonPayPayoutMetaNormalizer::mergeIntoMeta(
                array_merge(is_array($txn->meta) ? $txn->meta : [], [
                    'payout_bucket' => $bucket,
                ]),
                $result,
            );

            $refund = $bucket === MavonPayTransferService::BUCKET_FAILED;

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
            } elseif ($bucket === MavonPayTransferService::BUCKET_PENDING) {
                $meta['payout_pending'] = true;
                $meta['whatsapp_payout_processing'] = true;
            } else {
                $meta['payout_pending'] = false;
                $meta['payout_reference'] = $result['reference'] ?? $reference;
            }

            $txn->update(['meta' => $meta]);
        });

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();

        $when = $this->transferNoticeTimeLine();

        $brand = $this->waBrand();
        $refShown = (string) ($result['reference'] ?? $reference);
        $acctTail = $this->accountLast4($acct);
        $receipt = $this->bankReceiptFromPayout($result, $refShown);
        $receiptBlock = WhatsappBankTransferReceiptDetails::whatsappBlock(
            $receipt['session_id'] !== '' ? $receipt['session_id'] : null,
            $receipt['response_message'] !== '' ? $receipt['response_message'] : null,
        );

        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $receiptOk = $this->maybeSendBankTransferReceiptImage(
                $instance,
                $phone,
                $brand,
                $beneficiary,
                $bankName,
                $acct,
                $payoutAmount,
                $refShown,
                $when,
                $receipt['session_id'] !== '' ? $receipt['session_id'] : null,
                $receipt['response_message'] !== '' ? $receipt['response_message'] : null,
            );
            $pin = $this->pinDeleteReminderSuffix($userTypedPinInChat);
            $amountLine = $isSelf && $selfFee > 0
                ? '*You paid:* ₦'.number_format($amount, 2)."\n*Recipient got:* ₦".number_format($payoutAmount, 2)."\n*Fee:* ₦".number_format($selfFee, 2)."\n"
                : '*Amount:* ₦'.number_format($amount, 2)."\n";
            if (! $receiptOk) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    "✅ *Bank transfer sent!*\n\n".
                    "*To:* {$beneficiary}\n".
                    "*Bank:* {$bankName}\n".
                    "*Account:* ****{$acctTail}\n".
                    $amountLine.
                    "*Time:* {$when}\n".
                    'Ref: *'.$refShown.'*'.
                    $receiptBlock.
                    $this->forwardableReceiptFooter().
                    $pin
                );
            } elseif ($pin !== '') {
                $this->client->sendText($instance, $phone, ltrim($pin, "\n\n"));
            }

            $this->sendWalletSubmenu($instance, $phone, $wallet);

            return WalletTransferCompletionResult::success((float) $wallet->balance, 'Bank transfer sent.', $receipt);
        }

        if ($bucket === MavonPayTransferService::BUCKET_PENDING) {
            $this->client->sendText(
                $instance,
                $phone,
                "⏳ *Bank transfer processing*\n\n".
                "To: *{$beneficiary}*\n".
                "{$bankName} / ****{$acctTail} · ₦".number_format($amount, 2)."\n".
                "*Time:* {$when}\n".
                $receiptBlock.
                "\n\nYour wallet has been *debited*. We are waiting for the bank to confirm — contact support if it stays pending.".
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );
            $this->sendWalletSubmenu($instance, $phone, $wallet);

            return WalletTransferCompletionResult::success((float) $wallet->balance, 'Bank transfer is processing.', $receipt);
        }

        $detail = $result['response_message'] ?? 'The bank could not accept this transfer.';
        $this->client->sendText(
            $instance,
            $phone,
            "⚠️ *Bank transfer not completed*\n\n".
            "Attempted to: *{$beneficiary}*\n".
            "{$bankName} / ****{$acctTail} · ₦".number_format($amount, 2)."\n".
            "*Time:* {$when}\n".
            $receiptBlock.
            "\n\n".
            $detail."\n\n".
            "Your wallet was *refunded*.\n\n".
            '💰 Balance now: *₦'.number_format((float) $wallet->balance, 2).'*'.
            $this->pinDeleteReminderSuffix($userTypedPinInChat)
        );
        $this->sendWalletSubmenu($instance, $phone, $wallet);

        return WalletTransferCompletionResult::failed('Bank transfer not completed.');
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
    ): WalletTransferCompletionResult {
        $recipient = isset($ctx['p2p_recipient_e164']) && is_string($ctx['p2p_recipient_e164'])
            ? $ctx['p2p_recipient_e164']
            : '';
        $amount = isset($ctx['p2p_amount']) && is_numeric($ctx['p2p_amount']) ? (float) $ctx['p2p_amount'] : 0.0;

        if ($recipient === '' || $amount < 1) {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendWalletSubmenu($instance, $phone, $wallet->fresh());

            return WalletTransferCompletionResult::failed('Invalid send details.');
        }

        $eval = $this->crossBorderFx->evaluateP2p($instance, $recipient, $amount, (string) $wallet->phone_e164);
        if ($eval['status'] === 'blocked' || $eval['status'] === 'missing_rate') {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->client->sendText(
                $instance,
                $phone,
                (string) ($eval['message'] ?? 'This send is not available.').
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );
            $this->sendWalletSubmenu($instance, $phone, $wallet->fresh());

            return WalletTransferCompletionResult::failed((string) ($eval['message'] ?? 'This send is not available.'));
        }

        $debitAmount = (float) $eval['debit'];
        $creditAmount = (float) $eval['credit'];
        $senderCur = (string) $eval['sender_currency'];
        $recvCur = (string) $eval['recipient_currency'];
        $isFx = $senderCur !== $recvCur;

        $recvRow = WhatsappWallet::query()
            ->where('phone_e164', $recipient)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! $recvRow) {
            $hold = $this->pendingP2p->createHold($wallet->fresh(), $recipient, $debitAmount, $instance, $creditAmount);
            if (! ($hold['ok'] ?? false)) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    ($hold['message'] ?? 'Send failed. Try again.').
                    $this->pinDeleteReminderSuffix($userTypedPinInChat)
                );

                return WalletTransferCompletionResult::failed((string) ($hold['message'] ?? 'Send failed.'));
            }

            $session->update(['chat_context' => ['step' => 'submenu']]);
            $wallet = $wallet->fresh();
            $masked = $this->maskPhoneTail($recipient);
            $debitFmt = WhatsappWalletMoneyFormatter::format($debitAmount, $senderCur);
            $creditNote = $isFx
                ? "\n*They receive:* ".WhatsappWalletMoneyFormatter::format($creditAmount, $recvCur)."\n"
                : "\n";
            $this->client->sendText(
                $instance,
                $phone,
                "⏳ *Sent — waiting for them*\n\n".
                '*To:* '.$masked."\n".
                "*You sent:* {$debitFmt}{$creditNote}".
                "They need to open *WALLET* on that *WhatsApp* number to receive.\n\n".
                "The funds are for them here until they do. They can send *CANCEL* to return it to you.\n\n".
                '💡 Open *WALLET* anytime here to see your balance.'.
                $this->forwardableReceiptFooter().
                $this->pinDeleteReminderSuffix($userTypedPinInChat)
            );
            $this->sendWalletSubmenu($instance, $phone, $wallet);

            return WalletTransferCompletionResult::success((float) $wallet->balance, 'Sent — waiting for recipient.');
        }

        try {
            DB::transaction(function () use ($wallet, $recipient, $debitAmount, $creditAmount, $phone, $senderCur, $recvCur, $isFx) {
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

                if (! $sender->hasPin() || ! $sender->canDebit($debitAmount)['ok'] || ! $recv->canCredit($creditAmount)['ok']) {
                    throw new \RuntimeException('limits');
                }

                $newSenderBal = round((float) $sender->balance - $debitAmount, 2);
                $newRecvBal = round((float) $recv->balance + $creditAmount, 2);

                $sender->balance = $newSenderBal;
                $sender->daily_transfer_total = round((float) $sender->daily_transfer_total + $debitAmount, 2);
                $sender->daily_transfer_for_date = now()->toDateString();
                $sender->pin_failed_attempts = 0;
                $sender->save();

                $recv->balance = $newRecvBal;
                $recv->save();

                $fxMeta = $isFx ? [
                    'cross_border' => true,
                    'sender_currency' => $senderCur,
                    'recipient_currency' => $recvCur,
                    'recipient_credit_amount' => $creditAmount,
                ] : [];

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $sender->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                    'amount' => $debitAmount,
                    'balance_after' => $newSenderBal,
                    'counterparty_phone_e164' => $recipient,
                    'meta' => array_merge(['channel' => 'whatsapp_menu'], $fxMeta),
                ]);

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $recv->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                    'amount' => $creditAmount,
                    'balance_after' => $newRecvBal,
                    'counterparty_phone_e164' => $phone,
                    'meta' => array_merge(['channel' => 'whatsapp_menu'], $fxMeta),
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

            return WalletTransferCompletionResult::failed('Send failed.');
        }

        $sentAt = now();
        $when = $this->transferNoticeTimeLine($sentAt);
        $senderDisplayName = $wallet->normalizedSenderName();

        $recvNotify = WhatsappWallet::query()->where('phone_e164', $recipient)->first();
        $recvFresh = $recvNotify?->fresh();
        $recipientDisplayName = $recvFresh?->normalizedSenderName();

        if ($recvFresh) {
            $crossBorderFx = $isFx ? [
                'debit_amount' => $debitAmount,
                'debit_currency' => $senderCur,
                'credit_amount' => $creditAmount,
                'credit_currency' => $recvCur,
            ] : null;
            $this->walletNotifier->notifyP2pReceived(
                $instance,
                $recvFresh,
                $creditAmount,
                $phone,
                $senderDisplayName,
                $sentAt,
                $recvCur,
                $crossBorderFx,
            );
        }

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();
        $toLine = $recipientDisplayName !== null
            ? "*To:* {$recipientDisplayName}\n*Number:* ".$this->maskPhoneTail($recipient)
            : '*To:* '.$this->maskPhoneTail($recipient);
        $brand = $this->waBrand();
        $maskedTo = $this->maskPhoneTail($recipient);
        $receiptId = 'P2P-'.$sentAt->timezone(config('app.timezone'))->format('Ymd-His');
        $debitFmt = WhatsappWalletMoneyFormatter::format($debitAmount, $senderCur);
        $amountBlock = $isFx
            ? "*You sent:* {$debitFmt}\n*They receive:* ".WhatsappWalletMoneyFormatter::format($creditAmount, $recvCur)
            : '*Amount:* '.$debitFmt;
        $recvReceiptLine = $isFx
            ? 'They receive: '.strtoupper($recvCur).' '.number_format($creditAmount, 2)
            : null;
        $receiptOk = $this->maybeSendP2pReceiptImage(
            $instance,
            $phone,
            $brand,
            $maskedTo,
            $debitAmount,
            $when,
            $receiptId,
            $senderCur,
            $recvReceiptLine
        );
        $pin = $this->pinDeleteReminderSuffix($userTypedPinInChat);
        if (! $receiptOk) {
            $this->client->sendText(
                $instance,
                $phone,
                "✅ *Sent!*\n\n".
                $toLine."\n".
                $amountBlock."\n".
                "*Time:* {$when}\n".
                'Receipt: *'.$receiptId.'*'.
                $this->forwardableReceiptFooter().
                $pin
            );
        } elseif ($pin !== '') {
            $this->client->sendText($instance, $phone, ltrim($pin, "\n\n"));
        }
        $this->sendWalletSubmenu($instance, $phone, $wallet);

        return WalletTransferCompletionResult::success((float) $wallet->balance, 'Transfer sent.');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{session_id: string, response_message: string, reference: string}
     */
    private function bankReceiptFromPayout(array $result, ?string $referenceFallback = null): array
    {
        $receipt = WhatsappBankTransferReceiptDetails::fromPayoutResult($result);
        if ($receipt['reference'] === '' && $referenceFallback !== null && $referenceFallback !== '') {
            $receipt['reference'] = $referenceFallback;
        }

        return $receipt;
    }
}
