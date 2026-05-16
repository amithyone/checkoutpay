<?php

namespace App\Services\Whatsapp;

use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;

/**
 * Guest hub: wallet-only mode.
 */
class WhatsappCheckoutServicesMenuHandler
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
    ) {}

    public function sendRootMenu(string $instance, string $phone): void
    {
        $this->client->sendText($instance, $phone, $this->rootMenuBody());
    }

    /** @deprecated Use sendRootMenu */
    public function sendMainMenu(string $instance, string $phone): void
    {
        $this->sendRootMenu($instance, $phone);
    }

    public function handleCommand(WhatsappSession $session, string $instance, string $phone, string $rawText): void
    {
        $cmd = WhatsappMenuInputNormalizer::commandToken($rawText);

        if ($this->isRootMenuCommand($cmd)) {
            $this->sendRootMenu($instance, $phone);

            return;
        }

        if (PhoneNormalizer::parseBareWalletMobileForP2pShortcut($rawText, $phone) !== null) {
            $linked = Renter::query()
                ->where('whatsapp_phone_e164', $phone)
                ->where('is_active', true)
                ->first();
            app(WhatsappWaWalletMenuHandler::class)->enterP2pFlowFromPhoneShortcut($session->fresh(), $instance, $phone, $rawText, $linked);

            return;
        }

        if (in_array($cmd, ['WALLET', 'TOPUP', 'TOP UP', '1'], true)) {
            app(WhatsappWaWalletMenuHandler::class)->openMenu($session->fresh(), $instance, $phone, null);

            return;
        }

        $linked = Renter::query()
            ->where('whatsapp_phone_e164', $phone)
            ->where('is_active', true)
            ->first();
        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $phone],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );
        if (app(WhatsappWaWalletMenuHandler::class)->tryHandleCasualBills($session->fresh(), $instance, $phone, $rawText, $linked, $wallet->fresh())) {
            return;
        }

        $this->sendRootMenu($instance, $phone);
    }

    private function isRootMenuCommand(string $cmd): bool
    {
        return in_array($cmd, ['MENU', 'START', 'SERVICES', 'HI', 'HELLO', 'HELP', 'HOME', 'BACK'], true);
    }

    public function rootMenuBody(): string
    {
        return "*Checkout*\n\n".
            "*1* — *WALLET* — balance, receive, send, bills, PIN & name\n\n".
            "Reply with *1* or *WALLET*.\n\n".
            WhatsappMenuInputNormalizer::navigationHelpFooter()."\n".
            "*RESTART* / *000* · *STOP* / *START* / *MENU*";
    }

    public function sendWalletIntro(string $instance, string $phone): void
    {
        $this->client->sendText($instance, $phone, $this->walletIntroBody());
    }

    private function walletIntroBody(): string
    {
        $url = $this->walletAppUrl();

        $t1max = number_format((float) config('whatsapp.wallet.tier1_max_balance', 50000), 0);
        $t1day = number_format((float) config('whatsapp.wallet.tier1_daily_transfer_limit', 50000), 0);

        return "*WhatsApp Wallet*\n\n".
            "Open the wallet menu with *WALLET* (balance, receive, transfer, PIN).\n\n".
            "*Tier 1:* WhatsApp number = your wallet ID. Cap ₦{$t1max} balance & ₦{$t1day} sent per day.\n\n".
            '*Tier 2:* *UPGRADE* in the wallet menu — KYC; permanent bank account via *'.(string) config('whatsapp.bot_brand_name', 'CheckoutNow')."*.\n\n".
            "Web app:\n{$url}\n\n".
            "*MENU* — main categories";
    }

    private function walletAppUrl(): string
    {
        return WhatsappWalletAppLinkCopy::url();
    }

    private function portalBusiness(): string
    {
        $u = rtrim((string) config('whatsapp.portals.business', ''), '/');

        return $u !== '' ? $u : $this->walletAppUrl();
    }

    private function portalRentals(): string
    {
        $u = rtrim((string) config('whatsapp.portals.rentals', ''), '/');

        return $u !== '' ? $u : $this->walletAppUrl();
    }
}
