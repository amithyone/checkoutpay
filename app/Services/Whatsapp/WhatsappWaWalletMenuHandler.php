<?php

namespace App\Services\Whatsapp;

use App\Models\Bank;
use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletUpgradeFlowHandler $upgradeFlow,
        private WhatsappCheckoutServicesMenuHandler $checkoutServicesMenu,
        private WhatsappWalletBankPayoutService $bankPayout,
        private WhatsappWalletTier1TopupVaService $tier1TopupVa,
        private WhatsappWalletTopupNotifier $walletNotifier,
    ) {}

    public function openMenu(WhatsappSession $session, string $instance, string $phone, ?Renter $renter): void
    {
        $wallet = $this->findOrCreateWallet($phone, $renter);
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
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->upgradeFlow->start($session->fresh(), $instance, $phone);

            return;
        }

        if (in_array($cmd, ['CANCEL'], true) && (str_starts_with($step, 'transfer_') || str_starts_with($step, 'p2p_'))) {
            $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

            return;
        }

        if ($cmd === 'BACK') {
            if ($step === 'submenu') {
                $this->exitToMain($session, $instance, $phone, $linkedRenter);

                return;
            }
            if (str_starts_with($step, 'transfer_') || str_starts_with($step, 'p2p_')) {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            if (in_array($step, ['pin_new', 'pin_confirm'], true)) {
                $this->returnToSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
        }

        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);

        match ($step) {
            'submenu' => $this->handleSubmenu($session, $instance, $phone, $text, $cmd, $wallet),
            'pin_new' => $this->handlePinNew($session, $instance, $phone, $text, $wallet),
            'pin_confirm' => $this->handlePinConfirm($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_acct' => $this->handleTransferAcct($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_bank' => $this->handleTransferBank($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_beneficiary' => $this->handleTransferBeneficiary($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_amount' => $this->handleTransferAmount($session, $instance, $phone, $text, $ctx, $wallet),
            'transfer_pin' => $this->handleTransferPin($session, $instance, $phone, $text, $ctx, $wallet, $linkedRenter),
            'p2p_phone' => $this->handleP2pPhone($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_amount' => $this->handleP2pAmount($session, $instance, $phone, $text, $ctx, $wallet),
            'p2p_pin' => $this->handleP2pPin($session, $instance, $phone, $text, $ctx, $wallet, $linkedRenter),
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

        return strtoupper($t);
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

        $bankNote = $this->bankPayout->isConfigured()
            ? 'Bank sends use *MavonPay*: we only keep the debit when the transfer is *confirmed successful* — failed or *pending* responses refund your wallet.'
            : 'Bank sends are recorded on your balance; connect MavonPay for live payouts.';

        $this->client->sendText(
            $instance,
            $phone,
            "*WhatsApp wallet*\n".
            "Balance: *{$bal}*\n".
            $vaBlock.
            "\n".
            "*1* — Receive / Top up\n".
            "*2* — Transfer to bank (account → bank → verify name → amount → PIN)\n".
            "*3* — Tier 2 (*UPGRADE*): permanent bank account (KYC)\n".
            "*4* — Send to another *WhatsApp* number (P2P; they must open *WALLET* once)\n\n".
            "{$pinLine}\n\n".
            "Tier 1 cap: ₦{$t1max} balance & same daily send limit until upgraded.\n".
            $tier1VaNote.
            "{$bankNote}\n\n".
            '*BACK* or *MENU* — leave wallet  *STOP* — pause bot'
        );
    }

    private function handleSubmenu(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        WhatsappWallet $wallet
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
            $session->update([
                'chat_context' => ['step' => 'pin_new'],
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Set wallet PIN*\n\nSend a *4-digit* PIN (numbers only).\n\n*BACK* — cancel"
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
            $session->update([
                'chat_context' => ['step' => 'transfer_acct'],
            ]);
            $this->sendTransferAccountStep($instance, $phone, $wallet);

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
            $session->update(['chat_context' => ['step' => 'p2p_phone']]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Send to WhatsApp (P2P)*\n\n".
                "Send the recipient's Nigerian mobile number (e.g. *080…* or *234…*).\n".
                "They must have sent *WALLET* here once so their wallet exists.\n\n".
                '*BACK* — cancel'
            );

            return;
        }

        if ($cmd === '3') {
            $session->update(['chat_flow' => null, 'chat_context' => null]);
            $this->upgradeFlow->start($session->fresh(), $instance, $phone);

            return;
        }

        if ($cmd === 'WALLET' || $cmd === '') {
            $this->sendSubmenu($instance, $phone, $wallet->fresh());

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            'Reply *1*–*4*, or *REGISTER* / *UPGRADE*. *MENU* — main categories.'
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
                "Transfer from your bank app. We credit this wallet when MevonPay confirms (*funding.success* webhook).\n".
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

        $session->update([
            'chat_context' => [
                'step' => 'pin_confirm',
                'pin_temp' => Hash::make($digits),
            ],
        ]);
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
            $session->update(['chat_context' => ['step' => 'pin_new']]);
            $this->client->sendText($instance, $phone, 'PINs did not match. Send a new *4-digit* PIN.');

            return;
        }

        $wallet->pin_hash = $hash;
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $this->client->sendText($instance, $phone, "PIN saved.\n\n");
        $this->sendSubmenu($instance, $phone, $wallet->fresh());
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

        $ctx['step'] = 'transfer_pin';
        $ctx['amount'] = $amount;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            "Send your *4-digit wallet PIN* to confirm this bank transfer.\n\n*BACK* — cancel"
        );
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

        $amount = isset($ctx['amount']) && is_numeric($ctx['amount']) ? (float) $ctx['amount'] : 0.0;
        $acct = isset($ctx['dest_acct']) && is_string($ctx['dest_acct']) ? $ctx['dest_acct'] : '';
        $bankName = isset($ctx['dest_bank']) && is_string($ctx['dest_bank']) ? $ctx['dest_bank'] : '';
        $bankCode = isset($ctx['dest_bank_code']) && is_string($ctx['dest_bank_code']) ? $ctx['dest_bank_code'] : '';
        $beneficiary = isset($ctx['dest_acct_name']) && is_string($ctx['dest_acct_name']) ? trim($ctx['dest_acct_name']) : '';

        if ($amount < 1 || strlen($acct) !== 10 || $bankCode === '' || $beneficiary === '') {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

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
                $beneficiary
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
            $this->client->sendText($instance, $phone, 'Transfer could not be completed. Check balance and limits, then try again.');

            return;
        }

        $session->update(['chat_context' => ['step' => 'submenu']]);
        $wallet = $wallet->fresh();
        $this->client->sendText(
            $instance,
            $phone,
            "*Transfer recorded*\n\n".
            '₦'.number_format($amount, 2)." → {$bankName} / {$acct} ({$beneficiary}).\n".
            "MavonPay is not configured — this is ledger-only until payouts are enabled.\n\n".
            'New balance: *₦'.number_format((float) $wallet->balance, 2).'*'
        );
        $this->sendSubmenu($instance, $phone, $wallet);
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
        string $beneficiary
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
            $this->client->sendText($instance, $phone, 'Transfer could not be completed. Check balance and limits, then try again.');

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
                'Balance: *₦'.number_format((float) $wallet->balance, 2).'*'
            );
        } else {
            $detail = $bucket === MavonPayTransferService::BUCKET_PENDING
                ? 'MavonPay returned *pending* (not a final success). WhatsApp transfers only complete when the bank confirms — your wallet has been *refunded*.'
                : ($result['response_message'] ?? 'The bank could not accept this transfer.');
            $this->client->sendText(
                $instance,
                $phone,
                "*Bank transfer not completed*\n\n".
                $detail."\n\n".
                "Your wallet was *refunded*.\n\n".
                'Balance: *₦'.number_format((float) $wallet->balance, 2).'*'
            );
        }

        $this->sendSubmenu($instance, $phone, $wallet);
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

        if (! $recvWallet) {
            $this->client->sendText(
                $instance,
                $phone,
                'That number does not have a WhatsApp wallet yet. Ask them to send *WALLET* here first, then try again.'
            );

            return;
        }

        $ctx['p2p_recipient_e164'] = $recipient;
        $ctx['step'] = 'p2p_amount';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            "Send *amount* in Naira (numbers only), minimum ₦1.\n\n*BACK* — cancel"
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

        $recv = WhatsappWallet::query()->where('phone_e164', $recipient)->first();
        if (! $recv || ! $recv->isActive()) {
            $this->client->sendText($instance, $phone, 'Recipient wallet is not available. Try again later.');

            return;
        }

        $creditCheck = $recv->canCredit($amount);
        if (! $creditCheck['ok']) {
            $this->client->sendText(
                $instance,
                $phone,
                ($creditCheck['message'] ?? 'Recipient cannot receive this amount.').' Ask them to spend or *UPGRADE*.'
            );

            return;
        }

        $ctx['p2p_amount'] = $amount;
        $ctx['step'] = 'p2p_pin';
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            'Send your *4-digit wallet PIN* to confirm this WhatsApp send.\n\n*BACK* — cancel'
        );
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

        $recipient = isset($ctx['p2p_recipient_e164']) && is_string($ctx['p2p_recipient_e164'])
            ? $ctx['p2p_recipient_e164']
            : '';
        $amount = isset($ctx['p2p_amount']) && is_numeric($ctx['p2p_amount']) ? (float) $ctx['p2p_amount'] : 0.0;

        if ($recipient === '' || $amount < 1) {
            $this->recoverSubmenu($session, $instance, $phone, $wallet);

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
                    'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                    'amount' => $amount,
                    'balance_after' => $newSenderBal,
                    'counterparty_phone_e164' => $recipient,
                    'meta' => ['channel' => 'whatsapp_menu'],
                ]);

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $recv->id,
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
                'Send failed (limits or availability). Check balance and try again.'
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
            'Your new balance: *₦'.number_format((float) $wallet->balance, 2).'*'
        );
        $this->sendSubmenu($instance, $phone, $wallet);
    }

    private function recoverSubmenu(WhatsappSession $session, string $instance, string $phone, WhatsappWallet $wallet): void
    {
        $session->update(['chat_context' => ['step' => 'submenu']]);
        $this->sendSubmenu($instance, $phone, $wallet->fresh());
    }

    private function returnToSubmenu(
        WhatsappSession $session,
        string $instance,
        string $phone,
        ?Renter $linkedRenter
    ): void {
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
        $session->update(['chat_flow' => null, 'chat_context' => null]);
        if ($linkedRenter !== null && $linkedRenter->is_active) {
            app(WhatsappLinkedMenuHandler::class)->sendRootForRenter($linkedRenter->fresh(), $instance, $phone);
        } else {
            $this->checkoutServicesMenu->sendRootMenu($instance, $phone);
        }
    }
}
