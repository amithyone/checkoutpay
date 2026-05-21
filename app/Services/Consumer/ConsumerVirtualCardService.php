<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPay\MevonPayCardApiClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ConsumerVirtualCardService
{
    public function __construct(
        private MevonPayCardApiClient $cardApi,
        private WhatsappCrossBorderP2pFxService $fx,
        private ConsumerWalletPinVerifier $pinVerifier,
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
        $latest = VirtualCardRequest::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->latest('id')
            ->first();

        $feeUsd = $this->requestFeeUsd();
        $feeNgn = $this->quoteFeeNgn($feeUsd);

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => [
                'enabled' => $this->isEnabled(),
                'is_tier2' => $wallet->isTier2(),
                'fee_usd' => $feeUsd,
                'fee_ngn' => $feeNgn,
                'fx_available' => $feeNgn !== null,
                'latest_request' => $latest ? $this->serializeRequest($latest) : null,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function prefill(WhatsappWallet $wallet): array
    {
        $feeUsd = $this->requestFeeUsd();
        $feeNgn = $this->quoteFeeNgn($feeUsd);
        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11((string) $wallet->phone_e164);
        $dob = $wallet->kyc_dob?->format('Y-m-d');

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => [
                'enabled' => $this->isEnabled(),
                'is_tier2' => $wallet->isTier2(),
                'fee_usd' => $feeUsd,
                'fee_ngn' => $feeNgn,
                'fx_available' => $feeNgn !== null,
                'first_name' => $wallet->kyc_fname,
                'last_name' => $wallet->kyc_lname,
                'email' => $wallet->kyc_email,
                'phone_number' => $phone11,
                'dob' => $dob,
                'home_number' => $wallet->card_home_number,
                'home_address' => $wallet->card_home_address,
                'card_name' => trim(($wallet->kyc_fname ?? '').' '.($wallet->kyc_lname ?? '')) ?: $wallet->displayName(),
            ],
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

        $feeUsd = $this->requestFeeUsd();
        $feeNgn = $this->quoteFeeNgn($feeUsd);
        if ($feeNgn === null || $feeNgn < 0.01) {
            return [
                'ok' => false,
                'message' => 'USD/NGN exchange rate is not configured. Ask admin to add it in WhatsApp wallet FX settings.',
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

        $fromCur = (string) config('virtual_card.fee_currency_from', 'USD');
        $toCur = (string) config('virtual_card.fee_currency_to', 'NGN');
        $fxRate = $this->fx->convertCurrency($fromCur, $toCur, 1.0);
        if ($fxRate === null || $fxRate < 0.01) {
            return ['ok' => false, 'message' => 'Exchange rate unavailable.'];
        }

        $reference = 'VCARD-'.strtoupper(Str::random(14));

        try {
            DB::transaction(function () use ($wallet, $feeNgn, $reference) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($feeNgn);
                if (! $w->hasPin() || ! $check['ok']) {
                    throw new \RuntimeException('cannot_debit');
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
                        'fee_usd' => $this->requestFeeUsd(),
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('consumer.virtual_card.debit_failed', ['wallet_id' => $wallet->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not debit wallet for card fee. Check balance and limits.'];
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
            'fx_rate_used' => $fxRate,
            'external_reference' => $reference,
            'card_name' => $cardName,
            'home_number' => $homeNumber,
            'home_address' => $homeAddress,
            'request_payload' => $payload,
        ]);

        $api = $this->cardApi->createCard($payload);

        $wallet->card_home_number = $homeNumber;
        $wallet->card_home_address = $homeAddress;
        $wallet->save();

        if ($api['ok'] ?? false) {
            $row->update([
                'status' => VirtualCardRequest::STATUS_SUBMITTED,
                'response_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : ['raw' => $api['raw'] ?? null],
                'card_external_id' => $this->extractCardId($api['data'] ?? null),
            ]);

            return [
                'ok' => true,
                'message' => (string) ($api['message'] ?? 'Dollar Virtual Card request submitted.'),
                'data' => [
                    'request' => $this->serializeRequest($row->fresh()),
                    'balance_after' => (float) $wallet->fresh()->balance,
                ],
            ];
        }

        $this->refundFee($wallet->id, $reference, $feeNgn, (string) ($api['message'] ?? 'Card provider error'));
        $row->update([
            'status' => VirtualCardRequest::STATUS_FAILED,
            'failure_reason' => (string) ($api['message'] ?? 'Failed'),
            'response_payload' => is_array($api['raw'] ?? null) ? $api['raw'] : null,
        ]);

        return [
            'ok' => false,
            'message' => (string) ($api['message'] ?? 'Dollar Virtual Card request failed. Fee refunded.'),
            'data' => [
                'balance_after' => (float) $wallet->fresh()->balance,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function topupStub(): array
    {
        return [
            'ok' => false,
            'message' => 'Card top-up is not available yet. We will enable it when the provider endpoint is ready.',
        ];
    }

    private function quoteFeeNgn(float $feeUsd): ?float
    {
        $from = (string) config('virtual_card.fee_currency_from', 'USD');
        $to = (string) config('virtual_card.fee_currency_to', 'NGN');

        return $this->fx->convertCurrency($from, $to, $feeUsd);
    }

    private function refundFee(int $walletId, string $reference, float $amount, string $reason): void
    {
        try {
            DB::transaction(function () use ($walletId, $reference, $amount, $reason) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($walletId);
                $txn = WhatsappWalletTransaction::query()
                    ->where('external_reference', $reference)
                    ->where('whatsapp_wallet_id', $walletId)
                    ->first();
                if (! $w || ! $txn) {
                    return;
                }
                $meta = is_array($txn->meta) ? $txn->meta : [];
                if ($meta['refunded'] ?? false) {
                    return;
                }
                $w->balance = round((float) $w->balance + $amount, 2);
                $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                $w->save();
                $meta['refunded'] = true;
                $meta['refund_reason'] = $reason;
                $txn->update(['meta' => $meta]);
            });
        } catch (\Throwable $e) {
            Log::error('consumer.virtual_card.refund_failed', ['wallet_id' => $walletId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRequest(VirtualCardRequest $row): array
    {
        return [
            'id' => $row->id,
            'status' => $row->status,
            'fee_usd' => (float) $row->fee_usd,
            'fee_ngn' => (float) $row->fee_ngn,
            'fx_rate_used' => $row->fx_rate_used !== null ? (float) $row->fx_rate_used : null,
            'card_name' => $row->card_name,
            'card_external_id' => $row->card_external_id,
            'failure_reason' => $row->failure_reason,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function extractCardId(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        $id = (string) ($data['card_id'] ?? $data['cardId'] ?? $data['id'] ?? '');

        return trim($id) !== '' ? $id : null;
    }
}
