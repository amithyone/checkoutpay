<?php

namespace App\Services\Whatsapp;

use App\Models\RentalCategory;
use App\Models\RentalItem;
use App\Models\Renter;
use App\Models\WhatsappSession;
use App\Services\Rentals\RenterRentalWalletSubmitService;
use App\Services\Rentals\RenterWalletFundPaymentService;
use Carbon\Carbon;

class WhatsappLinkedMenuHandler
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
        private RenterWalletFundPaymentService $fundPaymentService,
        private RenterRentalWalletSubmitService $rentalWalletSubmit,
        private WhatsappCheckoutServicesMenuHandler $checkoutServicesMenu,
    ) {}

    public function handle(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $rawText,
        bool $justLinked
    ): void {
        $this->clearFlow($session);
        if ($justLinked) {
            $this->client->sendText($instance, $phone, "Linked successfully, {$renter->name}. Opening Wallet...");
        }
        app(WhatsappWaWalletMenuHandler::class)->openMenu($session->fresh(), $instance, $phone, $renter->fresh());
    }

    private function appBase(): string
    {
        $b = rtrim((string) config('whatsapp.public_url', ''), '/');

        return $b !== '' ? $b : rtrim((string) config('app.url'), '/');
    }

    private function portalBusiness(): string
    {
        $u = rtrim((string) config('whatsapp.portals.business', ''), '/');

        return $u !== '' ? $u : $this->appBase();
    }

    private function portalRentals(): string
    {
        $u = rtrim((string) config('whatsapp.portals.rentals', ''), '/');

        return $u !== '' ? $u : $this->appBase();
    }

    private function portalTax(): string
    {
        return rtrim((string) config('whatsapp.portals.tax', 'https://nigtax.com'), '/');
    }

    public function sendRootForRenter(Renter $renter, string $instance, string $phone): void
    {
        app(WhatsappWaWalletMenuHandler::class)->openMenu(
            WhatsappSession::query()->firstOrCreate(['phone_e164' => $phone]),
            $instance,
            $phone,
            $renter->fresh()
        );
    }

    private function menuBody(): string
    {
        $rentals = $this->portalRentals();

        return "*Main categories*\n\n".
            "*1* — *RENTALS* — browse gear; pay with your *rentals* wallet\n".
            "*2* — *WALLET* — *WhatsApp wallet* (separate from rentals wallet)\n".
            "*3* — *BALANCE* — rentals wallet\n".
            "*4* — *FUND* — add money to *rentals* wallet\n".
            "*5* — *WITHDRAW* — business payouts info\n\n".
            "Reply with a *number* or the *keyword*.\n\n".
            WhatsappMenuInputNormalizer::navigationHelpFooter()."\n\n".
            "Full rentals site: {$rentals}\n\n".
            '*STOP* — pause bot  *START* / *MENU* — resume';
    }

    private function clearFlow(WhatsappSession $session): void
    {
        $session->update([
            'chat_flow' => null,
            'chat_context' => null,
        ]);
    }

    private function formatMoney(float $n): string
    {
        return '₦'.number_format($n, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(WhatsappSession $session): array
    {
        $c = $session->chat_context;

        return is_array($c) ? $c : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveContext(WhatsappSession $session, ?string $flow, array $data): void
    {
        $session->update([
            'chat_flow' => $flow,
            'chat_context' => $data === [] ? null : $data,
        ]);
    }

    private function handleGlobalCommands(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd
    ): void {
        $wallet = $this->formatMoney((float) ($renter->fresh()->wallet_balance ?? 0));

        $cmd = match ($cmd) {
            '1' => 'RENTALS',
            '2' => 'WALLET',
            '3' => 'BALANCE',
            '4' => 'FUND',
            '5' => 'WITHDRAW',
            default => $cmd,
        };

        if ($cmd === 'BALANCE') {
            $this->client->sendText($instance, $phone, "*Wallet balance:* {$wallet}");

            return;
        }

        if (in_array($cmd, ['MENU', 'HELP', 'HI', 'HELLO', 'START', 'BACK'], true)) {
            $this->client->sendText(
                $instance,
                $phone,
                "CheckoutNow — {$renter->email}\n*Rentals wallet:* {$wallet}\n\n".$this->menuBody()
            );

            return;
        }

        if (in_array($cmd, ['RESTART', 'MAIN'], true)) {
            $this->clearFlow($session);
            $this->sendRootForRenter($renter->fresh(), $instance, $phone);

            return;
        }

        if ($cmd === 'WALLET' || $cmd === 'TOPUP' || $cmd === 'TOP UP') {
            app(WhatsappWaWalletMenuHandler::class)->openMenu($session->fresh(), $instance, $phone, $renter);

            return;
        }

        if ($cmd === 'FUND') {
            $this->saveContext($session, 'rental_fund', ['step' => 'amount']);
            $this->client->sendText(
                $instance,
                $phone,
                "*Fund wallet*\n\nSend the amount in Naira (numbers only), minimum ₦1.\nExample: *5000*\n\nReply *BACK* to cancel."
            );

            return;
        }

        if ($cmd === 'WITHDRAW') {
            $biz = $this->portalBusiness();
            $rentals = $this->portalRentals();
            $tax = $this->portalTax();
            $this->client->sendText(
                $instance,
                $phone,
                "*Withdrawals*\n\n".
                "Payouts are only for *verified business (merchant)* accounts on *CheckoutNow* — the *business balance* from customer payments. Not for renter wallets.\n\n".
                "*Where each experience lives*\n".
                "• Business: {$biz}\n".
                "• Rentals: {$rentals}\n".
                "• Tax: {$tax}\n\n".
                "Verified businesses: withdrawals & dashboard\n{$biz}/dashboard/withdrawals\n\n".
                "API (business *X-API-Key*): POST /api/v1/withdrawal\n\n".
                "Renters (this chat): *BALANCE*, *RENTALS*, *FUND*. Full catalog: {$rentals}"
            );

            return;
        }

        if ($cmd === 'RENTALS') {
            $this->startRentalsFlow($renter, $session, $instance, $phone);

            return;
        }

        if ($cmd === 'CHECK') {
            $ctx = $this->context($session);
            $txn = isset($ctx['fund_txn']) && is_string($ctx['fund_txn']) ? $ctx['fund_txn'] : '';
            if ($txn !== '') {
                $this->runFundCheck($renter, $session, $instance, $phone, $txn);

                return;
            }
            $this->client->sendText(
                $instance,
                $phone,
                'Send *CHECK* followed by your transaction ID, e.g. CHECK ABC123XYZ'
            );

            return;
        }

        if (in_array($cmd, ['BACK', 'CANCEL', 'EXIT'], true)) {
            $this->clearFlow($session);
            $this->client->sendText($instance, $phone, 'Cancelled. '.$this->menuBody());

            return;
        }

        if ($cmd !== '') {
            $this->client->sendText($instance, $phone, 'Send *MENU* for options, *FUND* to add money, or *RENTALS* to book.');
        }
    }

    private function handleRentalFundFlow(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd
    ): void {
        $ctx = $this->context($session);

        if (in_array($cmd, ['BACK', 'CANCEL', 'EXIT'], true)) {
            $this->clearFlow($session);
            $this->client->sendText($instance, $phone, 'Funding cancelled. '.$this->menuBody());

            return;
        }

        if (($ctx['step'] ?? '') === 'amount') {
            $amount = $this->parsePositiveAmount($text);
            if ($amount === null) {
                $this->client->sendText($instance, $phone, 'Send a valid amount (numbers only), e.g. *10000*');

                return;
            }

            $result = $this->fundPaymentService->createFundPayment($renter->fresh(), $amount);
            if (! $result['ok']) {
                $this->client->sendText($instance, $phone, $result['message'] ?? 'Could not start funding.');

                return;
            }

            $p = $result['payment'];
            $txn = $p['transaction_id'] ?? '';
            $this->saveContext($session, 'rental_fund', [
                'step' => 'await_transfer',
                'fund_txn' => $txn,
            ]);

            $bank = $p['bank_name'] ?? $p['bank'] ?? '';
            $acct = $p['account_number'] ?? '';
            $aname = $p['account_name'] ?? '';
            $amt = $this->formatMoney((float) ($p['amount'] ?? $amount));

            $this->client->sendText(
                $instance,
                $phone,
                "*Transfer {$amt}* to:\n".
                "*Bank:* {$bank}\n".
                "*Account:* {$acct}\n".
                "*Name:* {$aname}\n".
                "*Reference / ID:* {$txn}\n\n".
                "Use your *verified account name* as sender if possible.\n".
                "When done, reply *CHECK* to refresh status (we match bank alerts).\n\n".
                '*BACK* — cancel this reminder'
            );

            return;
        }

        if (($ctx['step'] ?? '') === 'await_transfer') {
            if ($cmd === 'CHECK' || str_starts_with($cmd, 'CHECK ')) {
                $txn = is_string($ctx['fund_txn'] ?? null) ? $ctx['fund_txn'] : '';
                if ($txn !== '') {
                    $this->runFundCheck($renter, $session, $instance, $phone, $txn);
                }

                return;
            }

            $this->client->sendText(
                $instance,
                $phone,
                'When your transfer is sent, reply *CHECK* to update your balance. '.$this->menuBody()
            );
        }
    }

    private function runFundCheck(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $transactionId
    ): void {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return;
        }

        $res = $this->fundPaymentService->checkFundPayment($renter->fresh(), $transactionId);
        if (! $res['ok']) {
            $this->client->sendText($instance, $phone, $res['message'] ?? 'Check failed.');

            return;
        }

        $status = $res['status'] ?? '';
        $bal = $this->formatMoney((float) ($res['wallet_balance'] ?? 0));

        if ($status === \App\Models\Payment::STATUS_APPROVED) {
            $this->clearFlow($session);
            $this->client->sendText(
                $instance,
                $phone,
                "Payment *confirmed*. Wallet: *{$bal}*\n\n".$this->menuBody()
            );

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            "Status: *{$status}*\nWallet: *{$bal}*\n\nIf you just paid, wait a few minutes and send *CHECK {$transactionId}* again."
        );
    }

    private function parsePositiveAmount(string $text): ?float
    {
        $t = preg_replace('/[^\d.]/', '', $text) ?? '';
        if ($t === '' || ! is_numeric($t)) {
            return null;
        }
        $n = (float) $t;
        if ($n < 1) {
            return null;
        }

        return round($n, 2);
    }

    private function startRentalsFlow(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone
    ): void {
        $categories = RentalCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($categories->isEmpty()) {
            $this->client->sendText($instance, $phone, 'No categories available yet. Try the website later.');

            return;
        }

        $lines = ["*Rentals* — pick a *category* (reply number):\n"];
        $ids = [];
        $i = 1;
        foreach ($categories as $c) {
            $ids[] = (int) $c->id;
            $lines[] = "*{$i}.* {$c->name}";
            $i++;
        }
        $lines[] = "\n*BACK* — cancel";

        $this->saveContext($session, 'rentals', [
            'step' => 'category',
            'cat_ids' => $ids,
        ]);

        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    private function handleRentalsFlow(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        string $cmd
    ): void {
        $ctx = $this->context($session);

        if (in_array($cmd, ['BACK', 'CANCEL', 'EXIT'], true)) {
            $step = (string) ($ctx['step'] ?? '');
            if ($step === 'category') {
                $this->clearFlow($session);
                $this->client->sendText($instance, $phone, 'Cancelled. '.$this->menuBody());

                return;
            }

            if ($step === 'item') {
                $this->startRentalsFlow($renter, $session, $instance, $phone);

                return;
            }

            if ($step === 'qty') {
                $catIds = isset($ctx['cat_ids']) && is_array($ctx['cat_ids']) ? $ctx['cat_ids'] : [];
                $categoryId = (int) ($ctx['category_id'] ?? 0);
                $this->showItemsForCategory($session, $instance, $phone, $categoryId, $catIds);

                return;
            }

            if ($step === 'dates') {
                $this->saveContext($session, 'rentals', [
                    'step' => 'qty',
                    'cat_ids' => $ctx['cat_ids'] ?? [],
                    'category_id' => (int) ($ctx['category_id'] ?? 0),
                    'item_ids' => $ctx['item_ids'] ?? [],
                    'item_id' => (int) ($ctx['item_id'] ?? 0),
                ]);
                $this->client->sendText(
                    $instance,
                    $phone,
                    '*How many?* (reply a number)\n\n*BACK* — item list'
                );

                return;
            }

            if ($step === 'confirm') {
                $this->saveContext($session, 'rentals', [
                    'step' => 'dates',
                    'cat_ids' => $ctx['cat_ids'] ?? [],
                    'category_id' => (int) ($ctx['category_id'] ?? 0),
                    'item_ids' => $ctx['item_ids'] ?? [],
                    'item_id' => (int) ($ctx['item_id'] ?? 0),
                    'qty' => (int) ($ctx['qty'] ?? 1),
                ]);
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Send start date and days again (*YYYY-MM-DD days*).'
                );

                return;
            }
        }

        $step = (string) ($ctx['step'] ?? '');

        if ($step === 'category') {
            $this->pickCategory($session, $instance, $phone, $text, $ctx);

            return;
        }

        if ($step === 'item') {
            $this->pickItem($session, $instance, $phone, $text, $ctx);

            return;
        }

        if ($step === 'qty') {
            $this->pickQty($session, $instance, $phone, $text, $ctx);

            return;
        }

        if ($step === 'dates') {
            $this->pickDates($renter, $session, $instance, $phone, $text, $ctx);

            return;
        }

        if ($step === 'confirm') {
            $this->confirmRental($renter, $session, $instance, $phone, $cmd, $ctx);

            return;
        }

        $this->clearFlow($session);
        $this->client->sendText($instance, $phone, 'Session reset. '.$this->menuBody());
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function pickCategory(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $catIds = isset($ctx['cat_ids']) && is_array($ctx['cat_ids']) ? $ctx['cat_ids'] : [];
        $idx = (int) preg_replace('/\D+/', '', $text);
        if ($idx < 1 || $idx > count($catIds)) {
            $this->client->sendText($instance, $phone, 'Reply with a valid category number from the list.');

            return;
        }

        $categoryId = (int) $catIds[$idx - 1];
        $this->showItemsForCategory($session, $instance, $phone, $categoryId, $catIds);
    }

    /**
     * @param  array<int>  $catIds
     */
    private function showItemsForCategory(
        WhatsappSession $session,
        string $instance,
        string $phone,
        int $categoryId,
        array $catIds
    ): void {
        $items = RentalItem::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->orderBy('name')
            ->get(['id', 'name', 'daily_rate', 'currency']);

        if ($items->isEmpty()) {
            $this->client->sendText($instance, $phone, 'No items in that category right now. Pick another category number or *BACK*.');

            return;
        }

        $lines = ["*Pick an item* (reply number):\n"];
        $ids = [];
        $n = 1;
        foreach ($items as $it) {
            $ids[] = (int) $it->id;
            $rate = $this->formatMoney((float) $it->daily_rate);
            $lines[] = "*{$n}.* {$it->name} — from {$rate}/day";
            $n++;
        }
        $lines[] = "\n*BACK* — categories";

        $this->saveContext($session, 'rentals', [
            'step' => 'item',
            'cat_ids' => $catIds,
            'category_id' => $categoryId,
            'item_ids' => $ids,
        ]);

        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function pickItem(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $itemIds = isset($ctx['item_ids']) && is_array($ctx['item_ids']) ? $ctx['item_ids'] : [];
        $idx = (int) preg_replace('/\D+/', '', $text);
        if ($idx < 1 || $idx > count($itemIds)) {
            $this->client->sendText($instance, $phone, 'Reply with a valid item number.');

            return;
        }

        $itemId = (int) $itemIds[$idx - 1];
        $this->saveContext($session, 'rentals', [
            'step' => 'qty',
            'cat_ids' => $ctx['cat_ids'] ?? [],
            'category_id' => (int) ($ctx['category_id'] ?? 0),
            'item_ids' => $itemIds,
            'item_id' => $itemId,
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "*How many?* (reply a number, default 1)\n\n*BACK* — item list"
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function pickQty(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $digits = preg_replace('/\D+/', '', $text) ?? '';
        $qty = $digits === '' ? 1 : (int) $digits;
        if ($qty < 1 || $qty > 50) {
            $this->client->sendText($instance, $phone, 'Send a quantity between 1 and 50.');

            return;
        }

        $itemId = (int) ($ctx['item_id'] ?? 0);

        $this->saveContext($session, 'rentals', [
            'step' => 'dates',
            'cat_ids' => $ctx['cat_ids'] ?? [],
            'category_id' => (int) ($ctx['category_id'] ?? 0),
            'item_ids' => $ctx['item_ids'] ?? [],
            'item_id' => $itemId,
            'qty' => $qty,
        ]);

        $this->client->sendText(
            $instance,
            $phone,
            "*Rental days*\n\n".
            "Send *start date* and *number of consecutive days*, separated by space.\n".
            "Example: *2026-04-15 3* (3 days from 15 April)\n\n".
            '*BACK* — change quantity'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function pickDates(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        if (count($parts) < 2) {
            $this->client->sendText($instance, $phone, 'Use: *YYYY-MM-DD days* — e.g. *2026-04-20 2*');

            return;
        }

        $startRaw = $parts[0];
        $daysCount = (int) preg_replace('/\D+/', '', $parts[1] ?? '0');
        if ($daysCount < 1 || $daysCount > 60) {
            $this->client->sendText($instance, $phone, 'Use 1–60 consecutive days.');

            return;
        }

        try {
            $start = Carbon::parse($startRaw)->startOfDay();
        } catch (\Throwable $e) {
            $this->client->sendText($instance, $phone, 'Could not read the start date. Use *YYYY-MM-DD*.');

            return;
        }

        if ($start->lt(now()->startOfDay())) {
            $this->client->sendText($instance, $phone, 'Start date cannot be in the past.');

            return;
        }

        $selected = [];
        for ($i = 0; $i < $daysCount; $i++) {
            $selected[] = $start->copy()->addDays($i)->format('Y-m-d');
        }

        $itemId = (int) ($ctx['item_id'] ?? 0);
        $qty = (int) ($ctx['qty'] ?? 1);

        $quote = $this->quoteItem($itemId, $qty, $selected);
        if (! $quote['ok']) {
            $this->client->sendText($instance, $phone, $quote['message'] ?? 'Could not price that request.');

            return;
        }

        $this->saveContext($session, 'rentals', [
            'step' => 'confirm',
            'cat_ids' => $ctx['cat_ids'] ?? [],
            'category_id' => (int) ($ctx['category_id'] ?? 0),
            'item_ids' => $ctx['item_ids'] ?? [],
            'item_id' => $itemId,
            'qty' => $qty,
            'dates' => $selected,
            'item_total' => $quote['item_total'],
            'caution' => $quote['caution'],
            'grand' => $quote['grand'],
            'label' => $quote['label'],
        ]);

        $wallet = $this->formatMoney((float) ($renter->fresh()->wallet_balance ?? 0));
        $grand = $this->formatMoney($quote['grand']);
        $rent = $this->formatMoney($quote['item_total']);
        $caution = $this->formatMoney($quote['caution']);

        $kycNote = '';
        if (! $renter->isKycVerified()) {
            $kycNote = "\n\nComplete rentals KYC at ".$this->portalRentals()." before you can pay.";
        }

        $emailNote = '';
        if (! $renter->hasVerifiedEmail()) {
            $emailNote = "\n\nVerify your email (inbox or rentals site) before you can pay.";
        }

        $this->client->sendText(
            $instance,
            $phone,
            "*{$quote['label']}*\n".
            "Qty: *{$qty}*\n".
            "Dates: *{$selected[0]}* → *{$selected[array_key_last($selected)]}* ({$daysCount} days)\n\n".
            "Rental: *{$rent}*\n".
            "Caution (if any): *{$caution}*\n".
            "*Total due:* {$grand}\n".
            "*Your wallet:* {$wallet}\n\n".
            "Reply *YES* to pay from wallet, or *BACK* to change dates.".
            $kycNote.
            $emailNote
        );
    }

    /**
     * @param  array<int, string>  $selected
     * @return array{ok: bool, message?: string, item_total?: float, caution?: float, grand?: float, label?: string}
     */
    private function quoteItem(int $itemId, int $quantity, array $selected): array
    {
        $item = RentalItem::query()
            ->where('id', $itemId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->with('business')
            ->first();

        if (! $item) {
            return ['ok' => false, 'message' => 'Item not available.'];
        }

        $days = count($selected);
        $rate = $item->getRateForPeriod($days);
        $itemTotal = $rate * $quantity;
        $globalEnabled = (bool) ($item->business?->rental_global_caution_fee_enabled ?? false);
        $globalPercent = (float) ($item->business?->rental_global_caution_fee_percent ?? 0);
        $cautionPercent = $globalEnabled
            ? $globalPercent
            : ($item->caution_fee_enabled ? (float) $item->caution_fee_percent : 0.0);
        $caution = $cautionPercent > 0 ? round(($itemTotal * $cautionPercent) / 100, 2) : 0.0;

        return [
            'ok' => true,
            'item_total' => $itemTotal,
            'caution' => $caution,
            'grand' => $itemTotal + $caution,
            'label' => $item->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function confirmRental(
        Renter $renter,
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        array $ctx
    ): void {
        if (! in_array($cmd, ['YES', 'Y', 'OK', 'PAY'], true)) {
            $this->client->sendText($instance, $phone, 'Reply *YES* to pay from wallet, or *BACK* to edit.');

            return;
        }

        $itemId = (int) ($ctx['item_id'] ?? 0);
        $qty = (int) ($ctx['qty'] ?? 1);
        $dates = isset($ctx['dates']) && is_array($ctx['dates']) ? $ctx['dates'] : [];
        $dates = array_values(array_filter($dates, fn ($d) => is_string($d)));

        $res = $this->rentalWalletSubmit->submit($renter->fresh(), $itemId, $qty, $dates);
        if (! $res['ok']) {
            $this->client->sendText($instance, $phone, $res['message']);
            if (str_contains(strtolower($res['message']), 'insufficient')) {
                $this->client->sendText($instance, $phone, 'Tip: *FUND* adds money to your wallet.');
            }

            return;
        }

        $this->clearFlow($session);
        $this->client->sendText(
            $instance,
            $phone,
            $res['message']."\n\n".$this->menuBody()
        );
    }
}
