<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;

/**
 * Guest hub: two main categories — *RENTALS* and *WALLET* (WhatsApp wallet), plus support stubs.
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

        if (in_array($cmd, ['WALLET', 'TOPUP', 'TOP UP', '2'], true)) {
            app(WhatsappWaWalletMenuHandler::class)->openMenu($session->fresh(), $instance, $phone, null);

            return;
        }

        if (in_array($cmd, ['TICKET', 'TICKETS', 'SUPPORT', '3'], true)) {
            $this->client->sendText($instance, $phone, $this->ticketsBody());

            return;
        }

        if (in_array($cmd, ['INVOICE', 'INVOICES', 'PAY', 'PAYMENT', '4'], true)) {
            $this->client->sendText($instance, $phone, $this->invoiceBody());

            return;
        }

        $this->sendRootMenu($instance, $phone);
    }

    private function isRootMenuCommand(string $cmd): bool
    {
        return in_array($cmd, ['MENU', 'START', 'SERVICES', '0', 'HI', 'HELLO', 'HELP', 'HOME'], true);
    }

    public function rootMenuBody(): string
    {
        $biz = $this->portalBusiness();
        $rentals = $this->portalRentals();

        return "*Checkout*\n\n".
            "*Main categories*\n\n".
            "*1* — *RENTALS* — browse gear & prices (no login)\n".
            "*2* — *WALLET* — WhatsApp wallet: balance, top-up, bank transfer\n".
            "*3* — *TICKETS* — help (dashboard login)\n".
            "*4* — *INVOICE* — pay / view invoices (login)\n\n".
            "You can reply with the *number* or the *keyword*.\n\n".
            "Link your *rentals* account: send your *email* here.\n\n".
            "*RESTART* — back to this menu anytime\n".
            "*STOP* — pause auto-replies  *START* / *MENU* — resume\n\n".
            "Rentals site: {$rentals}\n".
            "Business portal: {$biz}";
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
            "*MENU* — main categories (or *1*–*4*)";
    }

    private function ticketsBody(): string
    {
        $biz = $this->portalBusiness();

        return "*Tickets / support*\n\n".
            "Sign in to your business or account dashboard:\n{$biz}/dashboard/login\n\n".
            "Rentals guests: use the rentals site contact options.\n\n".
            "*MENU* — main categories (or *1*–*4*)";
    }

    private function invoiceBody(): string
    {
        $biz = $this->portalBusiness();

        return "*Invoices & payments*\n\n".
            "Open your Checkout business dashboard to view and pay:\n{$biz}/dashboard/login\n\n".
            "*MENU* — main categories (or *1*–*4*)";
    }

    private function walletAppUrl(): string
    {
        $u = rtrim((string) config('whatsapp.wallet_app_url', ''), '/');
        if ($u !== '') {
            return $u;
        }

        return rtrim((string) config('whatsapp.public_url', ''), '/') ?: rtrim((string) config('app.url'), '/');
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
