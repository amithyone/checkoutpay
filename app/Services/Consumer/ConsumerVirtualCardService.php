<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\VirtualCardRequest;
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
    public function __construct(
        private MevonPayCardApiClient $cardApi,
        private VirtualCardFxService $fx,
        private ConsumerWalletPinVerifier $pinVerifier,
        private VirtualCardFeeRefundService $feeRefunds,
        private VirtualCardDebitRefundService $debitRefunds,
        private VirtualCardProviderResponseService $providerResponse,
        private MevonPayUsdAutoFundService $usdAutoFund,
        private VirtualCardRequestLogService $cardLogs,
    ) {}

    public function isEnabled(): bool
    {
        $stored = Setting::get('virtual_card_enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('virtual_card.enabled', true);
    }

    public function requestFeeUsd(): float
    {
        $stored = Setting::get('virtual_card_request_fee_usd');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, (float) $stored);
        }

        return max(0.0, (float) config('virtual_card.request_fee_usd', 5));
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function status(WhatsappWallet $wallet): array
    {
        return [
            'ok' => true,
            'message' => 'OK',
            'data' => $this->statusPayload($wallet),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function prefill(WhatsappWallet $wallet): array
    {
        $payload = $this->statusPayload($wallet);
        unset($payload['latest_request']);

        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11((string) $wallet->phone_e164);
        $dob = $wallet->kyc_dob?->format('Y-m-d');

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => array_merge($payload, [
                'first_name' => $wallet->kyc_fname,
                'last_name' => $wallet->kyc_lname,
                'email' => $wallet->kyc_email,
                'phone_number' => $phone11,
                'dob' => $dob,
                'home_number' => $wallet->card_home_number,
                'home_address' => $wallet->card_home_address,
                'card_name' => trim(($wallet->kyc_fname ?? '').' '.($wallet->kyc_lname ?? '')) ?: $wallet->displayName(),
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
                return ['ok' => false, 'message' => 'You already have a Dollar Virtual Card on this wallet.'];
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

        $feeUsd = $this->requestFeeUsd();
        $feeNgn = $this->fx->quoteRequestFeeNgn($feeUsd);
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
            DB::transaction(function () use ($wallet, $feeNgn, $reference, $feeUsd, $sellRate) {
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
            'amount' => $feeUsd,
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

        $fund = $this->usdAutoFund->ensureUsdBalance($feeUsd, 'virtual_card_request');
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
            $retryFund = $this->usdAutoFund->fundAfterProviderInsufficientUsd($feeUsd, 'virtual_card_request_retry');
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
        $cardCode = (string) $card->card_external_id;
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

        $fund = $this->usdAutoFund->ensureUsdBalance($amountUsd, 'virtual_card_topup');
        if (! ($fund['ok'] ?? false)) {
            $internalReason = (string) ($fund['message'] ?? 'USD auto-fund failed');
            Log::warning('consumer.virtual_card.usd_auto_fund_failed', [
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'reason' => $internalReason,
            ]);
            $this->debitRefunds->refundDebit(
                $wallet->id,
                $reference,
                $amountNgn,
                $internalReason,
                WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
            );

            return [
                'ok' => false,
                'message' => VirtualCardUserFacingMessage::topupFailedRefunded(),
                'data' => [
                    'balance_after' => (float) $wallet->fresh()->balance,
                ],
            ];
        }

        $api = $this->cardApi->topupCard($amountUsd, $cardCode);
        if (! ($api['ok'] ?? false) && $this->usdAutoFund->isInsufficientUsdError((string) ($api['message'] ?? ''))) {
            Log::warning('consumer.virtual_card.provider_insufficient_usd', [
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'amount_usd' => $amountUsd,
                'provider_message' => (string) ($api['message'] ?? ''),
            ]);
            $retryFund = $this->usdAutoFund->fundAfterProviderInsufficientUsd($amountUsd, 'virtual_card_topup_retry');
            if ($retryFund['ok'] ?? false) {
                $api = $this->cardApi->topupCard($amountUsd, $cardCode);
            }
        }

        if ($api['ok'] ?? false) {
            $card->update([
                'last_operation_at' => now(),
                'last_operation_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
            ]);

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
        $cardCode = (string) $card->card_external_id;
        $api = $this->cardApi->setCardStatus($action, $cardCode);

        if (! ($api['ok'] ?? false)) {
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
        $cardCode = (string) $card->card_external_id;
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

    private function resolveOperableCard(WhatsappWallet $wallet): ?VirtualCardRequest
    {
        return VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('card_external_id')
            ->whereIn('status', [VirtualCardRequest::STATUS_SUBMITTED, VirtualCardRequest::STATUS_ACTIVE])
            ->latest('id')
            ->first();
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
        $feeUsd = $this->requestFeeUsd();
        $feeNgn = $this->fx->quoteRequestFeeNgn($feeUsd);

        $operable = $this->resolveOperableCard($wallet);
        $latest = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->latest('id')
            ->first();

        return array_merge($this->fx->ratesPayload(), [
            'enabled' => $this->isEnabled(),
            'is_tier2' => $wallet->isTier2(),
            'fee_usd' => $feeUsd,
            'fee_ngn' => $feeNgn,
            'topup_min_usd' => (float) config('virtual_card.topup_min_usd', 1),
            'topup_max_usd' => (float) config('virtual_card.topup_max_usd', 500),
            'withdraw_min_usd' => (float) config('virtual_card.withdraw_min_usd', 1),
            'withdraw_max_usd' => (float) config('virtual_card.withdraw_max_usd', 500),
            'has_active_card' => $operable !== null,
            'can_request_card' => $operable === null && $this->blockingCardRequest($wallet) === null,
            'card_preparing' => $operable === null && $latest !== null && $this->isPreparingRequest($latest),
            'operable_request' => $operable ? $this->serializeRequest($operable) : null,
            'latest_request' => $latest ? $this->serializeRequest($latest) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRequest(VirtualCardRequest $row): array
    {
        $canManage = $row->card_external_id
            && in_array($row->status, [VirtualCardRequest::STATUS_SUBMITTED, VirtualCardRequest::STATUS_ACTIVE], true);

        return [
            'id' => $row->id,
            'status' => $row->status,
            'is_preparing' => $this->isPreparingRequest($row),
            'provider_reference' => $row->provider_reference,
            'fee_usd' => (float) $row->fee_usd,
            'fee_ngn' => (float) $row->fee_ngn,
            'fx_rate_used' => $row->fx_rate_used !== null ? (float) $row->fx_rate_used : null,
            'card_name' => $row->card_name,
            'card_external_id' => $row->card_external_id,
            'is_frozen' => (bool) $row->is_frozen,
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
        return $row->card_external_id
            && in_array($row->status, [VirtualCardRequest::STATUS_SUBMITTED, VirtualCardRequest::STATUS_ACTIVE], true);
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
}
