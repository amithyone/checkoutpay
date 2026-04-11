<?php

namespace App\Services\Whatsapp;

use App\Models\Bank;
use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * In-chat WhatsApp wallet hub: balance, top-up info, MavonPay bank transfer, P2P, PIN setup.
 */
class WhatsappWaWalletMenuHandler
{
    public const FLOW = 'wa_wallet';

    private const PIN_LEN = 4;

    private const MAX_PIN_FAILS = 5;

    private const PIN_LOCK_MINUTES = 15;

    private const SENDER_NAME_MIN_LEN = 2;

    private const SENDER_NAME_MAX_LEN = 120;

    private const TRANSFER_OTP_LEN = 6;

    /** WhatsApp history list: 6 lines per message; paginate with MORE/PREV. */
    private const TX_HISTORY_PAGE_SIZE = 6;

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletUpgradeFlowHandler $upgradeFlow,
        private WhatsappCheckoutServicesMenuHandler $checkoutServicesMenu,
        private WhatsappWalletBankPayoutService $bankPayout,
        private WhatsappWalletTier1TopupVaService $tier1TopupVa,
        private WhatsappWalletTopupNotifier $walletNotifier,
        private WhatsappWalletTransferCompletionService $transferCompletion,
        private WhatsappWalletSecureTransferAuthService $secureTransferAuth,
        private WhatsappWalletVtuFlowHandler $vtuFlow,
        private WhatsappWalletPinSetupWebService $pinSetupWeb,
        private WhatsappWalletPendingP2pService $pendingP2p,
    ) {}

    public function openMenu(WhatsappSession $session, string $instance, string $phone, ?Renter $renter): void
    {
        $wallet = $this->findOrCreateWallet($phone, $renter);
        $this->pendingP2p->tryClaimForWallet($wallet->fresh(), $instance);
        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'submenu'],
        ]);
        $this->sendSubmenu($instance, $phone, $wallet->fresh());
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function handle(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        ?Renter $linkedRenter
    ): void {
        $cmd = $this->normalizeWalletCommand($text);

        $ctx = $session->chat_context;
        if (! is_array($ctx)) {
            $ctx = [];
        }

        $step = (string) ($ctx['step'] ?? 'submenu');

        if (in_array($cmd, ['MENU', 'MAIN', 'START', 'HOME'], true)) {
            $this->exitToMain($session, $instance, $phone, $linkedRenter);

            return;
        }

        if (in_array($cmd, ['UPGRADE', 'TIER2', 'TIER 2'], true)) {
            $this->forgetPinSetupWebTokenFromSession($session);
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->upgradeFlow->start($session->fresh(), $instance, $phone);

            return;
        }

        if (in_array($cmd, ['CANCEL'], true) && (str_starts_with($step, 'transfer_') || str_starts_with($step, 'p2p_') || $step === 'wallet_tx_history')) {
            $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

            return;
        }

        if ($cmd === 'BACK') {
            if ($step === 'submenu') {
                $this->exitToMain($session, $instance, $phone, $linkedRenter);

                return;
            }
            if ($step === 'wallet_tx_history') {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            if ($step === 'p2p_verify_recipient') {
                $session->update(['chat_context' => ['step' => 'p2p_phone']]);
                $this->sendP2pPhoneStepPrompt($instance, $phone);

                return;
            }
            if (str_starts_with($step, 'transfer_') || str_starts_with($step, 'p2p_')) {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            if (in_array($step, ['pin_new', 'pin_confirm', 'pin_sender_name'], true)) {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
        }

        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);
        if ($step === 'submenu') {
            $this->pendingP2p->tryClaimForWallet($wallet->fresh(), $instance);
        }

        match ($step) {
            'submenu' => $this->handleSubmenu($session, $instance, $phone, $text, $cmd, $wallet, $linkedRenter),
            'pin_new' => $this->handlePinNew($session, $instance, $phone, $text, $wallet),
            'pin_confirm' => $this->handlePinConfirm($session, $instance, $phone, $text, $ctx, $wallet),
            'pin_sender_name' => $this->handlePinSenderName($session, $instance, $phone, $text, $wallet, $linkedRenter),
            'transfer_acct' => $this->handleTransferAcct($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_bank' => $this->handleTransferBank($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_beneficiary' => $this->handleTransferBeneficiary($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_amount' => $this->handleTransferAmount($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_otp' => $this->handleTransferOtp($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_pin' => $this->handleTransferPin($session, $instance, $phone, $text, $ctx, $wallet, $linkedRenter),
            'p2p_phone' => $this->handleP2pPhone($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_verify_recipient' => $this->handleP2pVerifyRecipient($session, $instance, $phone, $text, $cmd, $ctx, $wallet),
            'p2p_amount' => $this->handleP2pAmount($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_otp' => $this->handleP2pOtp($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_pin' => $this->handleP2pPin($session, $instance, $phone, $text, $ctx, $wallet, $linkedRenter),
            'wallet_tx_history' => $this->handleWalletTransactionHistory($session, $instance, $phone, $cmd, $wallet),
            default => $this->recoverSubmenu($session, $instance, $phone, $wallet),
        };
    }

    /**
     * WhatsApp users often send *1* or _1_; normalize so submenu and shortcuts match.
     */
    private function normalizeWalletCommand(string $text): string
    {
        $t = trim($text);
        $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t) ?? $t;
        $t = preg_replace('/^[\*_~\s]+|[\*_~\s]+$/u', '', $t) ?? $t;
        $t = trim($t);
        for ($i = 0; $i <= 9; $i++) {
            $fw = mb_chr(0xFF10 + $i, 'UTF-8');
            if ($fw !== '' && $fw !== false) {
                $t = str_replace($fw, (string) $i, $t);
            }
        }

        return WhatsappMenuInputNormalizer::mapNavigationShortcuts(strtoupper($t));
    }

    private function waBrand(): string
    {
        return (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
    }

    private function findOrCreateWallet(string $phone, ?Renter $renter): WhatsappWallet
    {
        $w = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        if ($renter && $w->renter_id === null) {
            $w->renter_id = $renter->id;
            $w->save();
        }

        return $w->fresh();
    }

    private function sendSubmenu(string $instance, string $phone, WhatsappWallet $wallet): void
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

        $vtuLine = $this->vtuFlow->isAvailable()
            ? "*5* — Airtime / Data / Electricity\n"
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
            '*4* — Send to another *WhatsApp* number (P2P; new users have *'.WhatsappWalletPendingP2pService::claimMinutes()." min* to open *WALLET* and claim)\n".
            $vtuLine.
            "*6* — Transaction history (*6* per page; *MORE* / *PREV*)\n".
            "\n".
            "{$pinLine}\n\n".
            "Tier 1 cap: ₦{$t1max} balance & same daily send limit until upgraded.\n".
            $tier1VaNote.
            "{$bankNote}\n\n".
            WhatsappMenuInputNormalizer::navigationHelpFooter()."  *STOP* — pause bot"
        );
    }

    private function handleSubmenu(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): void {
        if (in_array($cmd, ['REGISTER', 'PIN'], true)) {
            if ($wallet->hasPin()) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    'A wallet PIN is already set on this number. Use the web wallet or support to reset it.'
                );

                return;
            }
            $created = $this->pinSetupWeb->createAndStoreToken($session->fresh(), $instance, $phone, $wallet);
            if (! ($created['ok'] ?? false) || ! isset($created['token']) || ! is_string($created['token'])) {
                $this->client->sendText($instance, $phone, 'Could not start PIN setup. Try again or contact support.');

                return;
            }
            $token = $created['token'];
            $session->update([
                'chat_context' => [
                    'step' => 'pin_new',
                    'pin_setup_web_token' => $token,
                ],
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Set wallet PIN*\n\n".
                "Send a *4-digit* PIN here (numbers only), then confirm — *or* open the page in the *next message* to set your PIN privately.\n\n".
                '*BACK* — cancel'
            );
            $this->client->sendText($instance, $phone, $this->pinSetupWeb->setupUrl($token));

            return;
        }

        if ($cmd === '1' || in_array($cmd, ['RECEIVE', 'TOPUP', 'TOP UP'], true)) {
            try {
                $this->sendReceiveTopupHelp($instance, $phone, $wallet);
            } catch (\Throwable $e) {
                Log::error('whatsapp.wallet.receive_topup_help_failed', [
                    'error' => $e->getMessage(),
                    'phone' => $phone,
                ]);
                $this->client->sendText(
                    $instance,
                    $phone,
                    "We couldn't load top-up details just now. Try again in a moment.\n\n".
                    'Tier 2 users: your bank details are unchanged. Tier 1: try *UPGRADE* or *MENU*.'
                );
            }

            return;
        }

        if ($cmd === '2' || $cmd === 'TRANSFER') {
            if (! $wallet->hasPin()) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Set a wallet PIN first. Reply *REGISTER*.'
                );

                return;
            }
            if ($wallet->isPinLocked()) {
                $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked. Try again later or contact support.');

                return;
            }
            if ($wallet->normalizedSenderName() === null) {
                $this->startSenderNameStep($session, $instance, $phone);

                return;
            }
            $session->update([
                'chat_context' => ['step' => 'transfer_acct'],
            ]);
            $this->sendTransferAccountStep($instance, $phone, $wallet);

            return;
        }

        if ($cmd === '5' || $cmd === 'VTU') {
            if (! $this->vtuFlow->isAvailable()) {
                $this->client->sendText($instance, $phone, 'Airtime, data, and electricity payments are not available right now.');

                return;
            }
            if (! $wallet->hasPin()) {
                $this->client->sendText($instance, $phone, 'Set a wallet PIN first. Reply *REGISTER*.');

                return;
            }
            if ($wallet->isPinLocked()) {
                $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked. Try again later or contact support.');

                return;
            }
            if ($wallet->normalizedSenderName() === null) {
                $this->startSenderNameStep($session, $instance, $phone);

                return;
            }
            $this->vtuFlow->start($session->fresh(), $instance, $phone, $linkedRenter);

            return;
        }

        if ($cmd === '4' || $cmd === 'P2P' || $cmd === 'SEND') {
            if (! $wallet->hasPin()) {
                $this->client->sendText($instance, $phone, 'Set a wallet PIN first. Reply *REGISTER*.');

                return;
            }
            if ($wallet->isPinLocked()) {
                $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked. Try again later or contact support.');

                return;
            }
            if ($wallet->normalizedSenderName() === null) {
                $this->startSenderNameStep($session, $instance, $phone);

                return;
            }
            $session->update(['chat_context' => ['step' => 'p2p_phone']]);
            $this->sendP2pPhoneStepPrompt($instance, $phone);

            return;
        }

        if ($cmd === '3') {
            $this->forgetPinSetupWebTokenFromSession($session);
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->upgradeFlow->start($session->fresh(), $instance, $phone);

            return;
        }

        if ($cmd === '6' || $cmd === 'HISTORY' || $cmd === 'STATEMENT' || $cmd === 'TRANSACTIONS') {
            $session->update([
                'chat_context' => [
                    'step' => 'wallet_tx_history',
                    'wallet_tx_page' => 0,
                ],
            ]);
            $this->sendWalletTransactionHistoryPage($instance, $phone, $wallet->fresh(), 0);

            return;
        }

        if ($cmd === 'WALLET' || $cmd === '') {
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        $range = $this->vtuFlow->isAvailable() ? '*1*–*6*' : '*1*–*4*, *6*';
        $this->client->sendText(
            $instance,
            $phone,
            "Reply {$range}, or *REGISTER* / *UPGRADE*. *MENU* — main categories."
        );
    }

    private function sendReceiveTopupHelp(string $instance, string $phone, WhatsappWallet $wallet): void
    {
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $this->client->sendText(
                $instance,
                $phone,
                "*Receive / Top up*\n\n".
                'Transfer to your dedicated account (Tier 2):'."\n".
                '*Bank:* '.($wallet->mevon_bank_name ?? 'Rubies MFB')."\n".
                '*Account:* *'.$wallet->mevon_virtual_account_number."*\n\n".
                'Use your own bank app; funds will reflect when our bank confirms.'
            );

            return;
        }

        $t1max = number_format((float) config('whatsapp.wallet.tier1_max_balance', 50000), 0);
        $issued = $this->tier1TopupVa->issueFreshVa($wallet->fresh());
        if ($issued['ok'] ?? false) {
            $acct = $issued['account_number'] ?? '';
            $aname = $issued['account_name'] ?? '';
            $bname = $issued['bank_name'] ?? '';
            $exp = isset($issued['expires_at']) ? (string) $issued['expires_at'] : '';
            $expLine = '';
            if ($exp !== '') {
                try {
                    $expLine = 'Use before: *'.Carbon::parse($exp)->timezone(config('app.timezone'))->format('Y-m-d H:i').'* ('.config('app.timezone').")\n\n";
                } catch (\Throwable) {
                    $expLine = "Use before this VA expires (see dashboard if unsure).\n\n";
                }
            }

            $this->client->sendText(
                $instance,
                $phone,
                "*Receive / Top up (Tier 1)*\n\n".
                "This is a *temporary* account — a *new* one is created each time you tap *1*.\n\n".
                "*Bank:* {$bname}\n".
                "*Account:* *{$acct}*\n".
                "*Name:* {$aname}\n\n".
                "Transfer from your bank app. We credit this wallet when *{$this->waBrand()}* confirms your payment.\n".
                $expLine.
                "Max wallet balance Tier 1: ₦{$t1max}.\n\n".
                'Others can also send via *WALLET* → *4* (P2P). Permanent VA: *3* *UPGRADE*.'
            );

            return;
        }

        $why = $issued['message'] ?? 'Temporary top-up is not available.';

        $this->client->sendText(
            $instance,
            $phone,
            "*Receive / Top up (Tier 1)*\n\n".
            "{$why}\n\n".
            "You can still receive via *WALLET* → *4* (P2P) if the sender uses this chat bot.\n\n".
            "Max balance Tier 1: ₦{$t1max}.\n\n".
            'For a *fixed* bank account: *3* or *UPGRADE* (Tier 2 KYC).'
        );
    }

    private function handlePinNew(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        WhatsappWallet $wallet
    ): void {
        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::PIN_LEN) {
            $this->client->sendText($instance, $phone, 'Send exactly *4 digits* for your PIN.');

            return;
        }

        $prev = $session->chat_context;
        $nextCtx = [
            'step' => 'pin_confirm',
            'pin_temp' => Hash::make($digits),
        ];
        if (is_array($prev) && isset($prev['pin_setup_web_token']) && is_string($prev['pin_setup_web_token'])) {
            $nextCtx['pin_setup_web_token'] = $prev['pin_setup_web_token'];
        }
        $session->update(['chat_context' => $nextCtx]);
        $this->client->sendText(
            $instance,
            $phone,
            '*Confirm PIN*\n\nSend the *same 4 digits* again.'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handlePinConfirm(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $hash = $ctx['pin_temp'] ?? null;
        if (! is_string($hash) || $hash === '') {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::PIN_LEN || ! Hash::check($digits, $hash)) {
            $retryCtx = ['step' => 'pin_new'];
            if (isset($ctx['pin_setup_web_token']) && is_string($ctx['pin_setup_web_token'])) {
                $retryCtx['pin_setup_web_token'] = $ctx['pin_setup_web_token'];
            }
            $session->update(['chat_context' => $retryCtx]);
            $this->client->sendText($instance, $phone, 'PINs did not match. Send a new *4-digit* PIN.');

            return;
        }

        $wallet->pin_hash = $hash;
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        if (isset($ctx['pin_setup_web_token']) && is_string($ctx['pin_setup_web_token'])) {
            $this->pinSetupWeb->forgetToken($ctx['pin_setup_web_token']);
        }

        $session->update(['chat_context' => ['step' => 'pin_sender_name']]);
        $this->client->sendText(
            $instance,
            $phone,
            "*PIN saved*\n\n".
            "*Your send name*\n\n".
            'Send the name you want shown on your transfers (e.g. your full name). '.
            'Between *'.self::SENDER_NAME_MIN_LEN.'* and *'.self::SENDER_NAME_MAX_LEN."* characters.\n\n".
            '*BACK* — skip for now (you will be asked before you can send money).'
        );
    }

    private function handlePinSenderName(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): void {
        $raw = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $len = function_exists('mb_strlen') ? mb_strlen($raw, 'UTF-8') : strlen($raw);
        if ($len < self::SENDER_NAME_MIN_LEN || $len > self::SENDER_NAME_MAX_LEN) {
            $this->client->sendText(
                $instance,
                $phone,
                'Send a name between *'.self::SENDER_NAME_MIN_LEN.'* and *'.self::SENDER_NAME_MAX_LEN.'* characters, or *BACK* to cancel.'
            );

            return;
        }

        $wallet->sender_name = $raw;
        $wallet->save();

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $this->client->sendText($instance, $phone, "Send name saved.\n\n{$raw}\n\n");
        $this->sendSubmenu($instance, $phone, $wallet->fresh());
    }

    private function startSenderNameStep(WhatsappSession $session, string $instance, string $phone): void
    {
        $session->update(['chat_context' => ['step' => 'pin_sender_name']]);
        $this->client->sendText(
            $instance,
            $phone,
            "*Your send name*\n\n".
            'Before you can send money, send the name you want shown on your transfers (e.g. your full name). '.
            'Between *'.self::SENDER_NAME_MIN_LEN.'* and *'.self::SENDER_NAME_MAX_LEN."* characters.\n\n".
            '*BACK* — cancel'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferAcct(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $trim = trim($text);
        $pick = $this->normalizeWalletCommand($text);
        if (preg_match('/^[1-3]$/', $pick)) {
            $recent = $this->recentBankTransferTargets($wallet->fresh(), 3);
            $idx = (int) $pick - 1;
            if (! isset($recent[$idx])) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    "There is no recent recipient *{$pick}*.\n\n".$this->buildTransferAccountPrompt($wallet)
                );

                return;
            }
            $row = $recent[$idx];
            $this->applyRecentBankRecipientToContext($session, $instance, $phone, $ctx, $wallet, $row);

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== 10) {
            $this->client->sendText(
                $instance,
                $phone,
                "Send a valid *10-digit* account number, or *1* / *2* / *3* for a recent recipient.\n\n".$this->buildTransferAccountPrompt($wallet)
            );

            return;
        }

        $ctx['step'] = 'transfer_bank';
        $ctx['dest_acct'] = $digits;
        $ctx['bank_quick_page'] = 0;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, $this->bankPayout->transferBankPickerMessage(0));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{acct: string, bank_code: string, bank_name: string, account_name: string}  $row
     */
    private function applyRecentBankRecipientToContext(
        WhatsappSession $session,
        string $instance,
        string $phone,
        array $ctx,
        WhatsappWallet $wallet,
        array $row,
    ): void {
        $acct = $row['acct'];
        $bankCode = $row['bank_code'];
        $bankName = $row['bank_name'];
        $accountName = trim($row['account_name']);

        if ($accountName === '' && $this->bankPayout->isNameEnquiryAvailable()) {
            $ne = $this->bankPayout->nameEnquiry($bankCode, $acct);
            if ($ne && ($ne['account_name'] ?? '') !== '') {
                $accountName = trim((string) $ne['account_name']);
            }
        }

        $ctx['dest_acct'] = $acct;
        $ctx['dest_bank_code'] = $bankCode;
        $ctx['dest_bank'] = $bankName;
        unset($ctx['bank_quick_page']);

        if ($accountName !== '') {
            $ctx['dest_acct_name'] = $accountName;
            $ctx['step'] = 'transfer_amount';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "*{$bankName}* · ****".substr($acct, -4)." · {$accountName}\n\n".
                "Send the *amount* in Naira (numbers only), minimum ₦1.\n\n".
                '*BACK* — cancel'
            );

            return;
        }

        $ctx['step'] = 'transfer_beneficiary';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            "*{$bankName}* · ****".substr($acct, -4)."\n\n".
            'Send the *account holder name* exactly as registered with the bank.'."\n\n".
            '*BACK* — cancel'
        );
    }

    /**
     * Last unique bank-transfer destinations for this wallet (most recent first).
     *
     * @return list<array{acct: string, bank_code: string, bank_name: string, account_name: string}>
     */
    private function recentBankTransferTargets(WhatsappWallet $wallet, int $limit = 3): array
    {
        $rows = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT)
            ->whereNotNull('counterparty_account_number')
            ->where('counterparty_account_number', '!=', '')
            ->whereNotNull('counterparty_bank_code')
            ->where('counterparty_bank_code', '!=', '')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        $out = [];
        $seen = [];
        foreach ($rows as $txn) {
            $acct = (string) $txn->counterparty_account_number;
            $code = (string) $txn->counterparty_bank_code;
            $key = $acct.'|'.$code;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $meta = is_array($txn->meta) ? $txn->meta : [];
            $bankName = trim((string) ($meta['bank_name'] ?? ''));
            if ($bankName === '') {
                $bankName = (string) (Bank::query()->where('code', $code)->value('name') ?? '');
            }
            if ($bankName === '') {
                $bankName = 'Bank '.$code;
            }

            $out[] = [
                'acct' => $acct,
                'bank_code' => $code,
                'bank_name' => $bankName,
                'account_name' => trim((string) ($txn->counterparty_account_name ?? '')),
            ];

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function buildTransferAccountPrompt(WhatsappWallet $wallet): string
    {
        $lines = [
            '*Bank transfer*',
            '',
            'Send the *10-digit* account number.',
        ];

        $recent = $this->recentBankTransferTargets($wallet, 3);
        if ($recent !== []) {
            $lines[] = '';
            $lines[] = 'Last transfers — *1*, *2*, or *3* reuses that account (skips bank search):';
            foreach ($recent as $i => $r) {
                $n = $i + 1;
                $tail = strlen($r['acct']) >= 4 ? '****'.substr($r['acct'], -4) : '****';
                $who = $r['account_name'] !== '' ? $r['account_name'] : 'Saved account';
                $lines[] = "*{$n}* — {$r['bank_name']} · {$who} · {$tail}";
            }
        }

        $lines[] = '';
        $lines[] = '*BACK* — cancel';

        return implode("\n", $lines);
    }

    private function sendTransferAccountStep(string $instance, string $phone, WhatsappWallet $wallet): void
    {
        $this->client->sendText($instance, $phone, $this->buildTransferAccountPrompt($wallet));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferBank(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $trim = trim($text);
        $page = isset($ctx['bank_quick_page']) ? (int) $ctx['bank_quick_page'] : 0;
        $cmd = strtoupper($trim);

        if (in_array($cmd, ['MORE', 'NEXT'], true)) {
            $last = $this->bankPayout->quickBankLastPageIndex();
            $ctx['bank_quick_page'] = min($last, $page + 1);
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText($instance, $phone, $this->bankPayout->transferBankPickerMessage($ctx['bank_quick_page']));

            return;
        }

        if (in_array($cmd, ['PREV', 'PREVIOUS'], true)) {
            $ctx['bank_quick_page'] = max(0, $page - 1);
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText($instance, $phone, $this->bankPayout->transferBankPickerMessage($ctx['bank_quick_page']));

            return;
        }

        $resolved = null;
        if (preg_match('/^\d+$/', $trim)) {
            if (strlen($trim) >= 3) {
                $resolved = $this->bankPayout->resolveBankFromUserInput($trim);
            } else {
                $g = (int) $trim;
                $total = count($this->bankPayout->quickBanks());
                if ($g >= 1 && $g <= $total) {
                    $resolved = $this->bankPayout->resolveQuickBankGlobalNumber($g);
                } else {
                    $resolved = $this->bankPayout->resolveBankFromUserInput($trim);
                }
            }
        } else {
            $resolved = $this->bankPayout->resolveBankFromUserInput($trim);
        }

        if (! $resolved) {
            $this->client->sendText(
                $instance,
                $phone,
                "Could not match that bank.\n\n".
                'Pick a number from the list, try a clearer name (e.g. *Access Bank*, *First Bank*), *MORE* to see other banks, or send the *bank code* (3+ digits).'
            );

            return;
        }

        $acct = isset($ctx['dest_acct']) && is_string($ctx['dest_acct']) ? $ctx['dest_acct'] : '';
        if (strlen($acct) !== 10) {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $ctx['dest_bank_code'] = $resolved['code'];
        $ctx['dest_bank'] = $resolved['name'];

        $accountName = null;
        if ($this->bankPayout->isNameEnquiryAvailable()) {
            $ne = $this->bankPayout->nameEnquiry($resolved['code'], $acct);
            if ($ne && ! $this->bankPayout->isWeakVerifiedName($ne['account_name'])) {
                $accountName = $ne['account_name'];
            }
        }

        if ($accountName !== null) {
            $ctx['dest_acct_name'] = $accountName;
            $ctx['step'] = 'transfer_amount';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Verified:* {$accountName}\n\n".
                "Send the *amount* in Naira (numbers only), minimum ₦1.\n\n".
                '*BACK* — cancel'
            );

            return;
        }

        $ctx['step'] = 'transfer_beneficiary';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            "We could not auto-verify that account.\n\n".
            'Send the *account holder name* exactly as registered with *'.$resolved['name']."*.\n\n".
            '*BACK* — cancel'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferBeneficiary(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $name = trim($text);
        if (strlen($name) < 3) {
            $this->client->sendText($instance, $phone, 'Send the full account name (at least 3 characters).');

            return;
        }

        if (empty($ctx['dest_bank_code']) || empty($ctx['dest_bank'])) {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $ctx['dest_acct_name'] = $name;
        $ctx['step'] = 'transfer_amount';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            "Send the *amount* in Naira (numbers only), minimum ₦1.\n\n*BACK* — cancel"
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferAmount(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $t = preg_replace('/[^\d.]/', '', $text) ?? '';
        if ($t === '' || ! is_numeric($t)) {
            $this->client->sendText($instance, $phone, 'Send a valid amount, e.g. *5000*');

            return;
        }
        $amount = round((float) $t, 2);
        if ($amount < 1) {
            $this->client->sendText($instance, $phone, 'Minimum transfer is ₦1.');

            return;
        }

        $wallet->refresh();
        $check = $wallet->canDebit($amount);
        if (! $check['ok']) {
            $this->client->sendText($instance, $phone, $check['message'] ?? 'Cannot send that amount.');

            return;
        }

        $ctx['amount'] = $amount;
        $this->secureTransferAuth->beginBankTransferConfirmation($session, $instance, $phone, $wallet, $ctx);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferPin(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): void {
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked.');

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::PIN_LEN) {
            $this->client->sendText($instance, $phone, 'Send your *4-digit* PIN.');

            return;
        }

        if (! $wallet->pin_hash || ! Hash::check($digits, (string) $wallet->pin_hash)) {
            $wallet->increment('pin_failed_attempts');
            $wallet->refresh();
            if ((int) $wallet->pin_failed_attempts >= self::MAX_PIN_FAILS) {
                $wallet->pin_locked_until = now()->addMinutes(self::PIN_LOCK_MINUTES);
                $wallet->save();
                $this->secureTransferAuth->forgetConfirmTokenIfPresent(
                    isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
                        ? $ctx['wallet_transfer_confirm_token']
                        : null
                );
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Too many wrong PIN attempts. Wallet PIN locked for '.self::PIN_LOCK_MINUTES.' minutes.'
                );
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            $this->client->sendText($instance, $phone, 'Wrong PIN. Try again or *BACK* to cancel.');

            return;
        }

        $token = isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
            ? $ctx['wallet_transfer_confirm_token']
            : null;
        $this->secureTransferAuth->forgetConfirmTokenIfPresent($token);

        $this->transferCompletion->completeBankTransfer(
            $session,
            $instance,
            $phone,
            $wallet->fresh(),
            $ctx,
            true
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferOtp(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
    ): void {
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked.');

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::TRANSFER_OTP_LEN) {
            $this->client->sendText($instance, $phone, 'Send the *6-digit code* from your email (not your wallet PIN).');

            return;
        }

        $this->secureTransferAuth->verifyBankTransferOtp(
            $session,
            $instance,
            $phone,
            $wallet,
            $ctx,
            $digits
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleP2pPhone(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $digits = PhoneNormalizer::digitsOnly($text);
        $recipient = PhoneNormalizer::canonicalNgE164Digits($digits);
        if ($recipient === null || strlen($recipient) < 12) {
            $this->client->sendText($instance, $phone, 'Send a valid Nigerian mobile number (e.g. *08012345678*).');

            return;
        }

        if ($recipient === PhoneNormalizer::canonicalNgE164Digits($phone)) {
            $this->client->sendText($instance, $phone, 'You cannot send to your own number. Pick someone else.');

            return;
        }

        $recvWallet = WhatsappWallet::query()
            ->where('phone_e164', $recipient)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        $ctx['p2p_recipient_e164'] = $recipient;
        $ctx['step'] = 'p2p_verify_recipient';

        $mask = $this->maskPhoneForDisplay($recipient);
        $mins = WhatsappWalletPendingP2pService::claimMinutes();

        if (! $recvWallet) {
            $ctx['p2p_recipient_unregistered'] = true;
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Confirm recipient*\n\n".
                "Number: {$mask}\n".
                "They have *not* opened *WALLET* here yet.\n\n".
                "After you send, they'll get a message: *REGISTER* / *WALLET* to claim, or *CANCEL* to refund you. They have *{$mins} minutes*.\n\n".
                "Reply *YES* if this is the right person.\n\n".
                '*BACK* — enter a different number'
            );

            return;
        }

        unset($ctx['p2p_recipient_unregistered']);
        $session->update(['chat_context' => $ctx]);

        $recvName = $recvWallet->normalizedSenderName();
        $nameBlock = $recvName !== null
            ? "Registered send name (their wallet):\n{$recvName}"
            : 'Registered send name: not on file yet — rely on the number and your contact.';

        $this->client->sendText(
            $instance,
            $phone,
            "*Confirm recipient*\n\n".
            "Wallet: {$mask}\n".
            "{$nameBlock}\n\n".
            "Reply *YES* if this is the right person.\n\n".
            '*BACK* — enter a different number'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleP2pVerifyRecipient(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $recipient = isset($ctx['p2p_recipient_e164']) && is_string($ctx['p2p_recipient_e164'])
            ? $ctx['p2p_recipient_e164']
            : '';
        if ($recipient === '') {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $unreg = ! empty($ctx['p2p_recipient_unregistered']);

        if (! $unreg) {
            $recvWallet = WhatsappWallet::query()
                ->where('phone_e164', $recipient)
                ->where('status', WhatsappWallet::STATUS_ACTIVE)
                ->first();

            if (! $recvWallet) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    'That wallet is no longer available. *BACK* to try another number.'
                );

                return;
            }
        }

        if (in_array($cmd, ['YES', 'Y', 'OK', 'CONFIRM'], true) || $cmd === '1') {
            $ctx['step'] = 'p2p_amount';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "Send *amount* in Naira (numbers only), minimum ₦1.\n\n*BACK* — cancel"
            );

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            'Reply *YES* when the wallet and name match who you intend to pay, or *BACK* to change the number.'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleP2pAmount(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet
    ): void {
        $recipient = isset($ctx['p2p_recipient_e164']) && is_string($ctx['p2p_recipient_e164'])
            ? $ctx['p2p_recipient_e164']
            : '';
        if ($recipient === '') {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $t = preg_replace('/[^\d.]/', '', $text) ?? '';
        if ($t === '' || ! is_numeric($t)) {
            $this->client->sendText($instance, $phone, 'Send a valid amount, e.g. *5000*');

            return;
        }
        $amount = round((float) $t, 2);
        if ($amount < 1) {
            $this->client->sendText($instance, $phone, 'Minimum amount is ₦1.');

            return;
        }

        $wallet->refresh();
        $debitCheck = $wallet->canDebit($amount);
        if (! $debitCheck['ok']) {
            $this->client->sendText($instance, $phone, $debitCheck['message'] ?? 'Cannot send that amount.');

            return;
        }

        $recv = WhatsappWallet::query()
            ->where('phone_e164', $recipient)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! empty($ctx['p2p_recipient_unregistered']) && $recv) {
            unset($ctx['p2p_recipient_unregistered']);
        }

        if ($recv) {
            $creditCheck = $recv->canCredit($amount);
            if (! $creditCheck['ok']) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    ($creditCheck['message'] ?? 'Recipient cannot receive this amount.').' Ask them to spend or *UPGRADE*.'
                );

                return;
            }
        }

        $ctx['p2p_amount'] = $amount;
        $this->secureTransferAuth->beginP2pTransferConfirmation($session, $instance, $phone, $wallet, $ctx);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleP2pPin(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): void {
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked.');

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::PIN_LEN) {
            $this->client->sendText($instance, $phone, 'Send your *4-digit* PIN.');

            return;
        }

        if (! $wallet->pin_hash || ! Hash::check($digits, (string) $wallet->pin_hash)) {
            $wallet->increment('pin_failed_attempts');
            $wallet->refresh();
            if ((int) $wallet->pin_failed_attempts >= self::MAX_PIN_FAILS) {
                $wallet->pin_locked_until = now()->addMinutes(self::PIN_LOCK_MINUTES);
                $wallet->save();
                $this->secureTransferAuth->forgetConfirmTokenIfPresent(
                    isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
                        ? $ctx['wallet_transfer_confirm_token']
                        : null
                );
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Too many wrong PIN attempts. Wallet PIN locked for '.self::PIN_LOCK_MINUTES.' minutes.'
                );
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            $this->client->sendText($instance, $phone, 'Wrong PIN. Try again or *BACK* to cancel.');

            return;
        }

        $token = isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
            ? $ctx['wallet_transfer_confirm_token']
            : null;
        $this->secureTransferAuth->forgetConfirmTokenIfPresent($token);

        $this->transferCompletion->completeP2pTransfer(
            $session,
            $instance,
            $phone,
            $wallet->fresh(),
            $ctx,
            true
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleP2pOtp(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
    ): void {
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked.');

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::TRANSFER_OTP_LEN) {
            $this->client->sendText($instance, $phone, 'Send the *6-digit code* from your email (not your wallet PIN).');

            return;
        }

        $this->secureTransferAuth->verifyP2pTransferOtp(
            $session,
            $instance,
            $phone,
            $wallet,
            $ctx,
            $digits
        );
    }

    private function sendP2pPhoneStepPrompt(string $instance, string $phone): void
    {
        $this->client->sendText(
            $instance,
            $phone,
            "*Send to WhatsApp (P2P)*\n\n".
            "Send the recipient's Nigerian mobile number (e.g. *080…* or *234…*).\n".
            "They must have sent *WALLET* here once so their wallet exists.\n\n".
            '*BACK* — cancel'
        );
    }

    private function maskPhoneForDisplay(string $e164): string
    {
        $d = preg_replace('/\D/', '', $e164) ?? '';
        if (strlen($d) < 9) {
            return $e164;
        }

        return substr($d, 0, 5).' •••• '.substr($d, -4);
    }

    private function handleWalletTransactionHistory(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        WhatsappWallet $wallet,
    ): void {
        $ctx = $session->chat_context;
        if (! is_array($ctx)) {
            $ctx = [];
        }
        $page = (int) ($ctx['wallet_tx_page'] ?? 0);
        $perPage = self::TX_HISTORY_PAGE_SIZE;
        $total = WhatsappWalletTransaction::query()->where('whatsapp_wallet_id', $wallet->id)->count();
        $lastPage = $total > 0 ? (int) max(0, (int) ceil($total / $perPage) - 1) : 0;

        if (in_array($cmd, ['MORE', 'NEXT'], true)) {
            $page = min($lastPage, $page + 1);
        } elseif (in_array($cmd, ['PREV', 'PREVIOUS'], true)) {
            $page = max(0, $page - 1);
        } elseif ($cmd === '6' || $cmd === 'HISTORY' || $cmd === 'STATEMENT' || $cmd === 'TRANSACTIONS') {
            $page = 0;
        }

        $session->update([
            'chat_context' => [
                'step' => 'wallet_tx_history',
                'wallet_tx_page' => $page,
            ],
        ]);
        $this->sendWalletTransactionHistoryPage($instance, $phone, $wallet->fresh(), $page);
    }

    private function sendWalletTransactionHistoryPage(
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        int $page,
    ): void {
        $perPage = self::TX_HISTORY_PAGE_SIZE;
        $total = WhatsappWalletTransaction::query()->where('whatsapp_wallet_id', $wallet->id)->count();
        $lastPage = $total > 0 ? (int) max(0, (int) ceil($total / $perPage) - 1) : 0;
        $page = max(0, min($lastPage, $page));

        $rows = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->skip($page * $perPage)
            ->take($perPage)
            ->get();

        $lines = ["*Transaction history* (newest first)\n"];
        $lines[] = 'Page '.($page + 1).' / '.($lastPage + 1).' · '.$total." total\n";

        if ($rows->isEmpty()) {
            $lines[] = "\nNo transactions yet.";
        } else {
            foreach ($rows as $t) {
                $lines[] = $this->formatWalletTxHistoryLine($t);
            }
        }

        $lines[] = '';
        if ($page < $lastPage) {
            $lines[] = '*MORE* — next page';
        }
        if ($page > 0) {
            $lines[] = '*PREV* — previous page';
        }
        $lines[] = '*BACK* or *CANCEL* — wallet menu';

        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    private function formatWalletTxHistoryLine(WhatsappWalletTransaction $t): string
    {
        $amt = number_format((float) $t->amount, 2);
        $balAfter = $t->balance_after !== null ? number_format((float) $t->balance_after, 2) : '—';
        $when = $t->created_at instanceof Carbon
            ? $t->created_at->timezone(config('app.timezone'))->format('M j, g:i A')
            : '';

        return match ($t->type) {
            WhatsappWalletTransaction::TYPE_TOPUP => "• Top-up *+₦{$amt}* · bal ₦{$balAfter} · {$when}",
            WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT => $this->formatBankOutHistoryLine($t, $amt, $when),
            WhatsappWalletTransaction::TYPE_P2P_DEBIT => $this->formatP2pDebitHistoryLine($t, $amt, $when),
            WhatsappWalletTransaction::TYPE_P2P_CREDIT => '• WhatsApp receive ← '.$this->maskPhoneForDisplay((string) $t->counterparty_phone_e164)." · *+₦{$amt}* · {$when}",
            WhatsappWalletTransaction::TYPE_VTU_AIRTIME => $this->formatVtuHistoryLine('Airtime', $t, $amt, $when),
            WhatsappWalletTransaction::TYPE_VTU_DATA => $this->formatVtuHistoryLine('Data', $t, $amt, $when),
            WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY => $this->formatVtuHistoryLine('Electricity', $t, $amt, $when),
            WhatsappWalletTransaction::TYPE_ADJUSTMENT => "• Adjustment *₦{$amt}* · bal ₦{$balAfter} · {$when}",
            default => "• {$t->type} · *₦{$amt}* · {$when}",
        };
    }

    private function formatBankOutHistoryLine(WhatsappWalletTransaction $t, string $amt, string $when): string
    {
        $name = trim((string) $t->counterparty_account_name);
        if ($name === '') {
            $name = 'Beneficiary';
        }
        $acct = (string) $t->counterparty_account_number;
        $tail = strlen($acct) >= 4 ? '****'.substr($acct, -4) : $acct;
        $meta = is_array($t->meta) ? $t->meta : [];
        $bn = trim((string) ($meta['bank_name'] ?? ''));
        if ($bn === '' && $t->counterparty_bank_code) {
            $bn = (string) (Bank::query()->where('code', $t->counterparty_bank_code)->value('name') ?? '');
        }
        $bankBit = $bn !== '' ? $bn.' · ' : '';

        return "• Bank → *{$name}* · {$bankBit}{$tail} · *₦{$amt}* · {$when}";
    }

    private function formatVtuHistoryLine(string $label, WhatsappWalletTransaction $t, string $amt, string $when): string
    {
        $meta = is_array($t->meta) ? $t->meta : [];
        $extra = trim((string) ($meta['network_id'] ?? $meta['service_id'] ?? ''));

        return $extra !== ''
            ? "• {$label} ({$extra}) · *₦{$amt}* · {$when}"
            : "• {$label} · *₦{$amt}* · {$when}";
    }

    private function formatP2pDebitHistoryLine(WhatsappWalletTransaction $t, string $amt, string $when): string
    {
        $line = '• WhatsApp send → '.$this->maskPhoneForDisplay((string) $t->counterparty_phone_e164)." · *₦{$amt}* · {$when}";
        $meta = is_array($t->meta) ? $t->meta : [];
        if (! empty($meta['refunded_unclaimed'])) {
            return $line.' · *refunded (unclaimed)*';
        }
        if (! empty($meta['awaiting_recipient_wallet'])) {
            return $line.' · *awaiting claim*';
        }

        return $line;
    }

    private function forgetPinSetupWebTokenFromSession(WhatsappSession $session): void
    {
        $ctx = $session->chat_context;
        if (is_array($ctx) && isset($ctx['pin_setup_web_token']) && is_string($ctx['pin_setup_web_token'])) {
            $this->pinSetupWeb->forgetToken($ctx['pin_setup_web_token']);
        }
    }

    private function recoverSubmenu(WhatsappSession $session, string $instance, string $phone, WhatsappWallet $wallet): void
    {
        $ctx = $session->chat_context;
        if (is_array($ctx) && isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])) {
            $this->secureTransferAuth->forgetConfirmTokenIfPresent($ctx['wallet_transfer_confirm_token']);
        }
        $this->forgetPinSetupWebTokenFromSession($session);
        $session->update(['chat_context' => ['step' => 'submenu']]);
        $this->sendSubmenu($instance, $phone, $wallet->fresh());
    }

    private function returnToSubmenu(
        WhatsappSession $session,
        string $instance,
        string $phone,
        ?Renter $linkedRenter
    ): void {
        $ctx = $session->chat_context;
        if (is_array($ctx) && isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])) {
            $this->secureTransferAuth->forgetConfirmTokenIfPresent($ctx['wallet_transfer_confirm_token']);
        }
        $this->forgetPinSetupWebTokenFromSession($session);
        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);
        $this->sendSubmenu($instance, $phone, $wallet);
    }

    private function exitToMain(
        WhatsappSession $session,
        string $instance,
        string $phone,
        ?Renter $linkedRenter
    ): void {
        $this->forgetPinSetupWebTokenFromSession($session);
        $session->update(['chat_flow' => null, 'chat_context' => null]);
        if ($linkedRenter !== null && $linkedRenter->is_active) {
            app(WhatsappLinkedMenuHandler::class)->sendRootForRenter($linkedRenter->fresh(), $instance, $phone);
        } else {
            $this->checkoutServicesMenu->sendRootMenu($instance, $phone);
        }
    }
}
