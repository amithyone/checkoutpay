<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletMoneyRequest;
use App\Models\WhatsappWalletMoneyRequestBlock;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\Consumer\ConsumerWalletTransferService;
use Illuminate\Support\Str;

class WhatsappWalletMoneyRequestService
{
    public function __construct(
        private ConsumerWalletTransferService $transfers,
        private ConsumerWalletPushNotificationService $consumerPush,
        private EvolutionWhatsAppClient $whatsappClient,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.money_request_enabled', true);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function create(
        WhatsappWallet $requesterWallet,
        string $payerPhoneInput,
        float $amount,
        ?string $note = null,
        string $channel = WhatsappWalletMoneyRequest::CHANNEL_CONSUMER_API,
    ): array {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Money requests are not available right now.'];
        }

        $requesterPhone = (string) $requesterWallet->phone_e164;
        $payerPhone = PhoneNormalizer::canonicalNgE164Digits($payerPhoneInput)
            ?? PhoneNormalizer::canonicalInternationalWalletRecipientDigits(
                PhoneNormalizer::digitsOnly($payerPhoneInput) ?? $payerPhoneInput
            );

        if ($payerPhone === null || $payerPhone === '') {
            return ['ok' => false, 'message' => 'Invalid phone number.'];
        }

        if ($payerPhone === $requesterPhone) {
            return ['ok' => false, 'message' => 'You cannot request money from yourself.'];
        }

        $payerWallet = WhatsappWallet::query()
            ->where('phone_e164', $payerPhone)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if ($payerWallet instanceof WhatsappWallet) {
            if ($payerWallet->isMoneyRequestPaused()) {
                return ['ok' => false, 'message' => 'This person is not accepting money requests right now.'];
            }

            if ($payerWallet->blocksMoneyRequestFrom($requesterPhone)) {
                return ['ok' => false, 'message' => 'You cannot send a money request to this person.'];
            }
        }

        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $maxPending = max(1, (int) config('consumer_wallet.money_request_max_pending_per_pair', 3));
        $pendingCount = WhatsappWalletMoneyRequest::query()
            ->where('requester_wallet_id', $requesterWallet->id)
            ->where('payer_phone_e164', $payerPhone)
            ->where('status', WhatsappWalletMoneyRequest::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($pendingCount >= $maxPending) {
            return ['ok' => false, 'message' => 'You already have pending requests to this number. Wait for a response or cancel one first.'];
        }

        $expiryDays = max(1, (int) config('consumer_wallet.money_request_expiry_days', 7));

        $request = WhatsappWalletMoneyRequest::query()->create([
            'public_id' => (string) Str::uuid(),
            'requester_wallet_id' => $requesterWallet->id,
            'requester_phone_e164' => $requesterPhone,
            'payer_phone_e164' => $payerPhone,
            'payer_wallet_id' => $payerWallet?->id,
            'amount' => round($amount, 2),
            'currency' => 'NGN',
            'note' => $note !== null && trim($note) !== '' ? Str::limit(trim($note), 140, '') : null,
            'status' => WhatsappWalletMoneyRequest::STATUS_PENDING,
            'channel' => $channel,
            'expires_at' => now()->addDays($expiryDays),
        ]);

        $payerDisplay = $this->displayNameForPhone($payerWallet, $payerPhone);
        $message = $this->buildCreateMessageForRequester(
            $requesterWallet,
            $payerWallet,
            $payerDisplay,
            (float) $request->amount,
        );

        $this->notifyPayerOfNewRequest($request->fresh(['requesterWallet']));

        return [
            'ok' => true,
            'message' => $message,
            'data' => $this->serializeRequest($request),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function accept(WhatsappWallet $payerWallet, string $publicId): array
    {
        $request = $this->findPendingForPayer($payerWallet, $publicId);
        if ($request === null) {
            return ['ok' => false, 'message' => 'Request not found or no longer pending.'];
        }

        $requesterPhone = (string) $request->requester_phone_e164;
        $amount = (float) $request->amount;

        $transfer = $this->transfers->p2p($payerWallet->fresh(), $requesterPhone, $amount);
        if (! ($transfer['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($transfer['message'] ?? 'Transfer failed.'),
                'data' => $transfer['data'] ?? null,
            ];
        }

        $debitTxnId = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $payerWallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_P2P_DEBIT)
            ->where('counterparty_phone_e164', $requesterPhone)
            ->where('amount', $amount)
            ->orderByDesc('id')
            ->value('id');

        $request->status = WhatsappWalletMoneyRequest::STATUS_ACCEPTED;
        $request->responded_at = now();
        $request->p2p_debit_transaction_id = $debitTxnId;
        $request->save();

        $this->notifyRequesterOfDeclineOrAccept($request->fresh(['requesterWallet']), accepted: true);

        return [
            'ok' => true,
            'message' => 'Request accepted. Money sent.',
            'data' => array_merge($this->serializeRequest($request), [
                'transfer' => $transfer['data'] ?? null,
            ]),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function decline(WhatsappWallet $payerWallet, string $publicId): array
    {
        $request = $this->findPendingForPayer($payerWallet, $publicId);
        if ($request === null) {
            return ['ok' => false, 'message' => 'Request not found or no longer pending.'];
        }

        $request->status = WhatsappWalletMoneyRequest::STATUS_DECLINED;
        $request->responded_at = now();
        $request->save();

        $this->notifyRequesterOfDeclineOrAccept($request->fresh(['requesterWallet']), accepted: false);

        return [
            'ok' => true,
            'message' => 'Request declined.',
            'data' => $this->serializeRequest($request),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function cancel(WhatsappWallet $requesterWallet, string $publicId): array
    {
        $request = WhatsappWalletMoneyRequest::query()
            ->where('public_id', $publicId)
            ->where('requester_wallet_id', $requesterWallet->id)
            ->first();

        if ($request === null || ! $request->isPending()) {
            return ['ok' => false, 'message' => 'Request not found or no longer pending.'];
        }

        $request->status = WhatsappWalletMoneyRequest::STATUS_CANCELLED;
        $request->responded_at = now();
        $request->save();

        return [
            'ok' => true,
            'message' => 'Request cancelled.',
            'data' => $this->serializeRequest($request),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForWallet(WhatsappWallet $wallet, string $direction = 'incoming'): array
    {
        $direction = strtolower(trim($direction));
        $query = WhatsappWalletMoneyRequest::query()->orderByDesc('id');

        if ($direction === 'outgoing') {
            $query->where('requester_wallet_id', $wallet->id);
        } else {
            $query->where('payer_phone_e164', (string) $wallet->phone_e164);
        }

        return $query->limit(50)->get()->map(fn (WhatsappWalletMoneyRequest $r) => $this->serializeRequest($r))->all();
    }

    public function findByPublicId(string $publicId): ?WhatsappWalletMoneyRequest
    {
        return WhatsappWalletMoneyRequest::query()->where('public_id', $publicId)->first();
    }

    /**
     * @return array{money_request_balance_hint_enabled: bool, money_request_paused: bool, money_request_paused_until: string|null}
     */
    public function serializeSettings(WhatsappWallet $wallet): array
    {
        return [
            'money_request_balance_hint_enabled' => (bool) ($wallet->money_request_balance_hint_enabled ?? true),
            'money_request_paused' => $wallet->isMoneyRequestPaused(),
            'money_request_paused_until' => $wallet->money_request_paused_until?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function updateSettings(WhatsappWallet $wallet, array $input): array
    {
        if (array_key_exists('money_request_balance_hint_enabled', $input)) {
            $wallet->money_request_balance_hint_enabled = (bool) $input['money_request_balance_hint_enabled'];
        }

        if (array_key_exists('money_request_pause_hours', $input)) {
            $hours = $input['money_request_pause_hours'];
            if ($hours === null || $hours === 0 || $hours === '0') {
                $wallet->money_request_paused_until = null;
            } elseif ((int) $hours < 0) {
                $wallet->money_request_paused_until = now()->addYears(50);
            } else {
                $wallet->money_request_paused_until = now()->addHours(max(1, (int) $hours));
            }
        } elseif (array_key_exists('money_request_paused', $input) && ! (bool) $input['money_request_paused']) {
            $wallet->money_request_paused_until = null;
        } elseif (array_key_exists('money_request_paused', $input) && (bool) $input['money_request_paused']) {
            $wallet->money_request_paused_until = now()->addYears(50);
        }

        $wallet->save();

        return [
            'ok' => true,
            'message' => 'Settings updated.',
            'data' => $this->serializeSettings($wallet->fresh()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBlocks(WhatsappWallet $wallet): array
    {
        return $wallet->moneyRequestBlocks()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WhatsappWalletMoneyRequestBlock $block) => $this->serializeBlock($block))
            ->all();
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function addBlock(WhatsappWallet $wallet, string $phoneInput): array
    {
        $blockedPhone = PhoneNormalizer::canonicalNgE164Digits($phoneInput)
            ?? PhoneNormalizer::canonicalInternationalWalletRecipientDigits(
                PhoneNormalizer::digitsOnly($phoneInput) ?? $phoneInput
            );

        if ($blockedPhone === null || $blockedPhone === '') {
            return ['ok' => false, 'message' => 'Invalid phone number.'];
        }

        if ($blockedPhone === (string) $wallet->phone_e164) {
            return ['ok' => false, 'message' => 'You cannot block yourself.'];
        }

        $blockedWallet = WhatsappWallet::query()
            ->where('phone_e164', $blockedPhone)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        $displayName = $this->displayNameForPhone($blockedWallet, $blockedPhone);

        $block = WhatsappWalletMoneyRequestBlock::query()->updateOrCreate(
            [
                'whatsapp_wallet_id' => $wallet->id,
                'blocked_phone_e164' => $blockedPhone,
            ],
            [
                'blocked_wallet_id' => $blockedWallet?->id,
                'blocked_display_name' => $displayName !== $blockedPhone ? $displayName : null,
            ],
        );

        return [
            'ok' => true,
            'message' => sprintf('%s can no longer request money from you.', $displayName),
            'data' => $this->serializeBlock($block),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removeBlock(WhatsappWallet $wallet, string $phoneInput): array
    {
        $blockedPhone = PhoneNormalizer::canonicalNgE164Digits($phoneInput)
            ?? PhoneNormalizer::canonicalInternationalWalletRecipientDigits(
                PhoneNormalizer::digitsOnly($phoneInput) ?? $phoneInput
            );

        if ($blockedPhone === null || $blockedPhone === '') {
            return ['ok' => false, 'message' => 'Invalid phone number.'];
        }

        $deleted = WhatsappWalletMoneyRequestBlock::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('blocked_phone_e164', $blockedPhone)
            ->delete();

        if ($deleted < 1) {
            return ['ok' => false, 'message' => 'Block not found.'];
        }

        return ['ok' => true, 'message' => 'Person unblocked.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBlock(WhatsappWalletMoneyRequestBlock $block): array
    {
        return [
            'phone_e164' => (string) $block->blocked_phone_e164,
            'display_name' => $block->blocked_display_name,
            'created_at' => $block->created_at?->toIso8601String(),
        ];
    }

    private function findPendingForPayer(WhatsappWallet $payerWallet, string $publicId): ?WhatsappWalletMoneyRequest
    {
        $request = WhatsappWalletMoneyRequest::query()
            ->where('public_id', $publicId)
            ->where('payer_phone_e164', (string) $payerWallet->phone_e164)
            ->first();

        if ($request === null || ! $request->isPending()) {
            return null;
        }

        return $request;
    }

    private function buildCreateMessageForRequester(
        WhatsappWallet $requesterWallet,
        ?WhatsappWallet $payerWallet,
        string $payerDisplay,
        float $amount,
    ): string {
        $amountLabel = WhatsappWalletMoneyFormatter::format($amount, 'NGN');

        if ($payerWallet !== null
            && $payerWallet->wantsMoneyRequestBalanceHint()
            && (float) $payerWallet->balance + 0.0001 < $amount) {
            return sprintf(
                '%s doesn\'t have %s in their wallet right now, but we\'ve sent your request — they can top up and accept.',
                $payerDisplay,
                $amountLabel,
            );
        }

        return sprintf('Request sent to %s. You\'ll be notified when they respond.', $payerDisplay);
    }

    private function displayNameForPhone(?WhatsappWallet $wallet, string $phoneE164): string
    {
        if ($wallet !== null) {
            $name = $wallet->displayName();

            return $name !== null && trim($name) !== '' ? trim($name) : $phoneE164;
        }

        return $phoneE164;
    }

    private function notifyPayerOfNewRequest(WhatsappWalletMoneyRequest $request): void
    {
        $requester = $request->requesterWallet;
        if (! $requester instanceof WhatsappWallet) {
            return;
        }

        $requesterName = $this->displayNameForPhone($requester, (string) $request->requester_phone_e164);
        $amountLabel = WhatsappWalletMoneyFormatter::format((float) $request->amount, (string) $request->currency);
        $title = 'Money request';
        $body = sprintf('%s requested %s from you.', $requesterName, $amountLabel);
        if ($request->note) {
            $body .= ' Note: '.$request->note;
        }

        $payerWallet = $request->payer_wallet_id
            ? WhatsappWallet::query()->find($request->payer_wallet_id)
            : WhatsappWallet::query()->where('phone_e164', $request->payer_phone_e164)->first();

        if ($payerWallet instanceof WhatsappWallet) {
            $this->consumerPush->notifyGeneric($payerWallet, $title, $body, [
                'type' => 'money_request',
                'money_request_id' => (string) $request->public_id,
                'amount' => (string) $request->amount,
            ]);
        }

        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            return;
        }

        $lines = [
            '💸 *Money request*',
            '',
            sprintf('%s requested *%s* from you.', $requesterName, $amountLabel),
        ];
        if ($request->note) {
            $lines[] = 'Note: '.$request->note;
        }
        $lines[] = '';
        $lines[] = 'Reply *ACCEPT '.$request->public_id.'* to pay (PIN required) or *DECLINE '.$request->public_id.'* to decline.';
        $lines[] = 'Or open CheckoutNow app → Requests.';

        $this->whatsappClient->sendText($instance, (string) $request->payer_phone_e164, implode("\n", $lines));
    }

    private function notifyRequesterOfDeclineOrAccept(WhatsappWalletMoneyRequest $request, bool $accepted): void
    {
        $requester = $request->requesterWallet;
        if (! $requester instanceof WhatsappWallet) {
            return;
        }

        $payerWallet = WhatsappWallet::query()->where('phone_e164', $request->payer_phone_e164)->first();
        $payerName = $this->displayNameForPhone($payerWallet, (string) $request->payer_phone_e164);
        $amountLabel = WhatsappWalletMoneyFormatter::format((float) $request->amount, (string) $request->currency);

        if ($accepted) {
            $title = 'Request accepted';
            $body = sprintf('%s accepted your request for %s.', $payerName, $amountLabel);
        } else {
            $title = 'Request declined';
            $body = sprintf('%s declined your request for %s.', $payerName, $amountLabel);
        }

        $this->consumerPush->notifyGeneric($requester, $title, $body, [
            'type' => $accepted ? 'money_request_accepted' : 'money_request_declined',
            'money_request_id' => (string) $request->public_id,
        ]);

        $instance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance === '') {
            return;
        }

        $this->whatsappClient->sendText(
            $instance,
            (string) $request->requester_phone_e164,
            $accepted
                ? sprintf('✅ %s accepted your money request for %s.', $payerName, $amountLabel)
                : sprintf('❌ %s declined your money request for %s.', $payerName, $amountLabel),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequest(WhatsappWalletMoneyRequest $request): array
    {
        $requester = $request->relationLoaded('requesterWallet')
            ? $request->requesterWallet
            : WhatsappWallet::query()->find($request->requester_wallet_id);

        $payerWallet = $request->payer_wallet_id
            ? ($request->relationLoaded('payerWallet') ? $request->payerWallet : WhatsappWallet::query()->find($request->payer_wallet_id))
            : WhatsappWallet::query()->where('phone_e164', $request->payer_phone_e164)->first();

        return [
            'id' => (string) $request->public_id,
            'status' => $request->status,
            'amount' => (float) $request->amount,
            'currency' => (string) $request->currency,
            'note' => $request->note,
            'channel' => $request->channel,
            'requester_phone_e164' => (string) $request->requester_phone_e164,
            'requester_display_name' => $requester ? $this->displayNameForPhone($requester, (string) $request->requester_phone_e164) : null,
            'payer_phone_e164' => (string) $request->payer_phone_e164,
            'payer_display_name' => $this->displayNameForPhone($payerWallet, (string) $request->payer_phone_e164),
            'expires_at' => $request->expires_at?->toIso8601String(),
            'responded_at' => $request->responded_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
