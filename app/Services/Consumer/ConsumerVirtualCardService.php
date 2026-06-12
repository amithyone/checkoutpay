<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPay\MevonPayCardApiClient;
use App\Services\MevonPay\MevonPayUsdAutoFundService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ConsumerVirtualCardService
{
    private array $transactionsCache = [];

    public function __construct(
        private MevonPayCardApiClient $cardApi,
        private VirtualCardFxService $fx,
        private ConsumerWalletPinVerifier $pinVerifier,
        private VirtualCardFeeRefundService $feeRefunds,
        private VirtualCardDebitRefundService $debitRefunds,
        private VirtualCardProviderResponseService $providerResponse,
        private MevonPayUsdAutoFundService $usdAutoFund,
        private VirtualCardRequestLogService $cardLogs,
        private VirtualCardRequestSupersedeService $supersede,
        private VirtualCardStoredDetailsService $storedDetails,
        private VirtualCardNotificationService $cardNotifier,
    ) {}

    public function isEnabled(): bool
    {
        $stored = Setting::get('virtual_card_enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('virtual_card.enabled', true);
    }

    public function creationFeeUsd(): float
    {
        $stored = Setting::get('virtual_card_creation_fee_usd');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        return max(0.0, round((float) config('virtual_card.creation_fee_usd', 2.5), 2));
    }

    public function initialLoadUsd(): float
    {
        $stored = Setting::get('virtual_card_initial_load_usd');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.01, round((float) $stored, 2));
        }

        return max(0.01, round((float) config('virtual_card.initial_load_usd', 5), 2));
    }

    /** Total USD charged to the user (creation + starting balance). */
    public function requestFeeUsd(): float
    {
        $total = round($this->creationFeeUsd() + $this->initialLoadUsd(), 2);

        return $total > 0 ? $total : max(0.0, (float) config('virtual_card.request_fee_usd', 7.5));
    }

    /** USD sent to Mevon `card_request` as initial card balance. */
    public function mevonInitialLoadUsd(): float
    {
        return $this->initialLoadUsd();
    }

    /** Total USD required in Mevon merchant float (creation + initial load). */
    public function mevonTotalCostUsd(): float
    {
        return $this->requestFeeUsd();
    }

    /**
     * @return array{creation_fee_usd: float, initial_load_usd: float, total_usd: float, total_ngn: ?float}
     */
    public function requestFeeBreakdown(): array
    {
        $creation = $this->creationFeeUsd();
        $load = $this->initialLoadUsd();
        $total = $this->requestFeeUsd();

        return [
            'creation_fee_usd' => $creation,
            'initial_load_usd' => $load,
            'total_usd' => $total,
            'total_ngn' => $this->fx->quoteRequestFeeNgn($total),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function status(WhatsappWallet $wallet, bool $forceRefresh = false): array
    {
        $autoFrozen = false;
        if ($forceRefresh) {
            $this->refreshProviderCardBalance($wallet);
        }
        $card = $this->resolveOperableCard($wallet);
        if ($card !== null && $forceRefresh) {
            $autoFrozen = $this->evaluateAutoFreezeForCard($card->fresh());
        }

        $data = $this->statusPayload($wallet);
        if ($autoFrozen) {
            $data['auto_frozen'] = true;
        }

        return [
            'ok' => true,
            'message' => $autoFrozen ? 'Card frozen after a declined payment.' : 'OK',
            'data' => $data,
        ];
    }

    public function refreshProviderCardBalance(WhatsappWallet $wallet): void
    {
        $card = $this->resolveDisplayCard($wallet);
        if ($card === null || ! $this->cardApi->isConfigured()) {
            return;
        }

        $this->backfillMevonRequestId($card->fresh());
        $this->backfillMevonCardCode($card->fresh());
        $this->syncProviderCardBalance($card->fresh());
    }

    /**
     * Wallet-side Dollar Virtual Card activity (request fee, fund, withdraw).
     *
     * @return array{ok: bool, message: string, data?: list<array<string, mixed>>, meta?: array<string, mixed>}
     */
    public function cardTransactions(WhatsappWallet $wallet, int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);

        $mevonFetch = $this->fetchMevonCardTransactions($wallet);
        $items = $mevonFetch['items'];
        $autoFrozen = $mevonFetch['auto_frozen'];

        $walletTxns = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereIn('type', [
                WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
                WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
                WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW,
            ])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        foreach ($walletTxns as $txn) {
            $items[] = array_merge($this->serializeCardTransaction($txn), ['source' => 'wallet']);
        }

        usort($items, function (array $a, array $b): int {
            $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

            return $tb <=> $ta;
        });

        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        return [
            'ok' => true,
            'message' => $autoFrozen ? 'Card frozen after a declined payment.' : 'OK',
            'data' => array_values(array_slice($items, $offset, $perPage)),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'auto_frozen' => $autoFrozen,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function prefill(WhatsappWallet $wallet): array
    {
        $payload = $this->statusPayload($wallet);
        unset($payload['latest_request']);

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => array_merge($payload, [
                'latest_request' => $this->latestRequestForWallet($wallet),
            ]),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function quote(WhatsappWallet $wallet, float $amountUsd, string $action): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Dollar Virtual Card is not available right now.'];
        }

        $quote = $this->fx->quoteForAction($amountUsd, $action);
        if ($quote === null) {
            return ['ok' => false, 'message' => 'Exchange rate is not configured.'];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => array_merge($quote, [
                'action' => $action === 'withdraw' || $action === 'buy' ? 'withdraw' : 'topup',
                'rate_used' => $quote['sell_rate'] ?? $quote['buy_rate'] ?? null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function requestCard(WhatsappWallet $wallet, array $input, string $pin): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Dollar Virtual Card is not available right now.'];
        }
        if (! filter_var($input['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => false, 'message' => 'Accept the Privacy Policy and Terms and Conditions to request a Dollar Virtual Card.'];
        }
        if (! $wallet->isTier2()) {
            return ['ok' => false, 'message' => 'Complete Tier 2 KYC on your profile before requesting a Dollar Virtual Card.'];
        }
        if (! $this->cardApi->isConfigured()) {
            return ['ok' => false, 'message' => 'Dollar Virtual Card service is not configured.'];
        }
        if (! $this->pinVerifier->verify($wallet, $pin)) {
            return ['ok' => false, 'message' => 'Invalid PIN.'];
        }

        $blocking = $this->blockingCardRequest($wallet);
        if ($blocking !== null) {
            if ($this->isOperableCardRequest($blocking)) {
                return [
                    'ok' => true,
                    'message' => 'You already have a Dollar Virtual Card on this wallet.',
                    'data' => array_merge($this->statusPayload($wallet), [
                        'already_has_card' => true,
                    ]),
                ];
            }

            $this->cardLogs->info('request_blocked', 'Duplicate card request blocked while another is in progress', $blocking, [
                'status' => $blocking->status,
            ], $wallet->id);

            return [
                'ok' => true,
                'message' => VirtualCardUserFacingMessage::requestAlreadyInProgress(),
                'data' => [
                    'request' => $this->serializeRequest($blocking),
                    'balance_after' => (float) $wallet->balance,
                    'preparing' => true,
                ],
            ];
        }

        $feeBreakdown = $this->requestFeeBreakdown();
        $feeUsd = $feeBreakdown['total_usd'];
        $feeNgn = $feeBreakdown['total_ngn'];
        $creationFeeUsd = $feeBreakdown['creation_fee_usd'];
        $initialLoadUsd = $feeBreakdown['initial_load_usd'];
        if ($feeNgn === null || $feeNgn < 0.01) {
            return [
                'ok' => false,
                'message' => 'USD/NGN sell rate is not configured. Ask admin to set virtual card FX rates.',
            ];
        }

        $fname = trim((string) ($input['first_name'] ?? $wallet->kyc_fname ?? ''));
        $lname = trim((string) ($input['last_name'] ?? $wallet->kyc_lname ?? ''));
        $email = trim((string) ($input['email'] ?? $wallet->kyc_email ?? ''));
        $dob = trim((string) ($input['dob'] ?? ($wallet->kyc_dob?->format('Y-m-d') ?? '')));
        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11((string) ($input['phone_number'] ?? $wallet->phone_e164));
        $homeNumber = trim((string) ($input['home_number'] ?? $wallet->card_home_number ?? ''));
        $homeAddress = trim((string) ($input['home_address'] ?? $wallet->card_home_address ?? ''));
        $cardName = trim((string) ($input['card_name'] ?? trim($fname.' '.$lname)));

        if ($fname === '' || $lname === '' || $email === '' || $dob === '' || $phone11 === null) {
            return ['ok' => false, 'message' => 'Missing required profile details. Complete KYC or fill the form.'];
        }
        if ($homeNumber === '' || $homeAddress === '' || $cardName === '') {
            return ['ok' => false, 'message' => 'Home address, home number, and card name are required.'];
        }

        $sellRate = $this->fx->sellRate();
        $reference = 'VCARD-'.strtoupper(Str::random(14));

        try {
            DB::transaction(function () use ($wallet, $feeNgn, $reference, $feeUsd, $sellRate, $creationFeeUsd, $initialLoadUsd) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($feeNgn);
                if (! $w->hasPin()) {
                    throw new \RuntimeException('invalid_pin');
                }
                if (! $check['ok']) {
                    throw new \RuntimeException('cannot_debit:'.(string) ($check['message'] ?? 'Insufficient balance.'));
                }
                $newBal = round((float) $w->balance - $feeNgn, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $feeNgn, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
                    'amount' => $feeNgn,
                    'balance_after' => $newBal,
                    'external_reference' => $reference,
                    'meta' => [
                        'channel' => 'consumer_api',
                        'fee_usd' => $feeUsd,
                        'creation_fee_usd' => $creationFeeUsd,
                        'initial_load_usd' => $initialLoadUsd,
                        'mevon_amount_usd' => $initialLoadUsd,
                        'fx_mid_usd_ngn' => $this->fx->midUsdNgnRate(),
                        'sell_rate' => $sellRate,
                        'fx_side' => 'sell',
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('consumer.virtual_card.debit_failed', ['wallet_id' => $wallet->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $this->debitFailureMessage($e, 'Could not debit wallet for card fee. Check balance and limits.')];
        }

        $payload = [
            'amount' => $initialLoadUsd,
            'firstName' => $fname,
            'lastName' => $lname,
            'email' => $email,
            'phoneNumber' => $phone11,
            'dob' => $dob,
            'homeNumber' => $homeNumber,
            'homeAddress' => $homeAddress,
            'cardName' => $cardName,
        ];

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PENDING,
            'fee_usd' => $feeUsd,
            'fee_ngn' => $feeNgn,
            'fx_rate_used' => $sellRate,
            'external_reference' => $reference,
            'card_name' => $cardName,
            'home_number' => $homeNumber,
            'home_address' => $homeAddress,
            'request_payload' => $payload,
        ]);

        $this->cardLogs->info('fee_debited', 'Card request fee debited from wallet; awaiting MevonPay response', $row, [
            'fee_ngn' => $feeNgn,
            'fee_usd' => $feeUsd,
            'reference' => $reference,
        ], $wallet->id);

        $fund = $this->usdAutoFund->ensureUsdBalance($this->mevonTotalCostUsd(), 'virtual_card_request');
        if (! ($fund['ok'] ?? false)) {
            $internalReason = (string) ($fund['message'] ?? 'USD auto-fund failed');
            Log::warning('consumer.virtual_card.usd_auto_fund_failed', [
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'reason' => $internalReason,
            ]);
            $this->feeRefunds->refundFee($wallet->id, $reference, $feeNgn, $internalReason);
            $this->providerResponse->applyFailure($row, ['message' => $internalReason], $internalReason);
            $this->cardLogs->warning('fee_refunded', 'Fee refunded after USD auto-fund failed', $row->fresh(), [
                'reason' => $internalReason,
            ], $wallet->id);

            return [
                'ok' => false,
                'message' => VirtualCardUserFacingMessage::requestFailedRefunded(),
                'data' => [
                    'balance_after' => (float) $wallet->fresh()->balance,
                ],
            ];
        }

        $feeTxn = $this->feeRefunds->findFeeTransaction($wallet->id, $reference);
        if (! $feeTxn) {
            Log::error('consumer.virtual_card.fee_txn_missing_before_provider', [
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'virtual_card_request_id' => $row->id,
            ]);
            $internalReason = 'Card fee transaction missing';
            $this->providerResponse->applyFailure($row, ['message' => $internalReason], $internalReason);

            return ['ok' => false, 'message' => 'Could not complete card request. Contact support.'];
        }

        if ($this->feeRefunds->isFeeRefunded($feeTxn)) {
            Log::error('consumer.virtual_card.fee_already_refunded_before_provider', [
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'virtual_card_request_id' => $row->id,
            ]);
            $internalReason = 'Card fee was already refunded for this request';
            $this->providerResponse->applyFailure($row, ['message' => $internalReason], $internalReason);

            return ['ok' => false, 'message' => VirtualCardUserFacingMessage::requestFailedRefunded()];
        }

        $this->cardLogs->info('provider_request_sent', 'Outbound MevonPay card_request payload', $row, $this->cardLogs->withMevonApiRequest($payload, [
            'reference' => $reference,
        ]), $wallet->id);

        $api = $this->cardApi->createCard($payload);
        if (! ($api['ok'] ?? false) && $this->usdAutoFund->isInsufficientUsdError((string) ($api['message'] ?? ''))) {
            Log::warning('consumer.virtual_card.provider_insufficient_usd', [
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'fee_usd' => $feeUsd,
                'provider_message' => (string) ($api['message'] ?? ''),
            ]);
            $retryFund = $this->usdAutoFund->fundAfterProviderInsufficientUsd($this->mevonTotalCostUsd(), 'virtual_card_request_retry');
            if ($retryFund['ok'] ?? false) {
                $api = $this->cardApi->createCard($payload);
            }
        }

        $wallet->card_home_number = $homeNumber;
        $wallet->card_home_address = $homeAddress;
        $wallet->save();

        if ($this->providerResponse->isCreateAccepted($api)) {
            $this->providerResponse->applyAccepted($row, $api);
            $fresh = $row->fresh();
            if ($this->isOperableCardRequest($fresh)) {
                $this->supersede->supersedeStaleAttempts($fresh);
            }
            $preparing = $fresh->status === VirtualCardRequest::STATUS_PREPARING;

            $this->cardLogs->info(
                $preparing ? 'fee_held_awaiting_webhook' : 'provider_submitted',
                $preparing
                    ? 'Mevon accepted card request; fee held until card.created webhook'
                    : 'Mevon returned card_id immediately',
                $fresh,
                $this->cardLogs->withMevonApiResponse($api, [
                    'provider_message' => (string) ($api['message'] ?? ''),
                    'provider_reference' => $fresh->provider_reference,
                    'card_external_id' => $fresh->card_external_id,
                ]),
                $wallet->id,
            );

            return [
                'ok' => true,
                'message' => $preparing
                    ? VirtualCardUserFacingMessage::cardPreparing()
                    : (string) ($api['message'] ?? 'Dollar Virtual Card request submitted.'),
                'data' => [
                    'request' => $this->serializeRequest($fresh),
                    'balance_after' => (float) $wallet->fresh()->balance,
                    'preparing' => $preparing,
                ],
            ];
        }

        $providerMessage = (string) ($api['message'] ?? 'Card provider error');
        $this->feeRefunds->refundFee($wallet->id, $reference, $feeNgn, $providerMessage);
        $this->providerResponse->applyFailure($row, $api, $providerMessage);
        $this->cardLogs->error('provider_failed', 'MevonPay rejected card request; fee refunded', $row->fresh(), $this->cardLogs->withMevonApiResponse($api, [
            'provider_message' => $providerMessage,
        ]), $wallet->id);

        return [
            'ok' => false,
            'message' => VirtualCardUserFacingMessage::sanitizeProviderMessage(
                $providerMessage,
                VirtualCardUserFacingMessage::requestFailedRefunded(),
            ),
            'data' => [
                'balance_after' => (float) $wallet->fresh()->balance,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function topupCard(WhatsappWallet $wallet, string $pin, float $amountUsd): array
    {
        $gate = $this->gateOperableCard($wallet, $pin);
        if (! $gate['ok']) {
            return $gate;
        }

        $card = $gate['card'];
        $min = (float) config('virtual_card.topup_min_usd', 1);
        $max = (float) config('virtual_card.topup_max_usd', 500);
        if ($amountUsd < $min || $amountUsd > $max) {
            return ['ok' => false, 'message' => "Top-up amount must be between \${$min} and \${$max}."];
        }

        $quote = $this->fx->quoteTopupNgn($amountUsd);
        if ($quote === null) {
            return ['ok' => false, 'message' => 'Sell rate is not configured.'];
        }

        $amountNgn = $quote['amount_ngn'];
        $cardCode = $this->resolveProviderCardCode($card);
        if ($cardCode === null) {
            return ['ok' => false, 'message' => 'Could not resolve card for top-up.'];
        }

        $reference = 'VCARD-TOP-'.strtoupper(Str::random(12));

        try {
            DB::transaction(function () use ($wallet, $amountNgn, $reference, $amountUsd, $quote, $cardCode) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($amountNgn);
                if (! $w->hasPin()) {
                    throw new \RuntimeException('invalid_pin');
                }
                if (! $check['ok']) {
                    throw new \RuntimeException('cannot_debit:'.(string) ($check['message'] ?? 'Insufficient balance.'));
                }
                $newBal = round((float) $w->balance - $amountNgn, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amountNgn, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
                    'amount' => $amountNgn,
                    'balance_after' => $newBal,
                    'external_reference' => $reference,
                    'meta' => array_merge($quote, [
                        'channel' => 'consumer_api',
                        'card_code' => $cardCode,
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('consumer.virtual_card.topup_debit_failed', ['wallet_id' => $wallet->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $this->debitFailureMessage($e, 'Could not debit wallet for card top-up. Check balance and limits.')];
        }

        $api = $this->invokeCardTopupWithUsdRetry($amountUsd, $cardCode, $wallet->id, $reference);

        if ($api['ok'] ?? false) {
            $card->update([
                'last_operation_at' => now(),
                'last_operation_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
            ]);
            $this->applyCardBalanceAfterOperation($card, $amountUsd, $api, 'credit');
            $this->notifyWalletCardTransaction(
                $wallet,
                $card,
                'virtual_card_topup',
                'Fund card',
                $amountUsd,
                $amountNgn,
                'debit',
                $reference,
            );

            return [
                'ok' => true,
                'message' => (string) ($api['message'] ?? 'Card funded successfully.'),
                'data' => [
                    'amount_usd' => $amountUsd,
                    'amount_ngn' => $amountNgn,
                    'sell_rate' => $quote['sell_rate'],
                    'card_external_id' => $cardCode,
                    'balance_after' => (float) $wallet->fresh()->balance,
                    'request' => $this->serializeRequest($card->fresh()),
                ],
            ];
        }

        $providerMessage = (string) ($api['message'] ?? 'Provider top-up failed');
        $this->debitRefunds->refundDebit(
            $wallet->id,
            $reference,
            $amountNgn,
            $providerMessage,
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
        );

        return [
            'ok' => false,
            'message' => VirtualCardUserFacingMessage::sanitizeProviderMessage(
                $providerMessage,
                VirtualCardUserFacingMessage::topupFailedRefunded(),
            ),
            'data' => [
                'balance_after' => (float) $wallet->fresh()->balance,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function cardDetails(WhatsappWallet $wallet, string $pin): array
    {
        $gate = $this->gateOperableCard($wallet, $pin);
        if (! $gate['ok']) {
            return $gate;
        }

        $card = $gate['card'];
        $stored = $this->storedDetails->resolveForRequest($card);

        if ($stored === null) {
            $this->refreshProviderCardBalance($wallet);
            $card = $card->fresh();
            $stored = $this->storedDetails->resolveForRequest($card);
            if ($stored === null) {
                $stored = $this->fetchAndStoreProviderCardDetails($card);
            }
        }

        if ($stored === null) {
            return [
                'ok' => false,
                'message' => 'Card details are not available yet. Ask support to sync your card from Mevon logs.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => $this->normalizeCardDetails($stored, $card->fresh()),
        ];
    }

    public function syncStoredCardDetails(VirtualCardRequest $card): bool
    {
        $this->syncProviderCardCode($card);

        if ($this->storedDetails->resolveForRequest($card->fresh()) !== null) {
            return true;
        }

        return $this->fetchAndStoreProviderCardDetails($card->fresh()) !== null;
    }

    public function syncProviderCardCode(VirtualCardRequest $card): ?string
    {
        $card = $card->fresh();
        $this->repairCardExternalIdIfNeeded($card);
        $card = $card->fresh();

        return $this->resolveProviderCardCode($card);
    }

    public function repairCardExternalIdIfNeeded(VirtualCardRequest $card): ?string
    {
        $current = trim((string) ($card->card_external_id ?? ''));
        if ($this->isUsableMevonCardIdentifier($current)) {
            return $current;
        }

        $resolved = $this->resolveMevonCardIdentifier($card);
        if ($resolved === null) {
            return null;
        }

        if ($resolved !== $current) {
            $card->update(['card_external_id' => $resolved]);
            Log::info('consumer.virtual_card.external_id_repaired', [
                'virtual_card_request_id' => $card->id,
                'from' => $current !== '' ? $current : null,
                'to' => $resolved,
            ]);
        }

        return $resolved;
    }

    private function syncProviderCardBalance(VirtualCardRequest $card): void
    {
        $requestId = $this->resolveMevonBalanceRequestId($card);
        if ($requestId !== null) {
            $api = $this->cardApi->getCardBalance($requestId);
            if ($api['ok'] ?? false) {
                $balance = $this->storedDetails->extractBalanceFromProviderPayload($api['raw'] ?? $api);
                if ($balance !== null) {
                    $this->updateReconciledBalance($card, $balance);

                    return;
                }
            }

            Log::info('consumer.virtual_card.card_balance_api_miss', [
                'virtual_card_request_id' => $card->id,
                'request_id' => $requestId,
                'message' => (string) ($api['message'] ?? 'unknown'),
            ]);
        }

        $this->fetchAndStoreProviderCardDetails($card);
    }

    /**
     * @return array{items: list<array<string, mixed>>, auto_frozen: bool}
     */
    public function fetchMevonCardTransactions(WhatsappWallet $wallet): array
    {
        $empty = ['items' => [], 'auto_frozen' => false];

        $card = $this->resolveOperableCard($wallet);
        if ($card === null || ! $this->cardApi->isConfigured()) {
            return $empty;
        }

        $cardCode = $this->backfillMevonCardCode($card);
        if ($cardCode === null) {
            return $empty;
        }

        $this->syncProviderCardBalance($card);
        $card = $card->fresh();

        $api = $this->getCardTransactionsCached($cardCode);
        if (! ($api['ok'] ?? false)) {
            Log::warning('consumer.virtual_card.transactions_mevon_failed', [
                'virtual_card_request_id' => $card->id,
                'card_code' => $cardCode,
                'message' => (string) ($api['message'] ?? 'unknown'),
            ]);

            return $empty;
        }

        $rows = $this->normalizeMevonTransactionList($api['data'] ?? null);
        $wallet = $card->wallet;
        if ($wallet) {
            $this->maybeNotifyNewMevonTransactions($card, $wallet, $rows);
            $card = $card->fresh();
        }
        $autoFrozen = $this->maybeAutoFreezeOnDeclinedTransaction($card->fresh(), $rows);
        $items = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = $this->serializeMevonCardTransaction($row, (int) $index);
        }

        return ['items' => $items, 'auto_frozen' => $autoFrozen];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function setAutoFreezeOnDecline(WhatsappWallet $wallet, bool $enabled): array
    {
        $card = $this->resolveOperableCard($wallet);
        if ($card === null) {
            return ['ok' => false, 'message' => 'No active card found.'];
        }

        $card->update(['auto_freeze_on_decline' => $enabled]);

        return [
            'ok' => true,
            'message' => $enabled
                ? 'Auto-freeze enabled. Your card will freeze after a declined payment.'
                : 'Auto-freeze disabled.',
            'data' => [
                'request' => $this->serializeRequest($card->fresh()),
            ],
        ];
    }

    private function evaluateAutoFreezeForCard(VirtualCardRequest $card): bool
    {
        if (! $this->cardApi->isConfigured()) {
            return false;
        }

        $cardCode = $this->backfillMevonCardCode($card);
        if ($cardCode === null) {
            return false;
        }

        $api = $this->getCardTransactionsCached($cardCode);
        if (! ($api['ok'] ?? false)) {
            return false;
        }

        $rows = $this->normalizeMevonTransactionList($api['data'] ?? null);

        return $this->maybeAutoFreezeOnDeclinedTransaction($card->fresh(), $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function maybeAutoFreezeOnDeclinedTransaction(VirtualCardRequest $card, array $rows): bool
    {
        if (! ($card->auto_freeze_on_decline ?? true) || $card->is_frozen) {
            return false;
        }

        $sorted = array_values(array_filter($rows, fn ($row) => is_array($row)));
        usort($sorted, function (array $a, array $b): int {
            $ta = strtotime((string) ($a['createdOn'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['createdOn'] ?? '')) ?: 0;

            return $tb <=> $ta;
        });

        $latestFailedRef = null;
        foreach ($sorted as $row) {
            if (! $this->isMevonDeclinedRow($row)) {
                continue;
            }
            $latestFailedRef = trim((string) ($row['code'] ?? $row['reference'] ?? ''));
            break;
        }

        if ($latestFailedRef === null || $latestFailedRef === '') {
            return false;
        }

        $payload = is_array($card->last_operation_payload) ? $card->last_operation_payload : [];
        if (($payload['auto_freeze_trigger_ref'] ?? '') === $latestFailedRef) {
            return false;
        }

        if (! $this->freezeCardWithoutPin($card->fresh())) {
            return false;
        }

        $fresh = $card->fresh();
        $operationPayload = is_array($fresh->last_operation_payload) ? $fresh->last_operation_payload : [];
        $fresh->update([
            'last_operation_payload' => array_merge($operationPayload, [
                'auto_freeze_trigger_ref' => $latestFailedRef,
                'auto_freeze_at' => now()->toIso8601String(),
                'auto_freeze_reason' => 'declined_transaction',
            ]),
        ]);

        Log::info('consumer.virtual_card.auto_frozen', [
            'virtual_card_request_id' => $fresh->id,
            'trigger_ref' => $latestFailedRef,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isMevonDeclinedRow(array $row): bool
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (in_array($status, ['failed', 'declined', 'failure', 'fail', 'unsuccessful', 'rejected'], true)) {
            return true;
        }

        $category = strtolower(trim((string) ($row['category'] ?? '')));

        return str_contains($category, 'declined') || str_contains($category, 'failed');
    }

    private function notifyWalletCardTransaction(
        WhatsappWallet $wallet,
        VirtualCardRequest $card,
        string $type,
        string $label,
        float $amountUsd,
        ?float $amountNgn,
        string $direction,
        string $reference,
    ): void {
        $this->cardNotifier->notifyTransaction($wallet, $card, [
            'type' => $type,
            'label' => $label,
            'amount_usd' => $amountUsd,
            'amount_ngn' => $amountNgn,
            'direction' => $direction,
            'status' => 'success',
            'reference' => $reference,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function maybeNotifyNewMevonTransactions(
        VirtualCardRequest $card,
        WhatsappWallet $wallet,
        array $rows,
    ): void {
        $payload = is_array($card->last_operation_payload) ? $card->last_operation_payload : [];
        $notified = is_array($payload['notified_transaction_refs'] ?? null)
            ? $payload['notified_transaction_refs']
            : [];
        $notifiedSet = array_flip(array_map('strval', $notified));

        $sorted = array_values(array_filter($rows, fn ($row) => is_array($row)));
        usort($sorted, function (array $a, array $b): int {
            $ta = strtotime((string) ($a['createdOn'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['createdOn'] ?? '')) ?: 0;

            return $tb <=> $ta;
        });

        $newRefs = [];
        foreach ($sorted as $row) {
            $ref = trim((string) ($row['code'] ?? $row['reference'] ?? ''));
            if ($ref === '' || isset($notifiedSet[$ref])) {
                continue;
            }

            $amount = is_numeric($row['amount'] ?? null) ? round((float) $row['amount'], 2) : null;
            $status = strtolower(trim((string) ($row['status'] ?? 'success')));
            $category = trim((string) ($row['category'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $label = $description !== '' ? $description : $this->mevonCategoryLabel($category);
            $drcr = strtoupper(trim((string) ($row['drcr'] ?? 'DR')));

            $this->cardNotifier->notifyTransaction($wallet, $card, [
                'type' => $this->mevonCategoryType($category),
                'label' => $label,
                'amount_usd' => $amount,
                'amount_ngn' => null,
                'direction' => $drcr === 'CR' ? 'credit' : 'debit',
                'status' => $status !== '' ? $status : 'success',
                'reference' => $ref,
                'created_at' => $this->normalizeMevonTimestamp($row['createdOn'] ?? null),
            ]);
            $newRefs[] = $ref;
        }

        if ($newRefs === []) {
            return;
        }

        $merged = array_slice(array_values(array_unique(array_merge($notified, $newRefs))), -500);
        $card->update([
            'last_operation_payload' => array_merge($payload, [
                'notified_transaction_refs' => $merged,
            ]),
        ]);
    }

    private function freezeCardWithoutPin(VirtualCardRequest $card): bool
    {
        $cardCode = $this->backfillMevonCardCode($card);
        if ($cardCode === null) {
            return false;
        }

        $api = $this->cardApi->setCardStatus('freeze', $cardCode);
        if (! ($api['ok'] ?? false)) {
            Log::warning('consumer.virtual_card.auto_freeze_failed', [
                'virtual_card_request_id' => $card->id,
                'card_code' => $cardCode,
                'message' => (string) ($api['message'] ?? 'unknown'),
            ]);

            return false;
        }

        $card->update([
            'is_frozen' => true,
            'last_operation_at' => now(),
            'last_operation_payload' => array_merge(
                is_array($card->last_operation_payload) ? $card->last_operation_payload : [],
                [
                    'auto_freeze_provider_response' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
                ]
            ),
        ]);

        return true;
    }

    private function resolveMevonBalanceRequestId(VirtualCardRequest $card): ?string
    {
        foreach ($this->mevonBalanceRequestIdCandidates($card) as $candidate) {
            if ($this->looksLikeMevonBalanceRequestId($candidate)) {
                return $candidate;
            }
        }

        $fromLogs = $this->resolveMevonBalanceRequestIdFromLogs($card);
        if ($fromLogs !== null) {
            if (trim((string) ($card->provider_reference ?? '')) === '') {
                $card->update(['provider_reference' => $fromLogs]);
            }

            return $fromLogs;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mevonBalanceRequestIdCandidates(VirtualCardRequest $card): array
    {
        $candidates = [];

        $ref = trim((string) ($card->provider_reference ?? ''));
        if ($ref !== '') {
            $candidates[] = $ref;
        }

        $fromLogs = $this->resolveMevonBalanceRequestIdFromLogs($card);
        if ($fromLogs !== null) {
            $candidates[] = $fromLogs;
        }

        $stored = is_array($card->card_details_payload) ? $card->card_details_payload : [];
        foreach ([
            $stored['provider_reference'] ?? null,
            $stored['request_id'] ?? null,
        ] as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $candidates[] = $text;
            }
        }

        $response = is_array($card->response_payload) ? $card->response_payload : [];
        $webhook = $response['webhook'] ?? null;
        if (is_array($webhook)) {
            $req = $this->providerResponse->extractMevonRequestId($webhook);
            if ($req !== null) {
                $candidates[] = $req;
            }
            $data = is_array($webhook['data'] ?? null) ? $webhook['data'] : $webhook;
            foreach (['request_id', 'requestId'] as $key) {
                $text = trim((string) ($data[$key] ?? ''));
                if ($text !== '') {
                    $candidates[] = $text;
                }
            }
        }

        $req = $this->extractMevonRequestIdFromPayload($response);
        if ($req !== null) {
            $candidates[] = $req;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Backfill REQ… on cards activated before provider_reference was persisted from webhooks.
     */
    public function backfillMevonRequestId(VirtualCardRequest $card): ?string
    {
        $existing = trim((string) ($card->provider_reference ?? ''));
        if ($this->looksLikeMevonBalanceRequestId($existing)) {
            return strtoupper($existing);
        }

        $resolved = $this->resolveMevonBalanceRequestId($card);
        if ($resolved === null) {
            return null;
        }

        if ($existing === '') {
            $card->update(['provider_reference' => $resolved]);
        }

        return $resolved;
    }

    /**
     * Backfill VCARD… code from webhook, stored details, or card_details (same path as live balance).
     */
    public function backfillMevonCardCode(VirtualCardRequest $card): ?string
    {
        $this->backfillMevonRequestId($card->fresh());
        $this->repairCardExternalIdIfNeeded($card->fresh());
        $card = $card->fresh();

        $resolved = $this->resolveMevonCardCode($card);
        if ($resolved !== null) {
            $stored = is_array($card->card_details_payload) ? $card->card_details_payload : null;
            $this->persistProviderCardCode($card, $resolved, $stored);

            return $resolved;
        }

        $code = $this->resolveProviderCardCode($card);
        if ($code !== null) {
            return $code;
        }

        $this->fetchAndStoreProviderCardDetails($card->fresh());

        return $this->resolveProviderCardCode($card->fresh());
    }

    private function resolveMevonCardCode(VirtualCardRequest $card): ?string
    {
        foreach ($this->mevonCardCodeCandidates($card) as $candidate) {
            if ($this->looksLikeMevonCardCode($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mevonCardCodeCandidates(VirtualCardRequest $card): array
    {
        $candidates = [];

        $stored = is_array($card->card_details_payload) ? $card->card_details_payload : [];
        $storedCode = trim((string) ($stored['card_code'] ?? ''));
        if ($storedCode !== '') {
            $candidates[] = $storedCode;
        }

        $externalId = trim((string) ($card->card_external_id ?? ''));
        if ($externalId !== '') {
            $candidates[] = $externalId;
        }

        $webhookCode = $this->cardCodeFromWebhookPayload($card);
        if ($webhookCode !== null) {
            $candidates[] = $webhookCode;
        }

        $fromLogs = $this->resolveMevonCardCodeFromLogs($card);
        if ($fromLogs !== null) {
            $candidates[] = $fromLogs;
        }

        return array_values(array_unique(array_filter($candidates, fn (string $value) => $value !== '')));
    }

    private function resolveMevonCardCodeFromLogs(VirtualCardRequest $card): ?string
    {
        $logs = VirtualCardRequestLog::query()
            ->where(function ($query) use ($card) {
                $query->where('virtual_card_request_id', $card->id)
                    ->orWhere('whatsapp_wallet_id', $card->whatsapp_wallet_id);
            })
            ->latest('id')
            ->limit(150)
            ->get();

        foreach ($logs as $log) {
            $context = is_array($log->context) ? $log->context : [];
            foreach ([$context['raw_payload'] ?? null, $context['response'] ?? null, $context] as $payload) {
                $code = $this->extractMevonCardCodeFromPayload($payload);
                if ($code !== null) {
                    return $code;
                }
            }

            $rawBody = trim((string) ($context['raw_body'] ?? ''));
            if ($rawBody !== '' && preg_match('/VCARD\d{6,}/i', $rawBody, $match) === 1) {
                return strtoupper($match[0]);
            }
        }

        $response = is_array($card->response_payload) ? $card->response_payload : [];

        return $this->extractMevonCardCodeFromPayload($response);
    }

    private function extractMevonCardCodeFromPayload(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $serialized = json_encode($payload);
        if (is_string($serialized) && preg_match('/VCARD\d{6,}/i', $serialized, $match) === 1) {
            return strtoupper($match[0]);
        }

        foreach ([$payload['data'] ?? null, $payload] as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['card_code', 'cardCode'] as $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($this->looksLikeMevonCardCode($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolveMevonBalanceRequestIdFromLogs(VirtualCardRequest $card): ?string
    {
        $logs = VirtualCardRequestLog::query()
            ->where(function ($query) use ($card) {
                $query->where('virtual_card_request_id', $card->id)
                    ->orWhere('whatsapp_wallet_id', $card->whatsapp_wallet_id);
            })
            ->latest('id')
            ->limit(150)
            ->get();

        foreach ($logs as $log) {
            $context = is_array($log->context) ? $log->context : [];
            foreach ([$context['raw_payload'] ?? null, $context['response'] ?? null, $context] as $payload) {
                $req = $this->extractMevonRequestIdFromPayload($payload);
                if ($req !== null) {
                    return $req;
                }
            }

            $rawBody = trim((string) ($context['raw_body'] ?? ''));
            if ($rawBody !== '' && preg_match('/REQ\d{6,}/i', $rawBody, $match) === 1) {
                return strtoupper($match[0]);
            }
        }

        $response = is_array($card->response_payload) ? $card->response_payload : [];
        $req = $this->extractMevonRequestIdFromPayload($response);
        if ($req !== null) {
            return $req;
        }

        return null;
    }

    private function extractMevonRequestIdFromPayload(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $serialized = json_encode($payload);
        if (is_string($serialized) && preg_match('/REQ\d{6,}/i', $serialized, $match) === 1) {
            return strtoupper($match[0]);
        }

        foreach ([$payload['data'] ?? null, $payload] as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['request_id', 'requestId', 'reference'] as $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($this->looksLikeMevonBalanceRequestId($value)) {
                    return strtoupper($value);
                }
            }
        }

        return null;
    }

    private function looksLikeMevonBalanceRequestId(string $value): bool
    {
        return preg_match('/^REQ\d{6,}$/i', trim($value)) === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeMevonTransactionList(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if ($this->isListArray($data)) {
            return array_values(array_filter($data, fn ($row) => is_array($row)));
        }

        foreach (['transactions', 'items', 'records', 'history'] as $key) {
            $nested = $data[$key] ?? null;
            if (is_array($nested) && $this->isListArray($nested)) {
                return array_values(array_filter($nested, fn ($row) => is_array($row)));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function serializeMevonCardTransaction(array $row, int $index): array
    {
        $drcr = strtoupper(trim((string) ($row['drcr'] ?? 'DR')));
        $amount = is_numeric($row['amount'] ?? null) ? round((float) $row['amount'], 2) : 0.0;
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $category = trim((string) ($row['category'] ?? ''));
        $description = trim((string) ($row['description'] ?? ''));
        $reference = trim((string) ($row['reference'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));

        return [
            'id' => $code !== '' ? 'mevon:'.$code : 'mevon:'.$reference.':'.$index,
            'source' => 'mevon',
            'type' => $this->mevonCategoryType($category),
            'label' => $description !== '' ? $description : $this->mevonCategoryLabel($category),
            'direction' => $drcr === 'CR' ? 'credit' : 'debit',
            'amount_ngn' => null,
            'amount_usd' => $amount > 0 ? $amount : null,
            'amount_display' => trim((string) ($row['amountInfo'] ?? '')) ?: null,
            'status' => $status !== '' ? $status : null,
            'category' => $category !== '' ? $category : null,
            'description' => $description !== '' ? $description : null,
            'reference' => $reference !== '' ? $reference : null,
            'fee_usd' => is_numeric($row['fee'] ?? null) ? round((float) $row['fee'], 2) : null,
            'customer_name' => trim((string) ($row['customerName'] ?? '')) ?: null,
            'balance_after' => null,
            'external_reference' => $reference !== '' ? $reference : null,
            'created_at' => $this->normalizeMevonTimestamp($row['createdOn'] ?? null),
        ];
    }

    private function mevonCategoryType(string $category): string
    {
        $normalized = strtolower(trim($category));

        return match (true) {
            str_contains($normalized, 'declined') => 'card_declined',
            str_contains($normalized, 'reversed') => 'card_reversed',
            str_contains($normalized, 'card_withdrawal') => 'card_withdrawal',
            str_contains($normalized, 'withdraw card') => 'card_spend',
            default => 'card_spend',
        };
    }

    private function mevonCategoryLabel(string $category): string
    {
        $normalized = strtolower(trim($category));

        return match (true) {
            str_contains($normalized, 'declined') => 'Declined charge',
            str_contains($normalized, 'reversed') => 'Reversal',
            str_contains($normalized, 'card_withdrawal') => 'Withdraw from card',
            str_contains($normalized, 'withdraw card') => 'Card spend',
            default => 'Card spend',
        };
    }

    private function normalizeMevonTimestamp(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($text)->toIso8601String();
        } catch (\Throwable) {
            return $text;
        }
    }

    /**
     * @param  array<mixed>  $data
     */
    private function isListArray(array $data): bool
    {
        if ($data === []) {
            return true;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAndStoreProviderCardDetails(VirtualCardRequest $card): ?array
    {
        $this->repairCardExternalIdIfNeeded($card);
        $cardId = $this->resolveMevonCardIdentifier($card->fresh());
        if ($cardId === null || ! $this->cardApi->isConfigured()) {
            return null;
        }

        $api = $this->cardApi->getCardDetails($cardId);
        if (! ($api['ok'] ?? false)) {
            Log::info('consumer.virtual_card.details_provider_miss', [
                'virtual_card_request_id' => $card->id,
                'card_external_id' => $cardId,
                'message' => (string) ($api['message'] ?? 'unknown'),
            ]);

            return null;
        }

        $payload = is_array($api['raw'] ?? null) ? $api['raw'] : ['data' => $api['data'] ?? null];
        $this->storedDetails->persistFromWebhook($card, $payload);

        $data = is_array($api['data'] ?? null) ? $api['data'] : [];
        $cardCode = trim((string) ($data['card_code'] ?? ''));
        if ($cardCode !== '' && $this->looksLikeMevonCardCode($cardCode)) {
            $stored = is_array($card->card_details_payload) ? $card->card_details_payload : [];
            $this->persistProviderCardCode($card, $cardCode, $stored);
        }

        $balance = $this->storedDetails->extractBalanceFromProviderPayload($payload);
        if ($balance !== null) {
            $this->updateReconciledBalance($card, $balance);
        }

        return $this->storedDetails->resolveForRequest($card->fresh());
    }

    /**
     * @param  'freeze'|'unfreeze'  $action
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function setCardFrozen(WhatsappWallet $wallet, string $pin, string $action): array
    {
        if (! in_array($action, ['freeze', 'unfreeze'], true)) {
            return ['ok' => false, 'message' => 'Invalid card status action.'];
        }

        $gate = $this->gateOperableCard($wallet, $pin);
        if (! $gate['ok']) {
            return $gate;
        }

        $card = $gate['card'];
        if ($action === 'unfreeze') {
            $balance = $card->card_balance_usd !== null ? (float) $card->card_balance_usd : 0.0;
            if ($balance <= 4.0) {
                return [
                    'ok' => false,
                    'message' => 'Your card balance must be greater than $4 to unfreeze it.',
                ];
            }
        }

        $cardCode = $this->resolveProviderCardCode($card);
        if ($cardCode === null) {
            return ['ok' => false, 'message' => 'Could not resolve card for status update.'];
        }

        $api = $this->cardApi->setCardStatus($action, $cardCode);

        if (! ($api['ok'] ?? false)) {
            Log::warning('consumer.virtual_card.status_failed', [
                'virtual_card_request_id' => $card->id,
                'card_external_id' => $card->card_external_id,
                'card_code' => $cardCode,
                'action' => $action,
                'message' => (string) ($api['message'] ?? 'unknown'),
            ]);

            return [
                'ok' => false,
                'message' => (string) ($api['message'] ?? 'Could not update card status.'),
            ];
        }

        $card->update([
            'is_frozen' => $action === 'freeze',
            'last_operation_at' => now(),
            'last_operation_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
        ]);

        return [
            'ok' => true,
            'message' => (string) ($api['message'] ?? ($action === 'freeze' ? 'Card frozen.' : 'Card unfrozen.')),
            'data' => [
                'request' => $this->serializeRequest($card->fresh()),
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function withdrawFromCard(WhatsappWallet $wallet, string $pin, float $amountUsd, ?string $reason = null): array
    {
        $gate = $this->gateOperableCard($wallet, $pin);
        if (! $gate['ok']) {
            return $gate;
        }

        $card = $gate['card'];
        if ($card->is_frozen) {
            return ['ok' => false, 'message' => 'Card is frozen. Unfreeze it before withdrawing.'];
        }

        $min = (float) config('virtual_card.withdraw_min_usd', 1);
        $max = (float) config('virtual_card.withdraw_max_usd', 500);
        if ($amountUsd < $min || $amountUsd > $max) {
            return ['ok' => false, 'message' => "Withdraw amount must be between \${$min} and \${$max}."];
        }

        $quote = $this->fx->quoteWithdrawNgn($amountUsd);
        if ($quote === null) {
            return ['ok' => false, 'message' => 'Buy rate is not configured.'];
        }

        $amountNgn = $quote['amount_ngn'];
        $cardCode = $this->resolveProviderCardCode($card);
        if ($cardCode === null) {
            return ['ok' => false, 'message' => 'Could not resolve card for withdraw.'];
        }
        $reasonText = trim((string) ($reason ?? '')) ?: 'Withdrawal to Wallet';

        $api = $this->cardApi->withdrawFromCard($amountUsd, $cardCode, $reasonText);
        if (! ($api['ok'] ?? false)) {
            $providerMessage = (string) ($api['message'] ?? 'Card withdraw failed.');

            return [
                'ok' => false,
                'message' => VirtualCardUserFacingMessage::sanitizeProviderMessage(
                    $providerMessage,
                    'Card withdraw failed.',
                    treatInsufficientUsdAsInternal: false,
                ),
            ];
        }

        $reference = 'VCARD-WD-'.strtoupper(Str::random(12));

        try {
            DB::transaction(function () use ($wallet, $amountNgn, $reference, $amountUsd, $quote, $cardCode, $reasonText) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                if (! $w->canCredit($amountNgn)['ok']) {
                    throw new \RuntimeException('cannot_credit');
                }
                $newBal = round((float) $w->balance + $amountNgn, 2);
                $w->balance = $newBal;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW,
                    'amount' => $amountNgn,
                    'balance_after' => $newBal,
                    'external_reference' => $reference,
                    'meta' => array_merge($quote, [
                        'channel' => 'consumer_api',
                        'card_code' => $cardCode,
                        'reason' => $reasonText,
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('consumer.virtual_card.withdraw_credit_failed', [
                'wallet_id' => $wallet->id,
                'amount_usd' => $amountUsd,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Provider withdraw succeeded but wallet credit failed. Contact support with your reference.',
            ];
        }

        $card->update([
            'last_operation_at' => now(),
            'last_operation_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
        ]);
        $this->applyCardBalanceAfterOperation($card, $amountUsd, $api, 'debit');
        $this->notifyWalletCardTransaction(
            $wallet,
            $card,
            'virtual_card_withdraw',
            'Withdraw from card',
            $amountUsd,
            $amountNgn,
            'credit',
            $reference,
        );

        return [
            'ok' => true,
            'message' => (string) ($api['message'] ?? 'Withdrawal credited to wallet.'),
            'data' => [
                'amount_usd' => $amountUsd,
                'amount_ngn' => $amountNgn,
                'buy_rate' => $quote['buy_rate'],
                'card_external_id' => $cardCode,
                'balance_after' => (float) $wallet->fresh()->balance,
                'request' => $this->serializeRequest($card->fresh()),
            ],
        ];
    }

    /**
     * @param  array{ok?: bool, message?: string, data?: mixed, raw?: mixed}  $api
     */
    private function applyCardBalanceAfterOperation(
        VirtualCardRequest $card,
        float $amountUsd,
        array $api,
        string $direction,
    ): void {
        $fromResponse = $this->storedDetails->extractBalanceFromProviderPayload($api['raw'] ?? $api['data'] ?? $api);
        if ($fromResponse !== null) {
            $this->updateReconciledBalance($card, $fromResponse);

            return;
        }

        $before = $this->readStoredCardBalanceUsd($card->fresh());
        $this->fetchAndStoreProviderCardDetails($card);
        $afterFetch = $this->readStoredCardBalanceUsd($card->fresh());
        if ($afterFetch !== null && ($before === null || abs($afterFetch - $before) >= 0.01)) {
            $this->updateReconciledBalance($card, $afterFetch);

            return;
        }

        $current = $before ?? $afterFetch;
        if ($current === null) {
            return;
        }

        $next = $direction === 'credit'
            ? round($current + $amountUsd, 2)
            : max(0.0, round($current - $amountUsd, 2));
        $this->updateReconciledBalance($card, $next);
    }

    private function readStoredCardBalanceUsd(VirtualCardRequest $card): ?float
    {
        if ($card->card_balance_usd !== null) {
            return (float) $card->card_balance_usd;
        }

        $stored = is_array($card->card_details_payload) ? $card->card_details_payload : null;
        if ($stored !== null && is_numeric($stored['balance_usd'] ?? null)) {
            return (float) $stored['balance_usd'];
        }

        return null;
    }

    /**
     * @return array{ok: bool, message: string, card?: VirtualCardRequest}
     */
    private function gateOperableCard(WhatsappWallet $wallet, string $pin): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Dollar Virtual Card is not available right now.'];
        }
        if (! $wallet->isTier2()) {
            return ['ok' => false, 'message' => 'Complete Tier 2 KYC before managing your Dollar Virtual Card.'];
        }
        if (! $this->cardApi->isConfigured()) {
            return ['ok' => false, 'message' => 'Dollar Virtual Card service is not configured.'];
        }
        if (! $this->pinVerifier->verify($wallet, $pin)) {
            return ['ok' => false, 'message' => 'Invalid PIN.'];
        }

        $card = $this->resolveOperableCard($wallet);
        if (! $card) {
            return ['ok' => false, 'message' => 'No active virtual card found for this wallet.'];
        }

        return ['ok' => true, 'message' => 'OK', 'card' => $card];
    }

    private function debitFailureMessage(\Throwable $e, string $fallback): string
    {
        $error = $e->getMessage();
        if (str_starts_with($error, 'cannot_debit:')) {
            return substr($error, strlen('cannot_debit:'));
        }

        return $fallback;
    }

    /**
     * Call Mevon card_topup; auto-buy USD from NGN and retry when merchant float is low.
     *
     * @return array{ok?: bool, message?: string, raw?: mixed}
     */
    private function invokeCardTopupWithUsdRetry(float $amountUsd, string $cardCode, int $walletId, string $reference): array
    {
        $api = $this->cardApi->topupCard($amountUsd, $cardCode);
        $maxRetries = max(1, (int) config('virtual_card.topup_merchant_usd_retries', 2));

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($api['ok'] ?? false) {
                return $api;
            }

            $providerMessage = (string) ($api['message'] ?? '');
            if (! $this->usdAutoFund->isInsufficientUsdError($providerMessage)) {
                return $api;
            }

            $balanceSnap = app(\App\Services\MevonPay\MevonPayBalanceSnapshotService::class)->forDashboard();
            $walletUsd = $balanceSnap['usd_balance'] ?? null;
            Log::warning('consumer.virtual_card.provider_insufficient_usd', [
                'wallet_id' => $walletId,
                'reference' => $reference,
                'amount_usd' => $amountUsd,
                'card_code' => $cardCode,
                'attempt' => $attempt + 1,
                'provider_message' => $providerMessage,
                'usd_balance' => $walletUsd,
                'usd_ledger' => $balanceSnap['usd_ledger'] ?? null,
                'mevon_balance_covers_topup' => $walletUsd !== null && (float) $walletUsd >= $amountUsd,
            ]);

            $retryFund = $this->usdAutoFund->fundAfterProviderInsufficientUsd(
                $amountUsd,
                'virtual_card_topup_retry_'.($attempt + 1),
            );
            if (! ($retryFund['ok'] ?? false)) {
                return $api;
            }

            $api = $this->cardApi->topupCard($amountUsd, $cardCode);
        }

        return $api;
    }

    private function resolveOperableCard(WhatsappWallet $wallet): ?VirtualCardRequest
    {
        return $this->resolveDisplayCard($wallet);
    }

    private function resolveDisplayCard(WhatsappWallet $wallet): ?VirtualCardRequest
    {
        $operable = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('card_external_id')
            ->where('card_external_id', '!=', '')
            ->whereIn('status', [VirtualCardRequest::STATUS_SUBMITTED, VirtualCardRequest::STATUS_ACTIVE])
            ->latest('id')
            ->first();

        if ($operable) {
            return $operable;
        }

        $withCardId = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('card_external_id')
            ->where('card_external_id', '!=', '')
            ->latest('id')
            ->first();

        if ($withCardId) {
            return $withCardId;
        }

        return null;
    }

    private function cardScreenFor(?VirtualCardRequest $display, ?VirtualCardRequest $latest): string
    {
        if ($display !== null) {
            return 'manage';
        }

        if ($latest !== null && $this->isPreparingRequest($latest)) {
            return 'preparing';
        }

        return 'request';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestRequestForWallet(WhatsappWallet $wallet): ?array
    {
        $latest = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->latest('id')
            ->first();

        return $latest ? $this->serializeRequest($latest) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(WhatsappWallet $wallet): array
    {
        $feeBreakdown = $this->requestFeeBreakdown();

        $display = $this->resolveDisplayCard($wallet);
        $rawLatest = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->latest('id')
            ->first();
        $latest = $display ?? $rawLatest;
        $cardScreen = $this->cardScreenFor($display, $rawLatest);

        return array_merge($this->fx->ratesPayload(), $this->profileFieldsForWallet($wallet), [
            'enabled' => $this->isEnabled(),
            'is_tier2' => $wallet->isTier2(),
            'fee_usd' => $feeBreakdown['total_usd'],
            'fee_ngn' => $feeBreakdown['total_ngn'],
            'creation_fee_usd' => $feeBreakdown['creation_fee_usd'],
            'initial_load_usd' => $feeBreakdown['initial_load_usd'],
            'topup_min_usd' => (float) config('virtual_card.topup_min_usd', 1),
            'topup_max_usd' => (float) config('virtual_card.topup_max_usd', 500),
            'withdraw_min_usd' => (float) config('virtual_card.withdraw_min_usd', 1),
            'withdraw_max_usd' => (float) config('virtual_card.withdraw_max_usd', 500),
            'has_active_card' => $display !== null,
            'card_screen' => $cardScreen,
            'can_request_card' => $cardScreen === 'request',
            'card_preparing' => $cardScreen === 'preparing',
            'card_design_url' => $this->cardDesignUrl(),
            'operable_request' => $display ? $this->serializeRequest($display) : null,
            'latest_request' => $latest ? $this->serializeRequest($latest) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileFieldsForWallet(WhatsappWallet $wallet): array
    {
        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11((string) $wallet->phone_e164);
        $dob = $wallet->kyc_dob?->format('Y-m-d');

        return [
            'first_name' => $wallet->kyc_fname,
            'last_name' => $wallet->kyc_lname,
            'email' => $wallet->kyc_email,
            'phone_number' => $phone11,
            'dob' => $dob,
            'home_number' => $wallet->card_home_number,
            'home_address' => $wallet->card_home_address,
            'card_name' => trim(($wallet->kyc_fname ?? '').' '.($wallet->kyc_lname ?? '')) ?: $wallet->displayName(),
        ];
    }

    private function cardDesignUrl(): ?string
    {
        $path = Setting::get('virtual_card_design_image');
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return url('storage/'.ltrim($path, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCardDetails(mixed $payload, VirtualCardRequest $card): array
    {
        $row = is_array($payload) ? $payload : [];
        if (isset($row['data']) && is_array($row['data'])) {
            $row = array_merge($row, $row['data']);
        }
        if (isset($row['details']) && is_array($row['details'])) {
            $row = array_merge($row, $row['details']);
        }
        if (isset($row['card']) && is_array($row['card'])) {
            $row = array_merge($row, $row['card']);
        }

        $cardNumber = $this->pickCardDetailString($row, [
            'card_number', 'cardNumber', 'number', 'pan', 'card_pan',
        ]);
        $cvv = $this->pickCardDetailString($row, ['cvv', 'cvv2', 'security_code', 'cvc']);
        $expiryMonth = $this->pickCardDetailString($row, ['expiry_month', 'exp_month', 'expiration_month', 'expiryMonth']);
        $expiryYear = $this->pickCardDetailString($row, ['expiry_year', 'exp_year', 'expiration_year', 'expiryYear']);
        $expiry = $this->pickCardDetailString($row, ['expiry', 'expiry_date', 'expiration', 'exp_date', 'expiry_month_year', 'expiryMonthYear']);

        if ($expiry === '' && $expiryMonth !== '' && $expiryYear !== '') {
            $year = strlen($expiryYear) === 4 ? substr($expiryYear, -2) : $expiryYear;
            $expiry = str_pad($expiryMonth, 2, '0', STR_PAD_LEFT).'/'.$year;
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $expiry, $expiryMatch) === 1) {
            $expiry = str_pad($expiryMatch[1], 2, '0', STR_PAD_LEFT).'/'.substr($expiryMatch[2], -2);
        }

        $billingParts = $this->normalizeBillingParts(
            $row['billing'] ?? $row['billing_address'] ?? $row['address'] ?? null,
        );

        $lastFour = $cardNumber !== '' ? substr(preg_replace('/\D/', '', $cardNumber) ?? '', -4) : '';
        if ($lastFour === '' && isset($row['last_four'])) {
            $lastFour = (string) $row['last_four'];
        }
        if ($lastFour === '' && isset($row['last4'])) {
            $lastFour = (string) $row['last4'];
        }

        return [
            'card_number' => $cardNumber,
            'cvv' => $cvv,
            'expiry' => $expiry,
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear,
            'card_name' => trim((string) ($row['card_name'] ?? $row['name_on_card'] ?? $card->card_name ?? '')),
            'card_external_id' => (string) $card->card_external_id,
            'last_four' => $lastFour,
            'brand' => strtolower((string) ($row['brand'] ?? $row['card_brand'] ?? $row['scheme'] ?? 'visa')),
            'billing_address' => $billingParts['formatted'],
            'billing_street' => $billingParts['street'],
            'billing_city' => $billingParts['city'],
            'billing_state' => $billingParts['state'],
            'billing_zip' => $billingParts['zip'],
            'billing_country' => $billingParts['country'],
            'currency' => strtoupper((string) ($row['currency'] ?? 'USD')),
            'balance_usd' => ($card->card_balance_usd !== null ? (float) $card->card_balance_usd : null)
                ?? $this->storedDetails->extractBalanceFromProviderPayload($row)
                ?? (is_numeric($row['balance_usd'] ?? null) ? (float) $row['balance_usd'] : null),
            'reconciliation_pending' => (bool) $card->reconciliation_pending,
            'status' => (string) ($row['status'] ?? ($card->is_frozen ? 'frozen' : 'active')),
        ];
    }

    /**
     * @return array{street: string, city: string, state: string, zip: string, country: string, formatted: string}
     */
    private function normalizeBillingParts(mixed $billing): array
    {
        $empty = [
            'street' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'formatted' => '',
        ];

        if (is_string($billing)) {
            $text = trim($billing);
            if ($text === '') {
                return [
                    'street' => '3401 N. Miami, Ave. Ste 230',
                    'city' => 'Miami',
                    'state' => 'Florida',
                    'zip' => '33127',
                    'country' => 'United States',
                    'formatted' => '3401 N. Miami, Ave. Ste 230, Miami, Florida, 33127, United States',
                ];
            }

            $parts = array_map('trim', explode(',', $text));
            $count = count($parts);
            if ($count >= 3) {
                $country = $parts[$count - 1];
                $zip = '';
                $state = '';
                $city = '';
                
                if (preg_match('/\d/', $parts[$count - 2])) {
                    $zip = $parts[$count - 2];
                    $state = $parts[$count - 3] ?? '';
                    $city = $parts[$count - 4] ?? '';
                    $streetParts = array_slice($parts, 0, max(0, $count - 4));
                } else {
                    $state = $parts[$count - 2] ?? '';
                    $city = $parts[$count - 3] ?? '';
                    $streetParts = array_slice($parts, 0, max(0, $count - 3));
                }

                $street = implode(', ', $streetParts);

                if ($state === '') {
                    $state = 'Florida';
                }

                return [
                    'street' => $street,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                    'country' => $country,
                    'formatted' => $text,
                ];
            }

            $state = 'Florida';
            if (preg_match('/\b(Florida|FL)\b/i', $text)) {
                $state = 'Florida';
            }

            return array_merge($empty, [
                'state' => $state,
                'country' => 'United States',
                'formatted' => $text,
            ]);
        }

        if (! is_array($billing) || empty($billing)) {
            return [
                'street' => '3401 N. Miami, Ave. Ste 230',
                'city' => 'Miami',
                'state' => 'Florida',
                'zip' => '33127',
                'country' => 'United States',
                'formatted' => '3401 N. Miami, Ave. Ste 230, Miami, Florida, 33127, United States',
            ];
        }

        $line1 = trim((string) ($billing['address1'] ?? $billing['street'] ?? $billing['line1'] ?? ''));
        $line2 = trim((string) ($billing['address2'] ?? $billing['line2'] ?? ''));
        $street = trim($line1.($line2 !== '' ? ', '.$line2 : ''));
        $city = trim((string) ($billing['city'] ?? ''));
        $state = trim((string) ($billing['state'] ?? $billing['region'] ?? ''));
        if ($state === '') {
            $state = 'Florida';
        }
        $zip = trim((string) ($billing['zip_code'] ?? $billing['zip'] ?? $billing['postal_code'] ?? ''));
        $country = trim((string) ($billing['country'] ?? ''));

        if ($street === '' && $city === '') {
            return [
                'street' => '3401 N. Miami, Ave. Ste 230',
                'city' => 'Miami',
                'state' => 'Florida',
                'zip' => '33127',
                'country' => 'United States',
                'formatted' => '3401 N. Miami, Ave. Ste 230, Miami, Florida, 33127, United States',
            ];
        }

        $labeled = array_filter([
            $street !== '' ? $street : null,
            $city !== '' ? $city : null,
            $state !== '' ? $state : null,
            $zip !== '' ? $zip : null,
            $country !== '' ? $country : null,
        ]);

        return [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'country' => $country,
            'formatted' => implode(', ', $labeled),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function pickCardDetailString(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if (is_string($value) || is_numeric($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCardTransaction(WhatsappWalletTransaction $txn): array
    {
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $usd = (float) ($meta['amount_usd'] ?? $meta['fee_usd'] ?? 0);
        $refunded = (bool) ($meta['refunded'] ?? false);

        return [
            'id' => $txn->id,
            'type' => $txn->type,
            'label' => $this->cardTransactionLabel($txn->type),
            'direction' => match ($txn->type) {
                WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW => 'credit',
                default => 'debit',
            },
            'amount_ngn' => abs((float) $txn->amount),
            'amount_usd' => $usd > 0 ? round($usd, 2) : null,
            'balance_after' => $txn->balance_after !== null ? (float) $txn->balance_after : null,
            'external_reference' => $txn->external_reference,
            'sell_rate' => isset($meta['sell_rate']) ? (float) $meta['sell_rate'] : null,
            'buy_rate' => isset($meta['buy_rate']) ? (float) $meta['buy_rate'] : null,
            'refunded' => $refunded,
            'created_at' => $txn->created_at?->toIso8601String(),
        ];
    }

    private function cardTransactionLabel(string $type): string
    {
        return match ($type) {
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE => 'Card request fee',
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP => 'Fund card',
            WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW => 'Withdraw to wallet',
            default => 'Card activity',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRequest(VirtualCardRequest $row): array
    {
        $hasCard = trim((string) ($row->card_external_id ?? '')) !== '';
        $canManage = $hasCard;
        $status = $row->status;
        if ($hasCard && ! in_array($status, [VirtualCardRequest::STATUS_SUBMITTED, VirtualCardRequest::STATUS_ACTIVE], true)) {
            $status = VirtualCardRequest::STATUS_ACTIVE;
        }

        $lastFour = null;
        $stored = is_array($row->card_details_payload) ? $row->card_details_payload : null;
        if ($stored) {
            $lastFour = trim((string) ($stored['last_four'] ?? '')) ?: null;
        }

        return [
            'id' => $row->id,
            'status' => $status,
            'is_preparing' => $this->isPreparingRequest($row),
            'provider_reference' => $row->provider_reference,
            'fee_usd' => (float) $row->fee_usd,
            'fee_ngn' => (float) $row->fee_ngn,
            'fx_rate_used' => $row->fx_rate_used !== null ? (float) $row->fx_rate_used : null,
            'card_name' => $row->card_name,
            'card_external_id' => $row->card_external_id,
            'card_last_four' => $lastFour,
            'card_balance_usd' => $row->card_balance_usd !== null ? (float) $row->card_balance_usd : null,
            'reconciliation_pending' => (bool) $row->reconciliation_pending,
            'is_frozen' => (bool) $row->is_frozen,
            'auto_freeze_on_decline' => (bool) ($row->auto_freeze_on_decline ?? true),
            'can_manage' => $canManage,
            'failure_reason' => $row->failure_reason,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function blockingCardRequest(WhatsappWallet $wallet): ?VirtualCardRequest
    {
        return VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereIn('status', [
                VirtualCardRequest::STATUS_PENDING,
                VirtualCardRequest::STATUS_PREPARING,
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_ACTIVE,
            ])
            ->latest('id')
            ->first();
    }

    private function isOperableCardRequest(VirtualCardRequest $row): bool
    {
        return trim((string) ($row->card_external_id ?? '')) !== '';
    }

    private function isPreparingRequest(VirtualCardRequest $row): bool
    {
        if ($row->status === VirtualCardRequest::STATUS_PREPARING) {
            return true;
        }

        return in_array($row->status, [
            VirtualCardRequest::STATUS_PENDING,
            VirtualCardRequest::STATUS_SUBMITTED,
        ], true) && ! $row->card_external_id;
    }

    private function resolveProviderCardCode(VirtualCardRequest $card): ?string
    {
        $this->repairCardExternalIdIfNeeded($card);
        $card = $card->fresh();

        $stored = is_array($card->card_details_payload) ? $card->card_details_payload : null;

        $externalId = $this->resolveMevonCardIdentifier($card);
        if ($externalId === null) {
            return null;
        }

        if ($this->looksLikeMevonCardCode($externalId)) {
            return $externalId;
        }

        if (is_array($stored)) {
            $code = trim((string) ($stored['card_code'] ?? ''));
            if ($code !== '' && $this->looksLikeMevonCardCode($code)) {
                return $code;
            }
        }

        $webhookCode = $this->cardCodeFromWebhookPayload($card);
        if ($webhookCode !== null) {
            $this->persistProviderCardCode($card, $webhookCode, $stored);

            return $webhookCode;
        }

        $api = $this->cardApi->getCardDetails($externalId);
        if (! ($api['ok'] ?? false)) {
            Log::warning('consumer.virtual_card.card_code_lookup_failed', [
                'virtual_card_request_id' => $card->id,
                'card_external_id' => $externalId,
                'message' => (string) ($api['message'] ?? 'unknown'),
            ]);

            return null;
        }

        $data = is_array($api['data'] ?? null) ? $api['data'] : [];
        $code = trim((string) ($data['card_code'] ?? ''));
        if ($code === '' || ! $this->looksLikeMevonCardCode($code)) {
            return null;
        }

        $this->persistProviderCardCode($card, $code, $stored);

        return $code;
    }

    private function resolveMevonCardIdentifier(VirtualCardRequest $card): ?string
    {
        $candidates = [];

        $stored = is_array($card->card_details_payload) ? $card->card_details_payload : [];
        $candidates[] = $stored['card_external_id'] ?? null;
        $candidates[] = $card->card_external_id;

        $response = is_array($card->response_payload) ? $card->response_payload : [];
        $webhook = $response['webhook'] ?? null;
        if (is_array($webhook)) {
            $data = is_array($webhook['data'] ?? null) ? $webhook['data'] : $webhook;
            $candidates[] = $data['card_id'] ?? null;
            $candidates[] = $data['cardId'] ?? null;
            $candidates[] = $data['card_code'] ?? null;
            $candidates[] = $data['cardCode'] ?? null;
        }

        $candidates[] = $response['card_id'] ?? null;
        $candidates[] = $response['cardId'] ?? null;

        foreach ($candidates as $candidate) {
            $id = trim((string) $candidate);
            if ($this->isUsableMevonCardIdentifier($id)) {
                return $id;
            }
        }

        return $this->resolveMevonCardIdentifierFromLogs($card, $stored);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function resolveMevonCardIdentifierFromLogs(VirtualCardRequest $card, array $stored): ?string
    {
        $needles = array_values(array_filter([
            trim((string) ($stored['card_number'] ?? '')),
            trim((string) ($stored['last_four'] ?? '')),
            trim((string) ($stored['provider_reference'] ?? '')),
        ], fn (string $value) => $value !== ''));

        if ($needles === []) {
            return null;
        }

        $logs = VirtualCardRequestLog::query()
            ->where(function ($query) use ($card) {
                $query->where('virtual_card_request_id', $card->id)
                    ->orWhere('whatsapp_wallet_id', $card->whatsapp_wallet_id);
            })
            ->latest('id')
            ->limit(120)
            ->get();

        foreach ($logs as $log) {
            $id = $this->extractCardIdentifierFromLog($log, $needles);
            if ($id !== null) {
                return $id;
            }
        }

        foreach ($needles as $needle) {
            if (strlen($needle) < 4) {
                continue;
            }

            $orphanLogs = VirtualCardRequestLog::query()
                ->whereRaw('CAST(context AS CHAR) LIKE ?', ['%'.$needle.'%'])
                ->latest('id')
                ->limit(20)
                ->get();

            foreach ($orphanLogs as $log) {
                $id = $this->extractCardIdentifierFromLog($log, $needles);
                if ($id !== null) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function extractCardIdentifierFromLog(VirtualCardRequestLog $log, array $needles): ?string
    {
        $context = is_array($log->context) ? $log->context : [];
        $payload = $context['raw_payload'] ?? null;
        if (! is_array($payload)) {
            $rawBody = trim((string) ($context['raw_body'] ?? ''));
            if ($rawBody !== '') {
                $decoded = json_decode($rawBody, true);
                $payload = is_array($decoded) ? $decoded : null;
            }
        }

        if (! is_array($payload)) {
            return null;
        }

        $serialized = json_encode($payload);
        if (! is_string($serialized)) {
            return null;
        }

        $matchesNeedle = false;
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($serialized, $needle)) {
                $matchesNeedle = true;
                break;
            }
        }

        if (! $matchesNeedle) {
            return null;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        foreach ([
            $data['card_id'] ?? null,
            $data['cardId'] ?? null,
            $data['card_code'] ?? null,
            $data['cardCode'] ?? null,
            $payload['card_id'] ?? null,
            $payload['cardId'] ?? null,
        ] as $candidate) {
            $id = trim((string) $candidate);
            if ($this->isUsableMevonCardIdentifier($id)) {
                return $id;
            }
        }

        return null;
    }

    private function isUsableMevonCardIdentifier(string $id): bool
    {
        if ($id === '' || $this->isPlaceholderCardIdentifier($id)) {
            return false;
        }

        if ($this->looksLikeMevonCardCode($id)) {
            return true;
        }

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id,
        );
    }

    private function isPlaceholderCardIdentifier(string $id): bool
    {
        $normalized = strtolower(trim($id));

        return str_contains($id, '{')
            || str_contains($id, '}')
            || in_array($normalized, [
                'card_id',
                '{card_id}',
                'card_code',
                '{card_code}',
                'request_id_here',
                'request-id',
            ], true);
    }

    private function cardCodeFromWebhookPayload(VirtualCardRequest $card): ?string
    {
        $response = is_array($card->response_payload) ? $card->response_payload : [];
        $webhook = $response['webhook'] ?? null;
        if (! is_array($webhook)) {
            return null;
        }

        $data = is_array($webhook['data'] ?? null) ? $webhook['data'] : $webhook;
        $code = trim((string) ($data['card_code'] ?? $data['cardCode'] ?? ''));

        return $code !== '' && $this->looksLikeMevonCardCode($code) ? $code : null;
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    private function persistProviderCardCode(VirtualCardRequest $card, string $code, ?array $stored): void
    {
        $payload = is_array($stored) ? $stored : [];
        if (($payload['card_code'] ?? '') === $code) {
            return;
        }

        $payload['card_code'] = $code;
        $card->update(['card_details_payload' => $payload]);
    }

    private function looksLikeMevonCardCode(string $value): bool
    {
        return preg_match('/^VCARD/i', $value) === 1;
    }

    private function getCardTransactionsCached(string $cardCode): array
    {
        if (isset($this->transactionsCache[$cardCode])) {
            return $this->transactionsCache[$cardCode];
        }

        $api = $this->cardApi->getCardTransactions($cardCode);
        $this->transactionsCache[$cardCode] = $api;

        return $api;
    }

    private function isSuccessfulSpend(array $row): bool
    {
        if ($this->isMevonDeclinedRow($row)) {
            return false;
        }

        $category = strtolower(trim((string) ($row['category'] ?? '')));
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (str_contains($category, 'reversed') || str_contains($status, 'reversed')) {
            return false;
        }

        $drcr = strtoupper(trim((string) ($row['drcr'] ?? 'DR')));

        return $drcr === 'DR';
    }

    private function isSuccessfulReversal(array $row): bool
    {
        if ($this->isMevonDeclinedRow($row)) {
            return false;
        }

        $drcr = strtoupper(trim((string) ($row['drcr'] ?? 'DR')));
        if ($drcr !== 'CR') {
            return false;
        }

        $category = strtolower(trim((string) ($row['category'] ?? '')));
        if (str_contains($category, 'topup') || str_contains($category, 'load') || str_contains($category, 'fund')) {
            return false;
        }

        return true;
    }

    public function getReconciledBalance(VirtualCardRequest $card, float $providerBalance): float
    {
        $cardCode = $this->resolveProviderCardCode($card);
        if ($cardCode === null) {
            return $providerBalance;
        }

        $initialLoadUsd = 0.0;
        if (is_array($card->request_payload) && isset($card->request_payload['amount'])) {
            $initialLoadUsd = (float) $card->request_payload['amount'];
        } else {
            $feeTxn = WhatsappWalletTransaction::query()
                ->where('whatsapp_wallet_id', $card->whatsapp_wallet_id)
                ->where('external_reference', $card->external_reference)
                ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE)
                ->first();
            if ($feeTxn && is_array($feeTxn->meta) && isset($feeTxn->meta['initial_load_usd'])) {
                $initialLoadUsd = (float) $feeTxn->meta['initial_load_usd'];
            }
        }
        if ($initialLoadUsd <= 0) {
            $initialLoadUsd = $this->initialLoadUsd();
        }

        $topupTxns = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $card->whatsapp_wallet_id)
            ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP)
            ->get();

        $totalTopups = 0.0;
        foreach ($topupTxns as $txn) {
            $meta = is_array($txn->meta) ? $txn->meta : [];
            if (! empty($meta['refunded'])) {
                continue;
            }
            $txnCardCode = trim((string) ($meta['card_code'] ?? ''));
            if ($txnCardCode === '' || $txnCardCode === $cardCode) {
                $totalTopups += (float) ($meta['amount_usd'] ?? 0);
            }
        }

        $withdrawTxns = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $card->whatsapp_wallet_id)
            ->where('type', WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_WITHDRAW)
            ->get();

        $totalWithdrawals = 0.0;
        foreach ($withdrawTxns as $txn) {
            $meta = is_array($txn->meta) ? $txn->meta : [];
            if (! empty($meta['refunded'])) {
                continue;
            }
            $txnCardCode = trim((string) ($meta['card_code'] ?? ''));
            if ($txnCardCode === '' || $txnCardCode === $cardCode) {
                $totalWithdrawals += (float) ($meta['amount_usd'] ?? 0);
            }
        }

        $totalSpends = 0.0;
        $totalReversals = 0.0;

        $api = $this->getCardTransactionsCached($cardCode);
        if ($api['ok'] ?? false) {
            $rows = $this->normalizeMevonTransactionList($api['data'] ?? null);
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $amount = is_numeric($row['amount'] ?? null) ? round((float) $row['amount'], 2) : 0.0;
                $fee = is_numeric($row['fee'] ?? null) ? round((float) $row['fee'], 2) : 0.0;

                if ($this->isSuccessfulSpend($row)) {
                    $totalSpends += ($amount + $fee);
                } elseif ($this->isSuccessfulReversal($row)) {
                    $totalReversals += $amount;
                }
            }
        }

        if ($totalSpends === 0.0 && $totalReversals === 0.0) {
            return $providerBalance;
        }

        $calculated = round($initialLoadUsd + $totalTopups - $totalWithdrawals - $totalSpends + $totalReversals, 2);

        return max(0.0, $calculated);
    }

    public function updateReconciledBalance(VirtualCardRequest $card, float $providerBalance): void
    {
        $reconciled = $this->getReconciledBalance($card, $providerBalance);
        $finalBalance = min($providerBalance, $reconciled);
        $pending = $providerBalance > $reconciled;

        $card->update([
            'card_balance_usd' => $finalBalance,
            'reconciliation_pending' => $pending,
        ]);

        if ($finalBalance <= 0.0 && ! $card->is_frozen && trim((string) $card->card_external_id) !== '') {
            $cardCode = $this->resolveProviderCardCode($card);
            if ($cardCode !== null) {
                $api = $this->cardApi->setCardStatus('freeze', $cardCode);
                if ($api['ok'] ?? false) {
                    $card->update(['is_frozen' => true]);
                    $this->cardLogs->info('auto_freeze_zero_balance', 'Card auto-frozen because reconciled balance reached $0.', $card, [], $card->whatsapp_wallet_id);
                }
            }
        }
    }

    public function isReconciliationPending(VirtualCardRequest $card, ?float $providerBalance = null): bool
    {
        if ($providerBalance === null) {
            $providerBalance = $this->readStoredCardBalanceUsd($card);
        }
        if ($providerBalance === null) {
            return false;
        }

        $reconciled = $this->getReconciledBalance($card, $providerBalance);

        return $providerBalance > $reconciled;
    }
}
