<?php

namespace App\Services\Whatsapp;

use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Services\VtuNg\VtuNgApiClient;
use Illuminate\Support\Facades\Hash;

/**
 * WhatsApp wallet → VTU.ng (airtime, data, electricity). Debits wallet; refunds on API failure.
 */
class WhatsappWalletVtuFlowHandler
{
    public const FLOW = 'wa_wallet_vtu';

    private const PIN_LEN = 4;

    private const MAX_PIN_FAILS = 5;

    private const PIN_LOCK_MINUTES = 15;

    public function __construct(
        private EvolutionWhatsAppClient $client,
        private VtuNgApiClient $vtuApi,
        private WhatsappWalletVtuPurchaseService $purchase,
        private WhatsappWalletTransferCompletionService $transferCompletion,
        private WhatsappCheckoutServicesMenuHandler $checkoutServicesMenu,
        private WhatsappLinkedMenuHandler $linkedMenu,
    ) {}

    public function isAvailable(): bool
    {
        return $this->vtuApi->isConfigured();
    }

    public function start(WhatsappSession $session, string $instance, string $phone, ?Renter $linkedRenter): void
    {
        if (! $this->isAvailable()) {
            $this->client->sendText($instance, $phone, 'VTU (airtime / data / electricity) is not available right now.');

            return;
        }

        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);
        if (! $wallet->hasPin()) {
            $this->client->sendText($instance, $phone, 'Set a wallet PIN first. Open *WALLET* and reply *REGISTER*.');

            return;
        }
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked. Try again later.');

            return;
        }
        if ($wallet->normalizedSenderName() === null) {
            $this->client->sendText(
                $instance,
                $phone,
                'Set your *send name* first. Open *WALLET* and start a bank or P2P send once to save your name, or contact support.'
            );

            return;
        }

        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => ['step' => 'vtu_menu'],
        ]);
        $this->sendVtuRootMenu($instance, $phone);
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
        $ctx = $session->chat_context;
        if (! is_array($ctx)) {
            $ctx = [];
        }
        $step = (string) ($ctx['step'] ?? 'vtu_menu');

        if (in_array($cmd, ['MENU', 'MAIN', 'START', 'HOME'], true)) {
            $this->exitToMain($session, $instance, $phone, $linkedRenter);

            return;
        }

        if ($cmd === 'CANCEL') {
            $this->backToWalletSubmenu($session, $instance, $phone, $linkedRenter);

            return;
        }

        if ($cmd === 'BACK') {
            if ($step === 'vtu_menu') {
                $this->backToWalletSubmenu($session, $instance, $phone, $linkedRenter);

                return;
            }
            if ($step === 'vtu_dat_pick_plan') {
                $session->update(['chat_context' => ['step' => 'vtu_dat_network']]);
                $this->sendNetworkPicker($instance, $phone, 'data');

                return;
            }
            if (in_array($step, ['vtu_air_network', 'vtu_dat_network', 'vtu_el_disco'], true)) {
                $session->update(['chat_context' => ['step' => 'vtu_menu']]);
                $this->sendVtuRootMenu($instance, $phone);

                return;
            }
            if ($step === 'vtu_air_phone') {
                $session->update(['chat_context' => ['step' => 'vtu_air_network']]);
                $this->sendNetworkPicker($instance, $phone, 'airtime');

                return;
            }
            if ($step === 'vtu_air_amount') {
                $session->update(['chat_context' => ['step' => 'vtu_air_phone']]);
                $this->sendAirtimePhonePrompt($instance, $phone);

                return;
            }
            if ($step === 'vtu_dat_phone') {
                $merged = array_merge($ctx, ['step' => 'vtu_dat_pick_plan']);
                $session->update(['chat_context' => $merged]);
                $this->sendDataPlanPage($session, $instance, $phone, $merged, (int) ($merged['vtu_plan_page'] ?? 0));

                return;
            }
            if ($step === 'vtu_el_type') {
                $session->update(['chat_context' => ['step' => 'vtu_el_disco']]);
                $this->sendDiscoPicker($instance, $phone);

                return;
            }
            if ($step === 'vtu_el_meter') {
                $session->update(['chat_context' => array_merge($ctx, ['step' => 'vtu_el_type'])]);
                $this->sendMeterTypePrompt($instance, $phone, (string) ($ctx['vtu_el_service'] ?? ''));

                return;
            }
            if ($step === 'vtu_el_amount') {
                $session->update(['chat_context' => array_merge($ctx, ['step' => 'vtu_el_meter'])]);
                $this->client->sendText(
                    $instance,
                    $phone,
                    '*Electricity meter*\n\nSend the *meter number* (digits only).'
                );

                return;
            }
            $session->update(['chat_context' => ['step' => 'vtu_menu']]);
            $this->sendVtuRootMenu($instance, $phone);

            return;
        }

        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);

        match ($step) {
            'vtu_menu' => $this->handleVtuMenu($session, $instance, $phone, $cmd),
            'vtu_air_network' => $this->handleAirNetwork($session, $instance, $phone, $cmd, $ctx),
            'vtu_air_phone' => $this->handleAirPhone($session, $instance, $phone, $text, $cmd, $ctx, $phone),
            'vtu_air_amount' => $this->handleAirAmount($session, $instance, $phone, $text, $ctx),
            'vtu_air_pin' => $this->handleAirPin($session, $instance, $phone, $text, $ctx, $wallet, $linkedRenter),
            'vtu_dat_network' => $this->handleDatNetwork($session, $instance, $phone, $cmd, $ctx),
            'vtu_dat_pick_plan' => $this->handleDatPickPlan($session, $instance, $phone, $text, $cmd, $ctx, $linkedRenter),
            'vtu_dat_phone' => $this->handleDatPhone($session, $instance, $phone, $text, $cmd, $ctx, $phone),
            'vtu_dat_pin' => $this->handleDatPin($session, $instance, $phone, $text, $ctx, $wallet, $linkedRenter),
            'vtu_el_disco' => $this->handleElDisco($session, $instance, $phone, $cmd, $ctx, $linkedRenter),
            'vtu_el_type' => $this->handleElType($session, $instance, $phone, $cmd, $ctx),
            'vtu_el_meter' => $this->handleElMeter($session, $instance, $phone, $text, $ctx, $linkedRenter),
            'vtu_el_amount' => $this->handleElAmount($session, $instance, $phone, $text, $ctx),
            'vtu_el_pin' => $this->handleElPin($session, $instance, $phone, $text, $ctx, $wallet, $phone, $linkedRenter),
            default => $this->recover($session, $instance, $phone, $linkedRenter),
        };
    }

    private function sendVtuRootMenu(string $instance, string $phone): void
    {
        $this->client->sendText(
            $instance,
            $phone,
            "*VTU (airtime, data, electricity)*\n\n".
            "*1* — Airtime\n".
            "*2* — Data bundle\n".
            "*3* — Electricity bill\n\n".
            '*BACK* — return to wallet  *CANCEL* — wallet  *MENU* — main services'
        );
    }

    private function handleVtuMenu(WhatsappSession $session, string $instance, string $phone, string $cmd): void
    {
        if ($cmd === '1' || $cmd === 'AIRTIME') {
            $session->update(['chat_context' => ['step' => 'vtu_air_network']]);
            $this->sendNetworkPicker($instance, $phone, 'airtime');

            return;
        }
        if ($cmd === '2' || $cmd === 'DATA') {
            $session->update(['chat_context' => ['step' => 'vtu_dat_network']]);
            $this->sendNetworkPicker($instance, $phone, 'data');

            return;
        }
        if ($cmd === '3' || $cmd === 'ELECTRICITY' || $cmd === 'NEPA' || $cmd === 'PHCN') {
            $session->update(['chat_context' => ['step' => 'vtu_el_disco']]);
            $this->sendDiscoPicker($instance, $phone);

            return;
        }
        $this->client->sendText($instance, $phone, 'Reply *1*, *2*, or *3*. *BACK* returns to the wallet.');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleAirNetwork(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        array $ctx
    ): void {
        $net = $this->resolveNetworkFromCmd($cmd);
        if ($net === null) {
            $this->client->sendText($instance, $phone, 'Pick *1*–*4* for the network. *BACK* — VTU menu.');

            return;
        }
        $ctx['step'] = 'vtu_air_phone';
        $ctx['vtu_network'] = $net['id'];
        $session->update(['chat_context' => $ctx]);
        $this->sendAirtimePhonePrompt($instance, $phone);
    }

    private function sendAirtimePhonePrompt(string $instance, string $phone): void
    {
        $this->client->sendText(
            $instance,
            $phone,
            '*Airtime — phone*\n\n'.
            "Send the *11-digit* Nigerian number (e.g. *080…*), or *1* to use *this WhatsApp number*.\n\n".
            '*BACK* — pick network'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleAirPhone(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        array $ctx,
        string $senderE164
    ): void {
        $recipient = null;
        if ($cmd === '1' || $cmd === 'ME' || $cmd === 'SELF') {
            $recipient = PhoneNormalizer::canonicalNgE164Digits($senderE164);
        } else {
            $recipient = PhoneNormalizer::canonicalNgE164Digits(PhoneNormalizer::digitsOnly($text));
        }
        if ($recipient === null || strlen($recipient) < 12) {
            $this->client->sendText($instance, $phone, 'Send a valid Nigerian mobile number, or *1* for this number.');

            return;
        }
        $ctx['step'] = 'vtu_air_amount';
        $ctx['vtu_recipient_e164'] = $recipient;
        $session->update(['chat_context' => $ctx]);
        $min = number_format((float) config('vtu.airtime_min', 50), 0);
        $max = number_format((float) config('vtu.airtime_max', 50000), 0);
        $this->client->sendText(
            $instance,
            $phone,
            "*Airtime — amount*\n\n".
            "Send amount in *Naira* (whole numbers). Min ₦{$min}, max ₦{$max}.\n\n".
            '*BACK* — change number'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleAirAmount(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $t = preg_replace('/[^\d.]/', '', $text) ?? '';
        if ($t === '' || ! is_numeric($t)) {
            $this->client->sendText($instance, $phone, 'Send a numeric amount in Naira.');

            return;
        }
        $amount = round((float) $t, 0);
        $min = (float) config('vtu.airtime_min', 50);
        $max = (float) config('vtu.airtime_max', 50000);
        if ($amount < $min || $amount > $max) {
            $this->client->sendText(
                $instance,
                $phone,
                'Amount must be between ₦'.number_format($min, 0).' and ₦'.number_format($max, 0).'.'
            );

            return;
        }
        $ctx['step'] = 'vtu_air_pin';
        $ctx['vtu_amount'] = $amount;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            '*Confirm with wallet PIN*\n\n'.
            'Send your *4-digit* wallet PIN to buy airtime.'."\n\n".
            '*BACK* — change amount  *CANCEL* — wallet'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleAirPin(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): void {
        if (! $this->verifyPinStep($session, $instance, $phone, $text, $wallet, $linkedRenter)) {
            return;
        }

        $net = (string) ($ctx['vtu_network'] ?? '');
        $recipient = (string) ($ctx['vtu_recipient_e164'] ?? '');
        $amount = isset($ctx['vtu_amount']) ? (float) $ctx['vtu_amount'] : 0.0;
        if ($net === '' || $recipient === '' || $amount < 1) {
            $this->recover($session, $instance, $phone, $linkedRenter);

            return;
        }

        $w = $wallet->fresh();
        $result = $this->purchase->purchaseAirtime($w, $net, $recipient, $amount);
        $this->afterPurchase($session, $instance, $phone, $w, $result, $linkedRenter);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleDatNetwork(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        array $ctx
    ): void {
        $net = $this->resolveNetworkFromCmd($cmd);
        if ($net === null) {
            $this->client->sendText($instance, $phone, 'Pick *1*–*4* for the network. *BACK* — VTU menu.');

            return;
        }
        $this->client->sendText($instance, $phone, 'Loading data plans…');
        $plansRes = $this->vtuApi->fetchDataPlans($net['id']);
        if (! $plansRes['ok']) {
            $this->client->sendText(
                $instance,
                $phone,
                'Could not load data plans: '.($plansRes['message'] ?? 'Error')."\n\n*BACK* — VTU menu."
            );
            $session->update(['chat_context' => ['step' => 'vtu_menu']]);

            return;
        }
        $plans = $plansRes['plans'] ?? [];
        $available = array_values(array_filter($plans, fn ($p) => ($p['available'] ?? false) === true));
        if ($available === []) {
            $this->client->sendText($instance, $phone, 'No available data plans for that network right now. *BACK* — VTU menu.');
            $session->update(['chat_context' => ['step' => 'vtu_menu']]);

            return;
        }
        $ctx['step'] = 'vtu_dat_pick_plan';
        $ctx['vtu_network'] = $net['id'];
        $ctx['vtu_plans'] = $available;
        $ctx['vtu_plan_page'] = 0;
        $session->update(['chat_context' => $ctx]);
        $this->sendDataPlanPage($session, $instance, $phone, $ctx, 0);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function sendDataPlanPage(
        WhatsappSession $session,
        string $instance,
        string $phone,
        array $ctx,
        int $page
    ): void {
        $plans = $ctx['vtu_plans'] ?? [];
        if (! is_array($plans) || $plans === []) {
            $this->client->sendText($instance, $phone, 'No plans loaded. *BACK* — VTU menu.');

            return;
        }
        $pageSize = (int) config('vtu.data_plans_page_size', 6);
        $total = count($plans);
        $lastPage = (int) max(0, (int) ceil($total / $pageSize) - 1);
        $page = max(0, min($lastPage, $page));
        $slice = array_slice($plans, $page * $pageSize, $pageSize);
        $lines = ["*Data — pick a plan* (page ".($page + 1).' / '.($lastPage + 1).")\n"];
        $i = 1;
        foreach ($slice as $p) {
            if (! is_array($p)) {
                continue;
            }
            $label = (string) ($p['label'] ?? 'Plan');
            $price = number_format((float) ($p['price'] ?? 0), 2);
            $lines[] = "*{$i}* — {$label} — ₦{$price}";
            $i++;
        }
        $lines[] = '';
        if ($page < $lastPage) {
            $lines[] = '*MORE* — next page';
        }
        if ($page > 0) {
            $lines[] = '*PREV* — previous page';
        }
        $lines[] = '*BACK* — change network';
        $ctx['vtu_plan_page'] = $page;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleDatPickPlan(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        array $ctx,
        ?Renter $linkedRenter
    ): void {
        $plans = $ctx['vtu_plans'] ?? [];
        if (! is_array($plans) || $plans === []) {
            $this->recover($session, $instance, $phone, $linkedRenter);

            return;
        }
        $pageSize = (int) config('vtu.data_plans_page_size', 6);
        $page = (int) ($ctx['vtu_plan_page'] ?? 0);
        $total = count($plans);
        $lastPage = (int) max(0, (int) ceil($total / $pageSize) - 1);

        if (in_array($cmd, ['MORE', 'NEXT'], true)) {
            if ($page < $lastPage) {
                $ctx['vtu_plan_page'] = $page + 1;
                $session->update(['chat_context' => $ctx]);
                $this->sendDataPlanPage($session, $instance, $phone, $ctx, $page + 1);
            } else {
                $this->client->sendText($instance, $phone, 'Already on the last page.');
            }

            return;
        }
        if (in_array($cmd, ['PREV', 'PREVIOUS'], true)) {
            if ($page > 0) {
                $ctx['vtu_plan_page'] = $page - 1;
                $session->update(['chat_context' => $ctx]);
                $this->sendDataPlanPage($session, $instance, $phone, $ctx, $page - 1);
            } else {
                $this->client->sendText($instance, $phone, 'Already on the first page.');
            }

            return;
        }

        $pick = (int) preg_replace('/\D/', '', $text);
        if ($pick < 1 || $pick > $pageSize) {
            $this->client->sendText($instance, $phone, 'Reply with a plan number on this page, *MORE*, or *PREV*.');

            return;
        }
        $idx = $page * $pageSize + ($pick - 1);
        if (! isset($plans[$idx]) || ! is_array($plans[$idx])) {
            $this->client->sendText($instance, $phone, 'Invalid plan. Pick a number from the list.');

            return;
        }
        $row = $plans[$idx];
        $ctx['step'] = 'vtu_dat_phone';
        $ctx['vtu_sel_variation_id'] = (int) ($row['variation_id'] ?? 0);
        $ctx['vtu_sel_label'] = (string) ($row['label'] ?? '');
        $ctx['vtu_sel_price'] = round((float) ($row['price'] ?? 0), 2);
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            '*Data — phone*\n\n'.
            "Send the *11-digit* number to receive data, or *1* for *this WhatsApp number*.\n\n".
            '*BACK* — plans list'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleDatPhone(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd,
        array $ctx,
        string $senderE164
    ): void {
        $recipient = null;
        if ($cmd === '1' || $cmd === 'ME' || $cmd === 'SELF') {
            $recipient = PhoneNormalizer::canonicalNgE164Digits($senderE164);
        } else {
            $recipient = PhoneNormalizer::canonicalNgE164Digits(PhoneNormalizer::digitsOnly($text));
        }
        if ($recipient === null || strlen($recipient) < 12) {
            $this->client->sendText($instance, $phone, 'Send a valid Nigerian mobile number, or *1* for this number.');

            return;
        }
        $ctx['step'] = 'vtu_dat_pin';
        $ctx['vtu_recipient_e164'] = $recipient;
        $session->update(['chat_context' => $ctx]);
        $label = (string) ($ctx['vtu_sel_label'] ?? '');
        $price = number_format((float) ($ctx['vtu_sel_price'] ?? 0), 2);
        $this->client->sendText(
            $instance,
            $phone,
            "*Confirm data purchase*\n\n".
            "{$label}\n".
            "Price: *₦{$price}*\n\n".
            'Send your *4-digit* wallet PIN to pay.'."\n\n".
            '*BACK* — change number  *CANCEL* — wallet'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleDatPin(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): void {
        if (! $this->verifyPinStep($session, $instance, $phone, $text, $wallet, $linkedRenter)) {
            return;
        }

        $net = (string) ($ctx['vtu_network'] ?? '');
        $recipient = (string) ($ctx['vtu_recipient_e164'] ?? '');
        $vid = (int) ($ctx['vtu_sel_variation_id'] ?? 0);
        $price = (float) ($ctx['vtu_sel_price'] ?? 0);
        if ($net === '' || $recipient === '' || $vid < 1 || $price < 1) {
            $this->recover($session, $instance, $phone, $linkedRenter);

            return;
        }

        $w = $wallet->fresh();
        $result = $this->purchase->purchaseData($w, $net, $recipient, $vid, $price);
        $this->afterPurchase($session, $instance, $phone, $w, $result, $linkedRenter);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleElDisco(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        array $ctx,
        ?Renter $linkedRenter
    ): void {
        $discos = config('vtu.electricity_discos', []);
        if (! is_array($discos) || $discos === []) {
            $this->client->sendText($instance, $phone, 'Electricity discos are not configured.');

            return;
        }
        $n = (int) preg_replace('/\D/', '', $cmd);
        if ($n < 1 || $n > count($discos)) {
            $this->client->sendText(
                $instance,
                $phone,
                'Pick a disco number from the list (*1*–*'.count($discos).'*.). *BACK* — VTU menu.'
            );

            return;
        }
        $row = $discos[$n - 1];
        if (! is_array($row) || empty($row['id'])) {
            $this->recover($session, $instance, $phone, $linkedRenter);

            return;
        }
        $ctx['step'] = 'vtu_el_type';
        $ctx['vtu_el_service'] = (string) $row['id'];
        $session->update(['chat_context' => $ctx]);
        $this->sendMeterTypePrompt($instance, $phone, (string) $row['id']);
    }

    private function sendMeterTypePrompt(string $instance, string $phone, string $serviceId): void
    {
        $this->client->sendText(
            $instance,
            $phone,
            "*Electricity — meter type*\n\n".
            "*1* — Prepaid\n".
            "*2* — Postpaid\n\n".
            '*BACK* — pick disco'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleElType(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        array $ctx
    ): void {
        $var = null;
        if ($cmd === '1' || $cmd === 'PREPAID') {
            $var = 'prepaid';
        }
        if ($cmd === '2' || $cmd === 'POSTPAID') {
            $var = 'postpaid';
        }
        if ($var === null) {
            $this->client->sendText($instance, $phone, 'Reply *1* prepaid or *2* postpaid.');

            return;
        }
        $ctx['step'] = 'vtu_el_meter';
        $ctx['vtu_el_variation'] = $var;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            '*Electricity — meter*\n\n'.
            'Send the *meter number* (digits only).'."\n\n".
            '*BACK* — meter type'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleElMeter(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        ?Renter $linkedRenter
    ): void {
        $meter = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($meter) < 6) {
            $this->client->sendText($instance, $phone, 'Send a valid meter number (digits only).');

            return;
        }
        $service = (string) ($ctx['vtu_el_service'] ?? '');
        $variation = (string) ($ctx['vtu_el_variation'] ?? '');
        if ($service === '' || $variation === '') {
            $this->recover($session, $instance, $phone, $linkedRenter);

            return;
        }
        $this->client->sendText($instance, $phone, 'Verifying meter…');
        $ver = $this->vtuApi->verifyElectricityCustomer($service, $meter, $variation);
        if (! $ver['ok']) {
            $this->client->sendText(
                $instance,
                $phone,
                'Verification failed: '.($ver['message'] ?? 'Error')."\n\nTry again or *BACK*."
            );

            return;
        }
        $data = $ver['data'] ?? [];
        $name = '';
        if (is_array($data)) {
            $name = trim((string) ($data['customer_name'] ?? $data['name'] ?? $data['Customer_Name'] ?? ''));
        }
        if ($name === '') {
            $name = 'Customer';
        }
        $min = number_format((float) config('vtu.electricity_min', 500), 0);
        $ctx['step'] = 'vtu_el_amount';
        $ctx['vtu_el_meter'] = $meter;
        $ctx['vtu_el_customer_name'] = $name;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            "*Verified:* {$name}\n\n".
            "Send *amount* in Naira (minimum ₦{$min}).\n\n".
            '*BACK* — change meter'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleElAmount(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $t = preg_replace('/[^\d.]/', '', $text) ?? '';
        if ($t === '' || ! is_numeric($t)) {
            $this->client->sendText($instance, $phone, 'Send a numeric amount in Naira.');

            return;
        }
        $amount = round((float) $t, 0);
        $min = (float) config('vtu.electricity_min', 500);
        if ($amount < $min) {
            $this->client->sendText($instance, $phone, 'Minimum amount is ₦'.number_format($min, 0).'.');

            return;
        }
        $ctx['step'] = 'vtu_el_pin';
        $ctx['vtu_amount'] = $amount;
        $session->update(['chat_context' => $ctx]);
        $this->client->sendText(
            $instance,
            $phone,
            '*Confirm with wallet PIN*\n\n'.
            'Send your *4-digit* wallet PIN to pay electricity.'."\n\n".
            '*BACK* — change amount  *CANCEL* — wallet'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleElPin(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx,
        WhatsappWallet $wallet,
        string $payerE164,
        ?Renter $linkedRenter
    ): void {
        if (! $this->verifyPinStep($session, $instance, $phone, $text, $wallet, $linkedRenter)) {
            return;
        }

        $service = (string) ($ctx['vtu_el_service'] ?? '');
        $meter = (string) ($ctx['vtu_el_meter'] ?? '');
        $variation = (string) ($ctx['vtu_el_variation'] ?? '');
        $amount = isset($ctx['vtu_amount']) ? (float) $ctx['vtu_amount'] : 0.0;
        $cust = (string) ($ctx['vtu_el_customer_name'] ?? '');
        if ($service === '' || $meter === '' || $variation === '' || $amount < 1) {
            $this->recover($session, $instance, $phone, $linkedRenter);

            return;
        }

        $w = $wallet->fresh();
        $result = $this->purchase->purchaseElectricity(
            $w,
            $service,
            $meter,
            $variation,
            $payerE164,
            $amount,
            $cust !== '' ? $cust : null
        );
        $this->afterPurchase($session, $instance, $phone, $w, $result, $linkedRenter);
    }

    /**
     * @param  array{ok: bool, message: string, balance_after?: float|null}  $result
     */
    private function afterPurchase(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
        array $result,
        ?Renter $linkedRenter
    ): void {
        $w = $wallet->fresh();
        if ($result['ok'] ?? false) {
            $w->pin_failed_attempts = 0;
            $w->save();
            $bal = isset($result['balance_after']) ? (float) $result['balance_after'] : (float) $w->balance;
            $this->client->sendText(
                $instance,
                $phone,
                "*Done*\n\n".
                ($result['message'] ?? 'Success')."\n\n".
                'New balance: *₦'.number_format($bal, 2).'*'
            );
        } else {
            $this->client->sendText($instance, $phone, ($result['message'] ?? 'Purchase failed.')."\n\nTry again or *CANCEL*.");
        }
        $this->backToWalletSubmenu($session, $instance, $phone, $linkedRenter);
    }

    private function verifyPinStep(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        WhatsappWallet $wallet,
        ?Renter $linkedRenter
    ): bool {
        if ($wallet->isPinLocked()) {
            $this->client->sendText($instance, $phone, 'Wallet PIN is temporarily locked.');
            $this->backToWalletSubmenu($session, $instance, $phone, $linkedRenter);

            return false;
        }
        $digits = preg_replace('/\D/', '', $text) ?? '';
        if (strlen($digits) !== self::PIN_LEN) {
            $this->client->sendText($instance, $phone, 'Send your *4-digit* wallet PIN.');

            return false;
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
                    'Too many wrong PIN attempts. Locked for '.self::PIN_LOCK_MINUTES.' minutes.'
                );
                $this->backToWalletSubmenu($session, $instance, $phone, $linkedRenter);

                return false;
            }
            $this->client->sendText($instance, $phone, 'Wrong PIN. Try again or *CANCEL*.');

            return false;
        }

        return true;
    }

    private function sendNetworkPicker(string $instance, string $phone, string $forKind): void
    {
        $nets = config('vtu.networks', []);
        $lines = $forKind === 'data'
            ? ["*Data — network*\n"]
            : ["*Airtime — network*\n"];
        $i = 1;
        if (is_array($nets)) {
            foreach ($nets as $n) {
                if (! is_array($n)) {
                    continue;
                }
                $lines[] = "*{$i}* — ".($n['label'] ?? $n['id'] ?? '?');
                $i++;
            }
        }
        $lines[] = "\n*BACK* — VTU menu";
        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    private function sendDiscoPicker(string $instance, string $phone): void
    {
        $discos = config('vtu.electricity_discos', []);
        $lines = ["*Electricity — disco*\n"];
        $i = 1;
        if (is_array($discos)) {
            foreach ($discos as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $lines[] = "*{$i}* — ".($d['label'] ?? $d['id'] ?? '?');
                $i++;
            }
        }
        $lines[] = "\n*BACK* — VTU menu";
        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function resolveNetworkFromCmd(string $cmd): ?array
    {
        $nets = config('vtu.networks', []);
        if (! is_array($nets)) {
            return null;
        }
        $n = (int) preg_replace('/\D/', '', $cmd);
        if ($n < 1 || $n > count($nets)) {
            return null;
        }
        $row = $nets[$n - 1];
        if (! is_array($row) || empty($row['id'])) {
            return null;
        }

        return ['id' => (string) $row['id'], 'label' => (string) ($row['label'] ?? $row['id'])];
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

    private function backToWalletSubmenu(
        WhatsappSession $session,
        string $instance,
        string $phone,
        ?Renter $linkedRenter
    ): void {
        $wallet = $this->findOrCreateWallet($phone, $linkedRenter);
        $session->update([
            'chat_flow' => WhatsappWaWalletMenuHandler::FLOW,
            'chat_context' => ['step' => 'submenu'],
        ]);
        $this->transferCompletion->sendWalletSubmenu($instance, $phone, $wallet->fresh());
    }

    private function exitToMain(
        WhatsappSession $session,
        string $instance,
        string $phone,
        ?Renter $linkedRenter
    ): void {
        $session->update(['chat_flow' => null, 'chat_context' => null]);
        if ($linkedRenter !== null && $linkedRenter->is_active) {
            $this->linkedMenu->sendRootForRenter($linkedRenter->fresh(), $instance, $phone);
        } else {
            $this->checkoutServicesMenu->sendRootMenu($instance, $phone);
        }
    }

    private function recover(
        WhatsappSession $session,
        string $instance,
        string $phone,
        ?Renter $linkedRenter
    ): void {
        $session->update(['chat_context' => ['step' => 'vtu_menu']]);
        $this->sendVtuRootMenu($instance, $phone);
    }
}
