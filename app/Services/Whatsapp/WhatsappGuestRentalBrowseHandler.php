<?php

namespace App\Services\Whatsapp;

use App\Models\RentalCategory;
use App\Models\RentalItem;
use App\Models\WhatsappSession;
use Carbon\Carbon;

/**
 * Browse rental categories/items/pricing without a linked renter account.
 * Booking and wallet pay still require linking on the site or via email in this chat.
 */
class WhatsappGuestRentalBrowseHandler
{
    public const FLOW = 'rentals_guest';

    public function __construct(
        private EvolutionWhatsAppClient $client,
    ) {}

    public function handle(WhatsappSession $session, string $instance, string $phone, string $rawText): void
    {
        $session->save();

        $text = trim($rawText);
        $cmd = WhatsappMenuInputNormalizer::commandToken($rawText);
        $isRoot = in_array($cmd, ['MENU', 'START', 'SERVICES', 'HI', 'HELLO', 'HELP', 'HOME', 'BACK'], true);

        if ($session->chat_flow !== self::FLOW) {
            if ($this->isGuestBrowseCommand($cmd) || $cmd === '1' || $isRoot || $cmd === '') {
                $this->start($session->fresh(), $instance, $phone);
            } else {
                $this->client->sendText($instance, $phone, $this->guestHelpMessage());
            }

            return;
        }

        $ctx = $this->context($session);

        if (in_array($cmd, ['BACK', 'CANCEL', 'EXIT'], true)) {
            $step = (string) ($ctx['step'] ?? '');
            if ($step === 'category') {
                $this->clearFlow($session);
                $this->client->sendText($instance, $phone, $this->guestHelpMessage());

                return;
            }

            if ($step === 'brand') {
                $this->start($session->fresh(), $instance, $phone);

                return;
            }

            if ($step === 'item') {
                $this->backFromItemStep($session, $instance, $phone, $ctx);

                return;
            }

            if ($step === 'qty') {
                $this->showItemPage(
                    $session,
                    $instance,
                    $phone,
                    (int) ($ctx['category_id'] ?? 0),
                    isset($ctx['cat_ids']) && is_array($ctx['cat_ids']) ? $ctx['cat_ids'] : [],
                    (string) ($ctx['brand_key'] ?? WhatsappRentalCatalogHelper::BRAND_KEY_ALL),
                    (int) ($ctx['item_page'] ?? 0),
                    (bool) ($ctx['brand_was_prompted'] ?? false)
                );

                return;
            }

            if ($step === 'dates') {
                $this->saveContext($session, array_merge($this->browsePersistKeys($ctx), [
                    'step' => 'qty',
                    'item_ids' => $ctx['item_ids'] ?? [],
                    'item_id' => (int) ($ctx['item_id'] ?? 0),
                ]));
                $this->client->sendText(
                    $instance,
                    $phone,
                    '*How many?* (reply a number)\n\n*BACK* — item list'
                );

                return;
            }

            if ($step === 'summary') {
                $this->saveContext($session, array_merge($this->browsePersistKeys($ctx), [
                    'step' => 'dates',
                    'item_ids' => $ctx['item_ids'] ?? [],
                    'item_id' => (int) ($ctx['item_id'] ?? 0),
                    'qty' => (int) ($ctx['qty'] ?? 1),
                ]));
                $this->client->sendText(
                    $instance,
                    $phone,
                    'Send start date and days again (*YYYY-MM-DD days*).'
                );

                return;
            }
        }

        if (in_array($cmd, ['MENU', 'HELP'], true)) {
            $this->client->sendText($instance, $phone, $this->guestHelpMessage());

            return;
        }

        if ($this->isGuestBrowseCommand($cmd)) {
            $this->start($session->fresh(), $instance, $phone);

            return;
        }

        $step = (string) ($ctx['step'] ?? '');

        match ($step) {
            'category' => $this->pickCategory($session, $instance, $phone, $text, $ctx),
            'brand' => $this->pickBrand($session, $instance, $phone, $text, $ctx),
            'item' => $this->pickItem($session, $instance, $phone, $text, $ctx),
            'qty' => $this->pickQty($session, $instance, $phone, $text, $ctx),
            'dates' => $this->pickDates($session, $instance, $phone, $text, $ctx),
            'summary' => $this->handleSummary($session, $instance, $phone, $cmd, $ctx),
            default => $this->recover($session, $instance, $phone),
        };
    }

    private function isGuestBrowseCommand(string $cmd): bool
    {
        return in_array($cmd, ['RENTALS', 'BROWSE', 'SHOP', 'CATALOG'], true);
    }

    private function guestHelpMessage(): string
    {
        $rentals = $this->portalRentals();

        return "*CheckoutNow (guest)*\n\n".
            "*1* or *RENTALS* — browse categories & prices (no account needed)\n\n".
            "To link this WhatsApp and pay from your wallet, send the *email* you use on {$rentals}.";
    }

    private function portalRentals(): string
    {
        $u = rtrim((string) config('whatsapp.portals.rentals', ''), '/');

        return $u !== '' ? $u : rtrim((string) config('whatsapp.public_url', env('APP_URL', '')), '/');
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
    private function saveContext(WhatsappSession $session, array $data): void
    {
        $session->update([
            'chat_flow' => self::FLOW,
            'chat_context' => $data === [] ? null : $data,
        ]);
    }

    private function clearFlow(WhatsappSession $session): void
    {
        $session->update([
            'chat_flow' => null,
            'chat_context' => null,
        ]);
    }

    private function recover(WhatsappSession $session, string $instance, string $phone): void
    {
        $this->clearFlow($session);
        $this->client->sendText($instance, $phone, 'Browse session reset. '.$this->guestHelpMessage());
    }

    private function start(WhatsappSession $session, string $instance, string $phone): void
    {
        $categories = RentalCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($categories->isEmpty()) {
            $this->client->sendText($instance, $phone, 'No categories available yet. Try again later.');

            return;
        }

        $lines = ["*Rentals* (guest) — pick a *category* (number):\n"];
        $ids = [];
        $i = 1;
        foreach ($categories as $c) {
            $ids[] = (int) $c->id;
            $lines[] = "*{$i}.* {$c->name}";
            $i++;
        }
        $lines[] = "\n*BACK* — exit browse";

        $this->saveContext($session, [
            'step' => 'category',
            'cat_ids' => $ids,
        ]);

        $this->client->sendText($instance, $phone, implode("\n", $lines));
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
        if (WhatsappRentalCatalogHelper::shouldOfferBrandPicker($categoryId)) {
            $this->showBrandPicker($session, $instance, $phone, $categoryId, $catIds);

            return;
        }

        $this->showItemPage(
            $session,
            $instance,
            $phone,
            $categoryId,
            $catIds,
            WhatsappRentalCatalogHelper::BRAND_KEY_ALL,
            0,
            false
        );
    }

    /**
     * @param  array<int>  $catIds
     */
    private function showBrandPicker(
        WhatsappSession $session,
        string $instance,
        string $phone,
        int $categoryId,
        array $catIds
    ): void {
        $rows = WhatsappRentalCatalogHelper::brandMenuRows($categoryId);
        if ($rows === []) {
            $this->showItemPage(
                $session,
                $instance,
                $phone,
                $categoryId,
                $catIds,
                WhatsappRentalCatalogHelper::BRAND_KEY_ALL,
                0,
                false
            );

            return;
        }

        $keys = [];
        $lines = ["*Pick a brand / filter* (number):\n"];
        $i = 1;
        foreach ($rows as $row) {
            $keys[] = $row['key'];
            $lines[] = "*{$i}.* {$row['label']}";
            $i++;
        }
        $lines[] = "\n*BACK* — categories";

        $this->saveContext($session, [
            'step' => 'brand',
            'cat_ids' => $catIds,
            'category_id' => $categoryId,
            'brand_pick_keys' => $keys,
            'brand_was_prompted' => true,
        ]);

        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function pickBrand(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $text,
        array $ctx
    ): void {
        $keys = isset($ctx['brand_pick_keys']) && is_array($ctx['brand_pick_keys']) ? $ctx['brand_pick_keys'] : [];
        $idx = (int) preg_replace('/\D+/', '', $text);
        if ($idx < 1 || $idx > count($keys)) {
            $this->client->sendText($instance, $phone, 'Reply with a number from the brand list.');

            return;
        }

        $brandKey = (string) $keys[$idx - 1];
        $categoryId = (int) ($ctx['category_id'] ?? 0);
        $catIds = isset($ctx['cat_ids']) && is_array($ctx['cat_ids']) ? $ctx['cat_ids'] : [];
        $this->showItemPage($session, $instance, $phone, $categoryId, $catIds, $brandKey, 0, true);
    }

    /**
     * @param  array<int>  $catIds
     */
    private function showItemPage(
        WhatsappSession $session,
        string $instance,
        string $phone,
        int $categoryId,
        array $catIds,
        string $brandKey,
        int $itemPage,
        bool $brandWasPrompted
    ): void {
        $pack = WhatsappRentalCatalogHelper::paginatedItems($categoryId, $brandKey, $itemPage);
        /** @var \Illuminate\Support\Collection<int, RentalItem> $items */
        $items = $pack['items'];
        $total = (int) $pack['total'];
        $page = (int) $pack['page'];
        $totalPages = (int) $pack['total_pages'];

        if ($total === 0) {
            $this->client->sendText(
                $instance,
                $phone,
                'No items for that filter. Try *BACK* or pick another brand/category.'
            );

            return;
        }

        $lines = ['*Pick an item* (number) — page '.($page + 1)." of {$totalPages} ({$total} items)\n"];
        $ids = [];
        $n = 1;
        foreach ($items as $it) {
            $ids[] = (int) $it->id;
            $rate = $this->formatMoney((float) $it->daily_rate);
            $lines[] = "*{$n}.* {$it->name} — {$rate}/day";
            $n++;
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = '*PREV* — previous page';
        }
        if ($page < $totalPages - 1) {
            $nav[] = '*NEXT* — more items';
        }
        if ($nav !== []) {
            $lines[] = "\n".implode("\n  ", $nav);
        }
        $back = $brandWasPrompted ? '*BACK* — brand list' : '*BACK* — categories';
        $lines[] = "\n{$back}";

        $this->saveContext($session, array_merge($this->browsePersistKeys([
            'cat_ids' => $catIds,
            'category_id' => $categoryId,
            'brand_key' => $brandKey,
            'item_page' => $page,
            'brand_was_prompted' => $brandWasPrompted,
        ]), [
            'step' => 'item',
            'item_ids' => $ids,
        ]));

        $this->client->sendText($instance, $phone, implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function backFromItemStep(
        WhatsappSession $session,
        string $instance,
        string $phone,
        array $ctx
    ): void {
        $page = (int) ($ctx['item_page'] ?? 0);
        $categoryId = (int) ($ctx['category_id'] ?? 0);
        $catIds = isset($ctx['cat_ids']) && is_array($ctx['cat_ids']) ? $ctx['cat_ids'] : [];
        $brandKey = (string) ($ctx['brand_key'] ?? WhatsappRentalCatalogHelper::BRAND_KEY_ALL);
        $brandWasPrompted = (bool) ($ctx['brand_was_prompted'] ?? false);

        if ($page > 0) {
            $this->showItemPage($session, $instance, $phone, $categoryId, $catIds, $brandKey, $page - 1, $brandWasPrompted);

            return;
        }

        if ($brandWasPrompted && WhatsappRentalCatalogHelper::shouldOfferBrandPicker($categoryId)) {
            $this->showBrandPicker($session, $instance, $phone, $categoryId, $catIds);

            return;
        }

        $this->start($session->fresh(), $instance, $phone);
    }

    /**
     * Keys carried through qty → dates → summary so *BACK* restores the item list.
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function browsePersistKeys(array $ctx): array
    {
        return [
            'cat_ids' => $ctx['cat_ids'] ?? [],
            'category_id' => (int) ($ctx['category_id'] ?? 0),
            'brand_key' => (string) ($ctx['brand_key'] ?? WhatsappRentalCatalogHelper::BRAND_KEY_ALL),
            'item_page' => (int) ($ctx['item_page'] ?? 0),
            'brand_was_prompted' => (bool) ($ctx['brand_was_prompted'] ?? false),
        ];
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
        $nav = WhatsappMenuInputNormalizer::commandToken($text);
        $categoryId = (int) ($ctx['category_id'] ?? 0);
        $catIds = isset($ctx['cat_ids']) && is_array($ctx['cat_ids']) ? $ctx['cat_ids'] : [];
        $brandKey = (string) ($ctx['brand_key'] ?? WhatsappRentalCatalogHelper::BRAND_KEY_ALL);
        $page = (int) ($ctx['item_page'] ?? 0);
        $brandWasPrompted = (bool) ($ctx['brand_was_prompted'] ?? false);

        if (in_array($nav, ['NEXT', 'MORE'], true)) {
            $this->showItemPage($session, $instance, $phone, $categoryId, $catIds, $brandKey, $page + 1, $brandWasPrompted);

            return;
        }
        if (in_array($nav, ['PREV', 'PREVIOUS'], true)) {
            $this->showItemPage($session, $instance, $phone, $categoryId, $catIds, $brandKey, max(0, $page - 1), $brandWasPrompted);

            return;
        }

        $itemIds = isset($ctx['item_ids']) && is_array($ctx['item_ids']) ? $ctx['item_ids'] : [];
        $idx = (int) preg_replace('/\D+/', '', $text);
        if ($idx < 1 || $idx > count($itemIds)) {
            $this->client->sendText($instance, $phone, 'Reply with an item number, *NEXT*, or *PREV*.');

            return;
        }

        $itemId = (int) $itemIds[$idx - 1];
        $this->saveContext($session, array_merge($this->browsePersistKeys($ctx), [
            'step' => 'qty',
            'item_ids' => $itemIds,
            'item_id' => $itemId,
        ]));

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

        $this->saveContext($session, array_merge($this->browsePersistKeys($ctx), [
            'step' => 'dates',
            'item_ids' => $ctx['item_ids'] ?? [],
            'item_id' => $itemId,
            'qty' => $qty,
        ]));

        $this->client->sendText(
            $instance,
            $phone,
            "*Rental days*\n\n".
            "Send *start date* and *consecutive days*, e.g. *2026-04-15 3*\n\n".
            '*BACK* — change quantity'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function pickDates(
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

        $this->saveContext($session, array_merge($this->browsePersistKeys($ctx), [
            'step' => 'summary',
            'item_ids' => $ctx['item_ids'] ?? [],
            'item_id' => $itemId,
            'qty' => $qty,
            'dates' => $selected,
            'item_total' => $quote['item_total'],
            'caution' => $quote['caution'],
            'grand' => $quote['grand'],
            'label' => $quote['label'],
        ]));

        $rentals = $this->portalRentals();
        $grand = $this->formatMoney($quote['grand']);
        $rent = $this->formatMoney($quote['item_total']);
        $caution = $this->formatMoney($quote['caution']);

        $this->client->sendText(
            $instance,
            $phone,
            "*Browsing as guest*\n\n".
            "*{$quote['label']}*\n".
            "Qty: *{$qty}*\n".
            "Dates: *{$selected[0]}* → *{$selected[array_key_last($selected)]}* ({$daysCount} days)\n\n".
            "Rental: *{$rent}*\n".
            "Caution (if any): *{$caution}*\n".
            "*Estimated total:* {$grand}\n\n".
            "To *book and pay*, open:\n{$rentals}\n\n".
            "Or link this WhatsApp: send your account *email* here (same as on the rentals site).\n\n".
            '*BACK* — change dates  *RENTALS* — browse again'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function handleSummary(
        WhatsappSession $session,
        string $instance,
        string $phone,
        string $cmd,
        array $ctx
    ): void {
        if ($this->isGuestBrowseCommand($cmd)) {
            $this->start($session->fresh(), $instance, $phone);

            return;
        }

        $this->client->sendText(
            $instance,
            $phone,
            '*BACK* — edit dates  *RENTALS* — new browse  *MENU* — guest help'
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
}
