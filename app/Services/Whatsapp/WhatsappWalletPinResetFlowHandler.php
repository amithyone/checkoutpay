<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp chat flow to reset an existing wallet PIN (Tier 1 via upgrade + name match, Tier 2 via BVN/CAC).
 */
class WhatsappWalletPinResetFlowHandler
{
    public const FLOW = 'wa_wallet_pin_reset';

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletPinResetService $pinReset,
        private WhatsappWalletPinSetupWebService $pinSetupWeb,
        private WhatsappWalletUpgradeFlowHandler $upgradeFlow,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    public function start(WhatsappSession $session, string $instance, string $phone): void
    {
        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        Log::info('whatsapp.wallet.pin_reset.started', ['wallet_id' => $wallet->id]);

        if (! $wallet->hasPin()) {
            $created = $this->pinSetupWeb->createAndStoreToken($session->fresh(), $instance, $phone, $wallet);
            if (($created['ok'] ?? false) && isset($created['token'])) {
                $url = $this->pinSetupWeb->setupUrl((string) $created['token']);
                $this->client->sendText(
                    $instance,
                    $phone,
                    "You don't have a wallet PIN yet.\n\n".
                    "Set one on this secure page:\n{$url}\n\n".
                    '*Do not* type your PIN in this chat.'
                );
            } else {
                $this->client->sendText($instance, $phone, 'Could not start PIN setup. Try *MENU* or contact support.');
            }

            return;
        }

        if ($this->pinReset->isRateLimited($wallet)) {
            $this->client->sendText(
                $instance,
                $phone,
                'Too many failed PIN reset attempts. Try again in about 15 minutes or contact support.'
            );

            return;
        }

        if (! $wallet->isTier2() || $this->pinReset->bankNameForMatch($wallet) === '') {
            $this->startTier1UpgradePath($session, $instance, $phone, $wallet);

            return;
        }

        $this->startTier2IdentityStep($session, $instance, $phone, $wallet);
    }

    public function handle(WhatsappSession $session, string $instance, string $phone, string $text, string $cmd): void
    {
        if (in_array($cmd, ['CANCEL', 'BACK', 'MENU'], true)) {
            $session->update([
                'chat_flow' => WhatsappWaWalletMenuHandler::FLOW,
                'chat_context' => ['step' => 'submenu'],
            ]);
            app(WhatsappWaWalletMenuHandler::class)->openMenu($session->fresh(), $instance, $phone, null);

            return;
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $phone)->first();
        if (! $wallet) {
            $this->recover($session, $instance, $phone);

            return;
        }

        $ctx = is_array($session->chat_context) ? $session->chat_context : [];
        $step = (string) ($ctx['step'] ?? '');

        if ($step === 'tier1_upgrade') {
            $this->client->sendText(
                $instance,
                $phone,
                'Finish *UPGRADE* (Tier 2) first — reply with the details we asked for, or *BACK* to cancel PIN reset.'
            );

            return;
        }

        if ($step === 'verify_name') {
            $this->handleVerifyName($session, $instance, $phone, $wallet, $text);

            return;
        }

        if ($step === 'verify_bvn') {
            $this->handleVerifyBvn($session, $instance, $phone, $wallet, $text);

            return;
        }

        if ($step === 'verify_cac') {
            $this->handleVerifyCac($session, $instance, $phone, $wallet, $text);

            return;
        }

        $this->recover($session, $instance, $phone);
    }

    public function beginNameVerificationAfterUpgrade(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
    ): void {
        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'verify_name'],
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "*PIN reset — confirm your name*\n\n".
            'Your Tier 2 bank account is ready. We need to confirm your identity before resetting your PIN.'
        );

        $this->runNameCheckOrPrompt($session, $instance, $phone, $wallet->fresh());
    }

    private function startTier1UpgradePath(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
    ): void {
        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            $this->client->sendText(
                $instance,
                $phone,
                'PIN reset with bank verification is only available for *Nigeria* numbers right now.'
            );

            return;
        }

        Log::info('whatsapp.wallet.pin_reset.upgrade_required', ['wallet_id' => $wallet->id]);

        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'tier1_upgrade', 'resume_pin_reset' => true],
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "*Reset wallet PIN*\n\n".
            "To reset your PIN we need a *Tier 2* bank account on file (KYC).\n\n".
            "Next we'll walk you through *UPGRADE*. After your account is created we'll confirm your name, then send a secure link to choose a new PIN.\n\n".
            '*Do not* send your PIN or BVN in chat except when we ask during upgrade.'
        );

        $this->upgradeFlow->start($session->fresh(), $instance, $phone, resumePinReset: true);
    }

    private function startTier2IdentityStep(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
    ): void {
        if ($this->pinReset->shouldPromptCac($wallet)) {
            $session->update([
                'chat_flow' => self::FLOW,
                'chat_context' => ['step' => 'verify_cac'],
            ]);
            $this->client->sendText(
                $instance,
                $phone,
                "*Reset wallet PIN*\n\n".
                "Reply with your business *CAC* number exactly as registered (we have it on file from Tier 2).\n\n".
                '*Do not* send your new PIN here. *BACK* — cancel.'
            );

            return;
        }

        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'verify_bvn'],
        ]);

        $suffix = '';
        $bvn = preg_replace('/\D/', '', (string) $wallet->kyc_bvn) ?? '';
        if (strlen($bvn) === 11) {
            $suffix = "\n\n(We have BVN ending *".substr($bvn, -4).' on file.)';
        }

        $this->client->sendText(
            $instance,
            $phone,
            "*Reset wallet PIN*\n\n".
            "Reply with your *11-digit BVN* to confirm it's you.\n\n".
            '*Do not* send your new PIN in this chat. *BACK* — cancel.'.
            $suffix
        );
    }

    private function handleVerifyName(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        string $text,
    ): void {
        if ($this->pinReset->isRateLimited($wallet)) {
            $this->sendRateLimited($instance, $phone);

            return;
        }

        if ($this->pinReset->verifyProvidedName($wallet, $text)) {
            $this->issueResetLink($session, $instance, $phone, $wallet);

            return;
        }

        $this->pinReset->recordFailure($wallet, 'name_failed');
        $this->client->sendText(
            $instance,
            $phone,
            'That name does not match our bank records for your account. Reply with your *full name as on your bank account*, or *BACK* to cancel.'
        );
    }

    private function handleVerifyBvn(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        string $text,
    ): void {
        if ($this->pinReset->isRateLimited($wallet)) {
            $this->sendRateLimited($instance, $phone);

            return;
        }

        if ($this->pinReset->verifyBvn($wallet, $text)) {
            $this->issueResetLink($session, $instance, $phone, $wallet);

            return;
        }

        $this->pinReset->recordFailure($wallet, 'id_failed');
        $this->client->sendText(
            $instance,
            $phone,
            'BVN does not match our records. Check the 11 digits and try again, or *BACK* to cancel.'
        );
    }

    private function handleVerifyCac(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        string $text,
    ): void {
        if ($this->pinReset->isRateLimited($wallet)) {
            $this->sendRateLimited($instance, $phone);

            return;
        }

        if ($this->pinReset->verifyCac($wallet, $text)) {
            $this->issueResetLink($session, $instance, $phone, $wallet);

            return;
        }

        $this->pinReset->recordFailure($wallet, 'id_failed');
        $this->client->sendText(
            $instance,
            $phone,
            'CAC number does not match our records. Try again or *BACK* to cancel.'
        );
    }

    private function runNameCheckOrPrompt(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
    ): void {
        if ($this->pinReset->nameMatchPasses($wallet)) {
            $this->issueResetLink($session, $instance, $phone, $wallet);

            return;
        }

        $bank = $this->pinReset->bankNameForMatch($wallet);
        $hint = $bank !== '' ? "\n\nBank account name on file: *{$bank}*" : '';

        $this->client->sendText(
            $instance,
            $phone,
            'Reply with your *full name as it appears on your bank account*.'.
            $hint.
            "\n\n*BACK* — cancel."
        );
    }

    private function issueResetLink(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
    ): void {
        $created = $this->pinReset->createResetToken($session->fresh(), $instance, $phone, $wallet);
        if (! ($created['ok'] ?? false)) {
            if (($created['error'] ?? '') === 'rate_limited') {
                $this->sendRateLimited($instance, $phone);

                return;
            }
            $this->client->sendText($instance, $phone, 'Could not create reset link. Try again later or contact support.');

            return;
        }

        $url = (string) ($created['url'] ?? '');
        $session->update([
            'chat_flow' => WhatsappWaWalletMenuHandler::FLOW,
            'chat_context' => ['step' => 'submenu'],
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "*Reset wallet PIN*\n\n".
            "Open this *one-time* link to choose a new 4-digit PIN:\n{$url}\n\n".
            "*Do not* send your PIN in this chat.\n\n".
            'Link expires soon. *MENU* when done.'
        );
    }

    private function sendRateLimited(string $instance, string $phone): void
    {
        $this->client->sendText(
            $instance,
            $phone,
            'Too many failed PIN reset attempts. Try again in about 15 minutes.'
        );
    }

    private function recover(WhatsappSession $session, string $instance, string $phone): void
    {
        $session->update([
            'chat_flow' => WhatsappWaWalletMenuHandler::FLOW,
            'chat_context' => ['step' => 'submenu'],
        ]);
        $this->client->sendText($instance, $phone, 'PIN reset session ended. Send *RESET PIN* to try again or *MENU* for wallet.');
    }
}
