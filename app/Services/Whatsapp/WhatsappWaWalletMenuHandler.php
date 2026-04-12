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
     * Start P2P when the user sends only an NG mobile (080…, 80…, +234…) from the main menu or similar.
     * Same readiness rules as *4* (PIN, send name, not locked).
     */
    public function enterP2pFlowFromPhoneShortcut(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        ?Renter $linkedRenter
    ): void {
        if (PhoneNormalizer::parseBareNigerianMobileForP2pShortcut($text) === null) {
            return;
        }

        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);
        $this->pendingP2p->tryClaimForWallet($wallet->fresh(), $instance);

        if (! $wallet->hasPin()) {
            $this->client->sendText(
                $instance,
                $phone,
                'Set a wallet PIN first: send *WALLET* then *REGISTER*.'
            );

            return;
        }
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked. Try again later or contact support.');

            return;
        }
        if ($wallet->normalizedSenderName() === null) {
            $session->update([
                'chat_flow' => self::FLOW,
                'chat_context' => ['step' => 'submenu'],
            ]);
            $this->startSenderNameStep($session->fresh(), $instance, $phone);

            return;
        }

        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'p2p_phone'],
        ]);
        $this->handleP2pPhone($session->fresh(), $instance, $phone, $text, [], $wallet);
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

        if (in_array($cmd, ['MENU', 'MAIN', 'START', 'HOME', 'RESTART'], true)) {
            $this->exitToMain($session, $instance, $phone, $linkedRenter);

            return;
        }

        if (in_array($cmd, ['UPGRADE', 'TIER2', 'TIER 2'], true)) {
            $this->forgetPinSetupWebTokenFromSession($session);
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->upgradeFlow->start($session->fresh(), $instance, $phone);

            return;
        }

        if (in_array($cmd, ['CANCEL'], true) && (str_starts_with($step, 'transfer_') || str_starts_with($step, 'p2p_') || $step === 'wallet_tx_history' || $step === 'casual_bank_pick' || $step === 'pin_setup_web' || $step === 'wallet_settings')) {
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
            if (str_starts_with($step, 'transfer_') || str_starts_with($step, 'p2p_') || $step === 'casual_bank_pick') {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            if ($step === 'wallet_settings') {
                $session->update(['chat_context' => ['step' => 'submenu']]);
                $this->sendSubmenu($instance, $phone, $this->findOrCreateWallet($phone, $linkedRenter)->fresh());

                return;
            }
            if (in_array($step, ['pin_setup_web', 'pin_sender_name'], true)) {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            if (in_array($step, ['pin_new', 'pin_confirm'], true)) {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
        }

        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);
        if (! in_array($step, ['pin_setup_web', 'pin_new', 'pin_confirm'], true)) {
            $this->pendingP2p->tryClaimForWallet($wallet->fresh(), $instance);
        }

        if ($step === 'submenu') {
            if (PhoneNormalizer::parseBareNigerianMobileForP2pShortcut($text) !== null) {
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
                $this->handleP2pPhone($session->fresh(), $instance, $phone, $text, [], $wallet);

                return;
            }
        }

        match ($step) {
            'submenu' => $this->handleSubmenu($session, $instance, $phone, $text, $cmd, $wallet, $linkedRenter),
            'wallet_settings' => $this->handleWalletSettings($session, $instance, $phone, $cmd, $wallet, $linkedRenter),
            'casual_bank_pick' => $this->handleCasualBankPick($session, $instance, $phone, $text, $ctx, $wallet),
            'pin_setup_web', 'pin_new', 'pin_confirm' => $this->handlePinSetupWebWait($session, $instance, $phone, $text, $ctx, $wallet),
            'pin_sender_name' => $this->handlePinSenderName($session, $instance, $phone, $text, $wallet, $linkedRenter),
            'transfer_acct' => $this->handleTransferAcct($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_bank' => $this->handleTransferBank($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_beneficiary' => $this->handleTransferBeneficiary($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_amount' => $this->handleTransferAmount($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_otp' => $this->handleTransferOtp($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_pin' => $this->handleTransferPinWebOnly($instance, $phone, $text, $ctx, $wallet),
            'p2p_phone' => $this->handleP2pPhone($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_verify_recipient' => $this->handleP2pVerifyRecipient($session, $instance, $phone, $text, $cmd, $ctx, $wallet),
            'p2p_amount' => $this->handleP2pAmount($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_otp' => $this->handleP2pOtp($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_pin' => $this->handleTransferPinWebOnly($instance, $phone, $text, $ctx, $wallet),
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
        $wallet = $wallet->fresh();
        if ($wallet->needsQuickWalletSetup()) {
            $this->client->sendText(
                $instance,
                $phone,
                WhatsappWalletOnboardingCopy::compactWalletSubmenuBody($wallet)
            );

            return;
        }

        $bal = '₦'.number_format((float) $wallet->balance, 2);
        $t1max = number_format((float) config('whatsapp.wallet.tier1_max_balance', 50000), 0);
        $isTier2 = $wallet->isTier2();
        $pinSection = $wallet->hasPin()
            ? ''
            : "*REGISTER* — set wallet PIN (secure link; do not send PIN in chat).\n\n";

        $vaBlock = '';
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $vaBlock = "\n*Your bank account*\n".
                'Bank: *'.($wallet->mevon_bank_name ?? 'Rubies MFB')."*\n".
                'Account: *'.$wallet->mevon_virtual_account_number."*\n";
        }

        $tier1VaNote = '';
        if (! $isTier2 && (int) $wallet->tier === WhatsappWallet::TIER_WHATSAPP_ONLY && $this->tier1TopupVa->isAvailable()) {
            $tier1VaNote = "\nTier 1: *1* gives a *new temporary* pay-in account each time.\n";
        }

        $upgradeLine = $isTier2
            ? ''
            : "*3* — Get a permanent bank account (*UPGRADE* / Tier 2)\n";

        $tier1HeadsUp = $isTier2
            ? ''
            : "Heads-up — Tier 1 max balance is ₦{$t1max} until you upgrade.\n";

        $brand = $this->waBrand();
        $bankNote = $this->bankPayout->isConfigured()
            ? "Bank sends use *{$brand}*: we only keep the debit when the transfer is *confirmed successful* — failed or *pending* responses refund your wallet."
            : "Bank sends are recorded on your balance; connect *{$brand}* for live payouts.";

        $vtuLine = $this->vtuFlow->isAvailable()
            ? "*5* — Airtime / Data / Electricity\n"
            : '';

        $settingsLine = $isTier2
            ? "*7* — *SETTINGS* — email code for transfers *ON* / *OFF*\n"
            : '';

        $this->client->sendText(
            $instance,
            $phone,
            "Here's your wallet 👋\n".
            "Balance: *{$bal}*\n".
            $vaBlock.
            "\nWhat would you like to do?\n".
            "*1* — Add money / receive\n".
            "*2* — Send to someone's *bank* account\n".
            $upgradeLine.
            "*4* — Send money to another *WhatsApp* user\n".
            "Tip: you can paste *080…* / *234…* anytime to start a WhatsApp send.\n".
            $vtuLine.
            "*6* — See recent activity (*MORE* / *PREV* to flip pages)\n".
            $settingsLine.
            "\n".
            $pinSection.
            $tier1HeadsUp.
            $tier1VaNote.
            "{$bankNote}\n\n".
            "If you've sent to someone before, you can type e.g. *send 5k to Tunde Opay* in plain English.\n\n".
            WhatsappMenuInputNormalizer::navigationHelpFooter().' · *STOP* — pause replies'
        );
    }

    private function sendWalletSettingsScreen(string $instance, string $phone, WhatsappWallet $wallet): void
    {
        $wallet->refresh();
        $on = (bool) $wallet->transfer_email_otp_enabled;
        $hasEmail = $wallet->resolveOtpEmail() !== null;
        $statusLine = $on
            ? '*ON* — We email a *6-digit code*; you can still confirm with the secure PIN link.'
            : '*OFF* — *Default.* Confirm with the secure PIN link only (recommended).';
        $emailWarn = $hasEmail
            ? ''
            : "\n\n_No email on file — complete Tier 2 / link an account with email before you can turn this *ON*._";

        $this->client->sendText(
            $instance,
            $phone,
            "*Wallet settings* (Tier 2)\n\n".
            "*Email code* after starting a bank or WhatsApp send:\n{$statusLine}{$emailWarn}\n\n".
            "Reply *OFF* or *ON*. Shortcuts: *1* = OFF, *2* = ON.\n".
            "Wallet PIN is always entered on the *secure page* — never in this chat.\n\n".
            '*BACK* — wallet menu'
        );
    }

    /**
     * Tier 2: toggle transfer email OTP (default off = link only).
     */
    private function handleWalletSettings(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter,
    ): void {
        if ($cmd === 'WALLET' || $cmd === '') {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendSubmenu($instance, $phone, $this->findOrCreateWallet($phone, $linkedRenter)->fresh());

            return;
        }

        if (! $wallet->isTier2()) {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        if (in_array($cmd, ['OFF', '1'], true)) {
            $wallet->transfer_email_otp_enabled = false;
            $wallet->save();
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->client->sendText(
                $instance,
                $phone,
                'Saved: email transfer codes are *OFF*. Confirm sends with the *secure link* only.'
            );
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        if (in_array($cmd, ['ON', '2'], true)) {
            if ($wallet->resolveOtpEmail() === null) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    'We need an *email* on your profile to send codes. Complete *Tier 2* / link an account with email, then try *ON* again.'
                );

                return;
            }
            $wallet->transfer_email_otp_enabled = true;
            $wallet->save();
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->client->sendText(
                $instance,
                $phone,
                'Saved: email transfer codes are *ON*. We will email a *6-digit code*; you can still use the PIN link.'
            );
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            "Reply *ON* or *OFF* (or *2* / *1*). *BACK* — wallet menu.\n\n".WhatsappMenuInputNormalizer::navigationHelpFooter()
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
        if ($cmd === '7' || $cmd === 'SETTINGS') {
            if (! $wallet->isTier2()) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Wallet *SETTINGS* (email codes for transfers) are for *Tier 2*. Tier 1 confirms sends with a *secure link* only. Upgrade with *3* *UPGRADE*.'
                );

                return;
            }
            $session->update(['chat_context' => ['step' => 'wallet_settings']]);
            $this->sendWalletSettingsScreen($instance, $phone, $wallet->fresh());

            return;
        }

        if (in_array($cmd, ['REGISTER', 'PIN'], true)) {
            if ($wallet->hasPin()) {
                if ($wallet->normalizedSenderName() === null) {
                    $this->startSenderNameStep($session, $instance, $phone);

                    return;
                }
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
                    'step' => 'pin_setup_web',
                    'pin_setup_web_token' => $token,
                ],
            ]);
            $url = $this->pinSetupWeb->setupUrl($token);
            $this->client->sendText(
                $instance,
                $phone,
                "*Set wallet PIN*\n\n".
                "Open this link to choose your *4-digit PIN* on a secure page:\n{$url}\n\n".
                "*Do not* send your PIN in this chat.\n\n".
                'Lost the link? Reply *LINK* anytime. *BACK* — cancel'
            );

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
                $topupErr = $wallet->isTier2()
                    ? "We couldn't load receive details just now. Your dedicated account is unchanged — try again shortly or *MENU*."
                    : "We couldn't load top-up details just now. Try again in a moment.\n\n".
                        'Tier 2 users: your bank details are unchanged. Tier 1: try *UPGRADE* or *MENU*.';
                $this->client->sendText($instance, $phone, $topupErr);
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
            if ($wallet->isTier2()) {
                $acct = trim((string) $wallet->mevon_virtual_account_number);
                if ($acct !== '') {
                    $this->client->sendText(
                        $instance,
                        $phone,
                        "You're already on *Tier 2* with a fixed account.\n\n".
                        '*Bank:* '.($wallet->mevon_bank_name ?? 'Rubies MFB')."\n".
                        '*Account:* *'.$acct."*\n\n".
                        'Send *1* anytime for receive / top-up details.'
                    );
                } else {
                    $this->client->sendText(
                        $instance,
                        $phone,
                        'Your *Tier 2* account is still being set up. Try *WALLET* again soon or contact support.'
                    );
                }

                return;
            }
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

        if ($wallet->hasPin() && ! $wallet->isPinLocked() && $wallet->normalizedSenderName() !== null) {
            $norm = WhatsappWalletCasualSendParser::normalizeForCasualParse($text);
            if (WhatsappWalletCasualSendParser::looksLikeCasualSend($norm)) {
                $recent = $this->recentBankTransferTargets($wallet, 10);
                $casual = WhatsappWalletCasualSendParser::tryParse($norm, $wallet, $this->bankPayout, $recent);
                if ($casual !== null) {
                    if (($casual['flow'] ?? '') === 'bank_disambiguate') {
                        $this->beginCasualBankDisambiguation($session, $instance, $phone, $casual);

                        return;
                    }
                    $this->handleCasualSendFromSubmenu($session, $instance, $phone, $wallet, $casual);

                    return;
                }
                if (WhatsappWalletCasualSendParser::largestNairaAmount($norm) !== null) {
                    $this->client->sendText(
                        $instance,
                        $phone,
                        "I see you're trying to *send money* in plain English, but I couldn't match a saved bank payee.\n\n".
                        "• Use *2* once to send to their account (we save it for next time).\n".
                        "• Then try e.g. *send 5k to Tunde Opay* or *pay 2000 for mama GTB*.\n".
                        "• WhatsApp sends: *send 5k to 080…* with their number.\n".
                        "• If you only have *one* saved payee, *send 5k* alone can work.\n\n".
                        WhatsappMenuInputNormalizer::navigationHelpFooter()
                    );

                    return;
                }
            }
        }

        if ($cmd === 'WALLET' || $cmd === '') {
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        if ($wallet->needsQuickWalletSetup()) {
            $hint = $wallet->hasPin()
                ? 'Send *your name*, or *1* to add money. *MENU* — all services.'
                : 'Reply *REGISTER* (PIN link), send *your name*, or *1* to add money. *MENU* — all services.';
            $this->client->sendText($instance, $phone, $hint);

            return;
        }

        $range = $wallet->isTier2()
            ? ($this->vtuFlow->isAvailable() ? '*1*, *2*, *4*, *5*, *6*, *7*' : '*1*, *2*, *4*, *6*, *7*')
            : ($this->vtuFlow->isAvailable() ? '*1*–*6*' : '*1*–*4*, *6*');
        $this->client->sendText(
            $instance,
            $phone,
            "Hey — tap a number ({$range}), or say something like *send 5k to Tunde Opay* if you've paid them before.\n\n*00* or *MENU* = all services · *WALLET* = this screen again."
        );
    }

    /**
     * @param  array{flow: 'bank_disambiguate', amount: float, candidates: list<array{acct: string, bank_code: string, bank_name: string, account_name: string}>}  $casual
     */
    private function beginCasualBankDisambiguation(
        WhatsappSession $session,
        string $instance,
        string $phone,
        array $casual,
    ): void {
        $candidates = $casual['candidates'] ?? [];
        if ($candidates === []) {
            return;
        }
        $amt = number_format((float) $casual['amount'], 2);
        $session->update([
            'chat_context' => [
                'step' => 'casual_bank_pick',
                'casual_pick_amount' => (float) $casual['amount'],
                'casual_pick_candidates' => $candidates,
            ],
        ]);
        $lines = [
            "I found *more than one* saved payee that could match — *₦{$amt}*.",
            '',
            'Reply with a number:',
            '',
        ];
        foreach ($candidates as $i => $r) {
            $n = $i + 1;
            $who = $r['account_name'] !== '' ? $r['account_name'] : 'Saved account';
            $tail = strlen($r['acct']) >= 4 ? '****'.substr($r['acct'], -4) : '****';
            $lines[] = "*{$n}* — {$who} · {$r['bank_name']} · {$tail}";
        }
        $lines[] = '';
        $lines[] = '*BACK* or *CANCEL* — wallet menu · '.WhatsappMenuInputNormalizer::navigationHelpFooter();
        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleCasualBankPick(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
    ): void {
        $raw = trim($text);
        $raw = preg_replace('/^[\*_~\s]+|[\*_~\s]+$/u', '', $raw) ?? $raw;
        $raw = trim((string) $raw);
        if ($raw === '' || ! preg_match('/^(\d+)$/D', $raw, $m)) {
            $this->client->sendText(
                $instance,
                $phone,
                "Reply with *1*, *2*, … to pick who gets the money — or *BACK* to cancel.\n\n".
                    WhatsappMenuInputNormalizer::navigationHelpFooter()
            );

            return;
        }
        $idx = (int) $m[1] - 1;
        $candidates = $ctx['casual_pick_candidates'] ?? [];
        if (! is_array($candidates) || ! isset($candidates[$idx]) || ! is_array($candidates[$idx])) {
            $max = is_array($candidates) ? count($candidates) : 0;
            $range = $max > 0 ? "*1*–*{$max}*" : '*1*';
            $this->client->sendText(
                $instance,
                $phone,
                "That number is not on the list. Pick {$range} or *BACK*.\n\n".
                    WhatsappMenuInputNormalizer::navigationHelpFooter()
            );

            return;
        }
        $hit = $candidates[$idx];
        $amount = (float) ($ctx['casual_pick_amount'] ?? 0);
        if ($amount < 1) {
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }
        $casual = [
            'flow' => 'bank',
            'amount' => $amount,
            'ctx' => [
                'dest_acct' => $hit['acct'],
                'dest_bank_code' => $hit['bank_code'],
                'dest_bank' => $hit['bank_name'],
                'dest_acct_name' => $hit['account_name'],
                'amount' => $amount,
            ],
        ];
        $this->handleCasualSendFromSubmenu($session, $instance, $phone, $wallet, $casual);
    }

    /**
     * Natural-language shortcut from submenu: bank repeat-pay or P2P with amount + phone in one line.
     *
     * @param  array{flow: 'bank', amount: float, ctx: array<string, mixed>}|array{flow: 'p2p', amount: float, recipient_e164: string}  $casual
     */
    private function handleCasualSendFromSubmenu(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $casual,
    ): void {
        if ($casual['flow'] === 'bank') {
            $wallet->refresh();
            $check = $wallet->canDebit($casual['amount']);
            if (! $check['ok']) {
                $this->client->sendText($instance, $phone, ($check['message'] ?? 'Cannot send that amount.')."\n\n".WhatsappMenuInputNormalizer::navigationHelpFooter());

                return;
            }
            $ctx = array_merge($casual['ctx'], ['step' => 'transfer_amount']);
            $session->update(['chat_context' => $ctx]);
            $this->secureTransferAuth->beginBankTransferConfirmation(
                $session->fresh(),
                $instance,
                $phone,
                $wallet->fresh(),
                $ctx
            );

            return;
        }

        $recipient = $casual['recipient_e164'];
        if ($recipient === PhoneNormalizer::canonicalNgE164Digits($phone)) {
            $this->client->sendText($instance, $phone, "You can't send to your own number — pick someone else.\n\n".WhatsappMenuInputNormalizer::navigationHelpFooter());

            return;
        }

        $wallet->refresh();
        $debitCheck = $wallet->canDebit($casual['amount']);
        if (! $debitCheck['ok']) {
            $this->client->sendText($instance, $phone, ($debitCheck['message'] ?? 'Cannot send that amount.')."\n\n".WhatsappMenuInputNormalizer::navigationHelpFooter());

            return;
        }

        $recvWallet = WhatsappWallet::query()
            ->where('phone_e164', $recipient)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if ($recvWallet) {
            $creditCheck = $recvWallet->canCredit($casual['amount']);
            if (! $creditCheck['ok']) {
                $this->client->sendText(
                    $instance,
                    $phone,
                    ($creditCheck['message'] ?? 'Their wallet cannot receive this amount.')."\n\n".WhatsappMenuInputNormalizer::navigationHelpFooter()
                );

                return;
            }
        }

        $ctx = [
            'step' => 'p2p_verify_recipient',
            'p2p_recipient_e164' => $recipient,
            'p2p_amount' => round((float) $casual['amount'], 2),
        ];
        if (! $recvWallet) {
            $ctx['p2p_recipient_unregistered'] = true;
        }
        $session->update(['chat_context' => $ctx]);

        $mask = $this->maskPhoneForDisplay($recipient);
        $amt = number_format((float) $casual['amount'], 2);

        if (! $recvWallet) {
            $this->client->sendText(
                $instance,
                $phone,
                "Got it — *₦{$amt}* to *{$mask}* (they haven't opened a wallet here yet).\n\n".
                "If you continue, they open *WALLET* on that number to receive it (no time limit). They can send *CANCEL* to return it to you.\n\n".
                "Reply *YES* to go ahead — then we'll do your usual security check (PIN or email).\n\n".
                WhatsappMenuInputNormalizer::navigationHelpFooter()
            );

            return;
        }

        $recvName = $recvWallet->normalizedSenderName();
        $nameLine = $recvName !== null
            ? "Their saved send name: *{$recvName}*"
            : 'They have a wallet on this number.';

        $this->client->sendText(
            $instance,
            $phone,
            "Got it — *₦{$amt}* to *{$mask}*.\n{$nameLine}\n\n".
            "Reply *YES* if that's the right person — then we'll ask for your PIN or email code like always.\n\n".
            WhatsappMenuInputNormalizer::navigationHelpFooter()
        );
    }

    private function sendReceiveTopupHelp(string $instance, string $phone, WhatsappWallet $wallet): void
    {
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA && $wallet->mevon_virtual_account_number) {
            $this->client->sendText(
                $instance,
                $phone,
                "*Receive / Top up*\n\n".
                'Transfer to your dedicated account:'."\n".
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

    /**
     * Awaiting wallet PIN setup via secure web link only (no PIN in chat).
     *
     * @param  array<string, mixed>  $ctx
     */
    private function handlePinSetupWebWait(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
    ): void {
        $wallet->refresh();
        if ($wallet->hasPin()) {
            $this->forgetPinSetupWebTokenFromSession($session);
            $session->update(['chat_context' => ['step' => 'submenu']]);
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        $token = isset($ctx['pin_setup_web_token']) && is_string($ctx['pin_setup_web_token'])
            ? $ctx['pin_setup_web_token']
            : '';
        if ($token === '') {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $session->update([
            'chat_context' => [
                'step' => 'pin_setup_web',
                'pin_setup_web_token' => $token,
            ],
        ]);

        $cmd = $this->normalizeWalletCommand($text);
        if (in_array($cmd, ['LINK', 'RESEND', 'URL'], true)) {
            $url = $this->pinSetupWeb->setupUrl($token);
            $this->client->sendText(
                $instance,
                $phone,
                "*Set wallet PIN* — open this link:\n{$url}\n\n*Do not* send your PIN in this chat."
            );

            return;
        }

        if (in_array($cmd, ['REGISTER', 'PIN'], true)) {
            $this->client->sendText(
                $instance,
                $phone,
                'We already sent your PIN setup link. Scroll up and tap it, or reply *LINK* to see it again.'
            );

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) === self::PIN_LEN) {
            $url = $this->pinSetupWeb->setupUrl($token);
            $this->client->sendText(
                $instance,
                $phone,
                "Do *not* send your wallet PIN in this chat. Open the secure link only:\n{$url}"
            );

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            "Use the *PIN setup link* we sent when you tapped *REGISTER*. Lost it? Reply *LINK*.\n\n*BACK* — cancel"
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
            "*Your name*\n\n".
            'Send the name shown on your transfers (*'.self::SENDER_NAME_MIN_LEN.'*–*'.self::SENDER_NAME_MAX_LEN.'* characters).'."\n\n".
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
     * Transfer confirmation: wallet PIN only on the secure web page (never in chat).
     *
     * @param  array<string, mixed>  $ctx
     */
    private function handleTransferPinWebOnly(
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

        $token = isset($ctx['wallet_transfer_confirm_token']) && is_string($ctx['wallet_transfer_confirm_token'])
            ? $ctx['wallet_transfer_confirm_token']
            : '';
        if ($token === '') {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

            return;
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) === self::PIN_LEN) {
            $this->client->sendText(
                $instance,
                $phone,
                'Do *not* send your wallet PIN in this chat. Open the secure link and enter your PIN there:'
            );
            $this->client->sendText($instance, $phone, $this->secureTransferAuth->transferConfirmUrl($token));

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            "Open the link below and enter your *4-digit wallet PIN* to confirm. *Do not* type your PIN in WhatsApp.\n\n*BACK* — cancel"
        );
        $this->client->sendText($instance, $phone, $this->secureTransferAuth->transferConfirmUrl($token));
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

        if (! $recvWallet) {
            $ctx['p2p_recipient_unregistered'] = true;
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Confirm recipient*\n\n".
                "Number: {$mask}\n".
                "They have *not* opened *WALLET* here yet.\n\n".
                "After you send, they'll get a message to open *WALLET* / *REGISTER*, or *CANCEL* to return the money to you.\n\n".
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
            if (isset($ctx['p2p_amount']) && is_numeric($ctx['p2p_amount'])) {
                $preset = round((float) $ctx['p2p_amount'], 2);
                if ($preset < 1) {
                    $this->client->sendText($instance, $phone, 'That amount looks off. Start again from *4* or type a new send.');

                    return;
                }
                $wallet->refresh();
                $debitCheck = $wallet->canDebit($preset);
                if (! $debitCheck['ok']) {
                    $this->client->sendText($instance, $phone, $debitCheck['message'] ?? 'Cannot send that amount.');

                    return;
                }
                if (! $unreg) {
                    $recvWallet = WhatsappWallet::query()
                        ->where('phone_e164', $recipient)
                        ->where('status', WhatsappWallet::STATUS_ACTIVE)
                        ->first();
                    if (! $recvWallet) {
                        $this->recoverSubmenu($session, $instance, $phone, $wallet);

                        return;
                    }
                    $creditCheck = $recvWallet->canCredit($preset);
                    if (! $creditCheck['ok']) {
                        $this->client->sendText(
                            $instance,
                            $phone,
                            ($creditCheck['message'] ?? 'Recipient cannot receive this amount.').' Ask them to spend or *UPGRADE*.'
                        );

                        return;
                    }
                }
                $this->secureTransferAuth->beginP2pTransferConfirmation($session, $instance, $phone, $wallet, $ctx);

                return;
            }
            $ctx['step'] = 'p2p_amount';
            $session->update(['chat_context' => $ctx]);
            $this->client->sendText(
                $instance,
                $phone,
                "How much in Naira? (numbers only, min ₦1)\n\n*0* back · *00* wallet menu"
            );

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            "If that’s the right person, reply *YES*. Otherwise *0* to change the number.\n\n".WhatsappMenuInputNormalizer::navigationHelpFooter()
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
