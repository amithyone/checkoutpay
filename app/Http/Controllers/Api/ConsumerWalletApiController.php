<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingTopup;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessActivityService;
use App\Services\Consumer\ConsumerBusinessNameRegistrationService;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\Consumer\ConsumerWalletKycService;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use App\Services\Consumer\ConsumerWalletPayQrService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use App\Services\Consumer\ConsumerWalletSavingsService;
use App\Services\Consumer\ConsumerWalletTransferService;
use App\Services\MavonPayTransferService;
use App\Contracts\Vtu\VtuProviderContract;
use App\Services\Vtu\VtuProviderResolver;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletPartnerApiService;
use App\Services\Whatsapp\WhatsappWalletPendingP2pService;
use App\Services\Whatsapp\WhatsappWalletPendingPayoutReconciliationService;
use App\Services\Whatsapp\WhatsappWalletSecureTransferAuthService;
use App\Services\Whatsapp\WhatsappWalletTier1TopupVaService;
use App\Services\Whatsapp\WhatsappWalletVtuPurchaseService;
use App\Services\WhatsappWalletBankPayoutService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ConsumerWalletApiController extends Controller
{
    public function __construct(
        private WhatsappWalletPartnerApiService $partnerApi,
        private WhatsappWalletTier1TopupVaService $tier1TopupVa,
        private WhatsappWalletCountryResolver $walletCountry,
        private ConsumerWalletPinVerifier $pinVerifier,
        private ConsumerWalletTransferService $transfers,
        private ConsumerWalletKycService $kyc,
        private WhatsappWalletBankPayoutService $bankPayout,
        private WhatsappWalletVtuPurchaseService $vtuPurchase,
        private VtuProviderResolver $vtuResolver,
        private WhatsappWalletPendingP2pService $pendingP2p,
        private WhatsappWalletSecureTransferAuthService $waTransferAuth,
        private ConsumerWalletPayCodeService $payCodes,
        private ConsumerWalletPayQrService $payQr,
        private WhatsappWalletPendingPayoutReconciliationService $pendingPayoutReconcile,
        private ConsumerBusinessNameRegistrationService $businessNameRegistration,
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private ConsumerBusinessActivityService $businessActivity,
        private ConsumerWalletSavingsService $savings,
    ) {}

    private function vtu(): VtuProviderContract
    {
        return $this->vtuResolver->active();
    }

    private function walletFor(Request $request): WhatsappWallet
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }
        $user->loadMissing('wallet');
        $w = $user->wallet;
        if (! $w) {
            abort(403, 'Wallet not linked.');
        }

        return $w;
    }

    public function showWallet(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof ConsumerWalletApiAccount) {
            $user->last_app_active_at = now();
            $user->saveQuietly();
        }

        $wallet = $this->walletFor($request)->fresh();

        try {
            $this->pendingPayoutReconcile->reconcileWallet($wallet);
        } catch (\Throwable) {
            // Best-effort; do not block wallet summary.
        }

        $wallet = $wallet->fresh(['linkedBusiness']);
        $this->businessLedger->refreshLinkedBalanceCache($wallet);
        $payCode = $this->payCodes->ensureForWallet($wallet);
        $summary = $this->partnerApi->getWalletSummary((string) $wallet->phone_e164);
        $cur = $this->walletCountry->currencyForPhoneE164((string) $wallet->phone_e164);

        $base = $summary['ok'] ? [
            'phone_e164' => $summary['phone_e164'],
            'wallet_id' => $summary['wallet_id'],
            'balance' => $summary['balance'],
            'has_pin' => $summary['has_pin'],
            'tier' => $summary['tier'],
            'status' => $summary['status'],
        ] : [
            'phone_e164' => $wallet->phone_e164,
            'wallet_id' => $wallet->id,
            'balance' => (float) $wallet->balance,
            'has_pin' => $wallet->hasPin(),
            'tier' => (int) $wallet->tier,
            'status' => $wallet->status,
        ];

        $payIn = null;
        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA) {
            $acct = trim((string) $wallet->mevon_virtual_account_number);
            if ($acct !== '') {
                $displayName = trim(trim((string) $wallet->kyc_fname).' '.trim((string) $wallet->kyc_lname));
                if ($displayName === '' && (string) $wallet->rubies_account_type === 'business' && trim((string) $wallet->kyc_cac) !== '') {
                    $displayName = 'Business · '.trim((string) $wallet->kyc_cac);
                }
                if ($displayName === '') {
                    $displayName = trim((string) ($wallet->sender_name ?? ''));
                }
                if ($displayName === '') {
                    $displayName = (string) ($wallet->mevon_reference ?? 'Wallet account');
                }
                $payIn = [
                    'kind' => 'permanent',
                    'account_number' => $acct,
                    'account_name' => $displayName,
                    'bank_name' => $wallet->mevon_bank_name ?? 'Rubies MFB',
                    'bank_code' => $wallet->mevon_bank_code,
                    'expires_at' => null,
                ];
            }
        } elseif ((int) $wallet->tier === WhatsappWallet::TIER_WHATSAPP_ONLY) {
            $pending = WhatsappWalletPendingTopup::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->whereNull('fulfilled_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->first();
            if ($pending) {
                $expiresDisplay = null;
                if ($pending->expires_at) {
                    try {
                        $expiresDisplay = Carbon::parse($pending->expires_at)
                            ->timezone((string) config('app.timezone'))
                            ->toIso8601String();
                    } catch (\Throwable) {
                        $expiresDisplay = $pending->expires_at->toIso8601String();
                    }
                }
                $payIn = [
                    'kind' => 'temporary',
                    'account_number' => (string) $pending->account_number,
                    'account_name' => trim((string) ($pending->account_name ?? '')) !== ''
                        ? (string) $pending->account_name
                        : $wallet->normalizedSenderName(),
                    'bank_name' => (string) ($pending->bank_name ?? ''),
                    'bank_code' => $pending->bank_code,
                    'expires_at' => $expiresDisplay,
                ];
            }
        }

        $e164 = (string) $wallet->phone_e164;
        $vtuEligible = $this->walletCountry->isNigeriaPayInWallet($e164);
        $vtuConfigured = $this->vtu()->isConfigured();
        $savingsSummary = $this->savings->getSummary($wallet);

        return response()->json([
            'success' => true,
            'data' => array_merge($base, [
                'currency' => $cur,
                'sender_name' => $wallet->normalizedSenderName(),
                'needs_quick_setup' => $wallet->needsQuickWalletSetup(),
                'is_pin_locked' => $wallet->isPinLocked(),
                'mevon_virtual_account_number' => $wallet->mevon_virtual_account_number,
                'mevon_bank_name' => $wallet->mevon_bank_name,
                'mevon_bank_code' => $wallet->mevon_bank_code,
                'rubies_account_type' => $wallet->rubies_account_type,
                'pay_in' => $payIn,
                'business_pay_in' => $this->businessLedger->resolveBusinessPayInPayload($wallet),
                'business_balance' => $this->businessLedger->resolvedBalance($wallet),
                'business_wallet_enabled' => $this->businessLedger->walletHasBusinessActivity($wallet),
                'linked_business_id' => $wallet->linked_business_id,
                'linked_business_name' => $wallet->linkedBusiness?->name,
                'vtu' => [
                    'eligible' => $vtuEligible,
                    'configured' => $vtuConfigured,
                    'available' => $vtuEligible && $vtuConfigured,
                    'airtime_min' => (float) config('vtu.airtime_min', 50),
                    'airtime_max' => (float) config('vtu.airtime_max', 50000),
                    'daily_limit' => $wallet->isTier1() ? (float) $wallet->tier1DailyOutLimit() : null,
                    'daily_remaining' => $wallet->isTier1() ? (float) $wallet->tier1DailyOutRemaining() : null,
                ],
                'transfer_email_otp_enabled' => (bool) $wallet->transfer_email_otp_enabled,
                'transfer_email_otp_eligible' => $wallet->isTier2(),
                'transfer_email_otp_has_email' => $wallet->resolveOtpEmail() !== null,
                'transfer_email_otp_effective' => $wallet->wantsTransferEmailOtp(),
                'notify_card_created_email' => $wallet->wantsCardCreatedEmail(),
                'notify_card_created_whatsapp' => $wallet->wantsCardCreatedWhatsapp(),
                'notify_card_transaction_email' => $wallet->wantsCardTransactionEmail(),
                'notify_card_transaction_whatsapp' => $wallet->wantsCardTransactionWhatsapp(),
                'card_notify_has_email' => $wallet->resolveOtpEmail() !== null,
                'pay_code' => $payCode,
                'savings_balance' => (float) ($wallet->savings_balance ?? 0),
                'savings_enabled' => (bool) ($savingsSummary['product_enabled'] ?? false),
                'savings_next_maturity_at' => $savingsSummary['next_maturity_at'] ?? null,
            ]),
        ]);
    }

    public function receiveQr(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();

        return response()->json([
            'success' => true,
            'data' => $this->payQr->buildReceiveQr($wallet),
        ]);
    }

    public function scanResolve(Request $request): JsonResponse
    {
        $request->validate([
            'payload' => 'required|string|min:1|max:4096',
        ]);

        $result = $this->payQr->resolveScanInput((string) $request->input('payload'));
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not resolve scan.',
            ], 422);
        }

        $phone = (string) $result['phone_e164'];
        $self = $this->walletFor($request);
        if ($phone === (string) $self->phone_e164) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot send to your own wallet.',
            ], 422);
        }

        $recipientWallet = WhatsappWallet::query()
            ->where('phone_e164', $phone)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'p2p',
                'phone_e164' => $phone,
                'display_name' => $result['display_name'] ?? ($recipientWallet?->normalizedSenderName() ?: null),
                'pay_code' => $result['pay_code'] ?? null,
                'has_wallet' => $recipientWallet !== null,
            ],
        ]);
    }

    public function ensure(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->partnerApi->ensureWallet((string) $wallet->phone_e164);
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Ensure failed',
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh(['linkedBusiness']);
        $perPage = max(1, min(50, (int) $request->input('per_page', 20)));
        $scope = ConsumerWalletTransactionScope::normalize((string) $request->input('scope', 'personal'));

        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        $tz = config('app.timezone', 'Africa/Lagos');
        $page = max(1, (int) $request->input('page', 1));
        $businessView = ConsumerBusinessActivityService::normalizeView($request->input('business_view'));

        if ($scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
            $business = $this->businessLedger->resolveLinkedOrMatchedBusiness($wallet);
            [$from, $to] = $this->resolveBusinessActivityDateRange($from, $to, $tz);
            try {
                $fromAt = Carbon::parse($from, $tz)->startOfDay();
                $toAt = Carbon::parse($to, $tz)->endOfDay();
            } catch (\Throwable) {
                return response()->json(['success' => false, 'message' => 'Invalid from or to date. Use YYYY-MM-DD.'], 422);
            }

            if ($business !== null) {
                $refresh = $request->boolean('refresh');
                $result = $this->businessActivity->paginate($wallet, $business, $from, $to, $page, $perPage, $businessView, $refresh);
                $walletModels = [];
                foreach ($result['items'] as $item) {
                    if ($item['wallet_tx'] instanceof WhatsappWalletTransaction) {
                        $walletModels[] = $item['wallet_tx'];
                    }
                }
                $enriched = $this->enrichTransactionsWithCounterpartyNames($walletModels, $wallet);
                $byId = [];
                foreach ($enriched as $row) {
                    $byId[(int) ($row['id'] ?? 0)] = $row;
                }
                $data = [];
                foreach ($result['items'] as $item) {
                    if ($item['wallet_tx'] instanceof WhatsappWalletTransaction) {
                        $data[] = $byId[(int) $item['wallet_tx']->id] ?? $item['row'];
                    } else {
                        $data[] = $this->enrichSyntheticActivityRow($item['row'], $wallet);
                    }
                }

                $lastPage = max(1, (int) ceil($result['total'] / $perPage));

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'meta' => [
                        'current_page' => $page,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $result['total'],
                        'scope' => $scope,
                        'from' => $from,
                        'to' => $to,
                        'timezone' => $tz,
                        'business_id' => $business->id,
                        'includes_merchant_activity' => true,
                        'business_view' => $businessView,
                        'refreshed' => $refresh,
                    ],
                ]);
            }

            $walletBusinessQuery = WhatsappWalletTransaction::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->where('ledger_scope', ConsumerWalletTransactionScope::SCOPE_BUSINESS)
                ->where('created_at', '>=', $fromAt)
                ->where('created_at', '<=', $toAt);

            if ($businessView === ConsumerBusinessActivityService::VIEW_ACCOUNT) {
                $walletBusinessQuery->where('type', WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN);
            }

            $walletBusinessPaginator = $walletBusinessQuery->orderByDesc('id')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $this->enrichTransactionsWithCounterpartyNames($walletBusinessPaginator->items(), $wallet),
                'meta' => [
                    'current_page' => $walletBusinessPaginator->currentPage(),
                    'last_page' => $walletBusinessPaginator->lastPage(),
                    'per_page' => $walletBusinessPaginator->perPage(),
                    'total' => $walletBusinessPaginator->total(),
                    'scope' => $scope,
                    'from' => $from,
                    'to' => $to,
                    'timezone' => $tz,
                    'includes_merchant_activity' => false,
                    'business_view' => $businessView,
                ],
            ]);
        }

        $query = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id);
        ConsumerWalletTransactionScope::apply($query, $scope);

        if ($from !== '') {
            try {
                $query->where('created_at', '>=', Carbon::parse($from, $tz)->startOfDay());
            } catch (\Throwable) {
                return response()->json(['success' => false, 'message' => 'Invalid from date. Use YYYY-MM-DD.'], 422);
            }
        }
        if ($to !== '') {
            try {
                $query->where('created_at', '<=', Carbon::parse($to, $tz)->endOfDay());
            } catch (\Throwable) {
                return response()->json(['success' => false, 'message' => 'Invalid to date. Use YYYY-MM-DD.'], 422);
            }
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $this->enrichTransactionsWithCounterpartyNames($paginator->items(), $wallet),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'scope' => $scope,
                'from' => $from !== '' ? $from : null,
                'to' => $to !== '' ? $to : null,
                'timezone' => $tz,
            ],
        ]);
    }

    public function recipientLookup(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
        ]);

        $recipient = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($recipient === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number.',
            ], 422);
        }

        if ($recipient === (string) $wallet->phone_e164) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send to your own number.',
            ], 422);
        }

        $recvWallet = WhatsappWallet::query()
            ->where('phone_e164', $recipient)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'phone_e164' => $recipient,
                'has_wallet' => $recvWallet !== null,
                'display_name' => $recvWallet?->displayName(),
            ],
        ]);
    }

    /**
     * @param  list<WhatsappWalletTransaction>  $items
     * @return list<array<string, mixed>>
     */
    private function enrichTransactionsWithCounterpartyNames(array $items, WhatsappWallet $wallet): array
    {
        $phones = [];
        foreach ($items as $tx) {
            $phone = trim((string) $tx->counterparty_phone_e164);
            if ($phone === '') {
                continue;
            }
            $isP2pCredit = $tx->type === WhatsappWalletTransaction::TYPE_P2P_CREDIT;
            $needsSender = $isP2pCredit && trim((string) $tx->sender_name) === '';
            $needsCounterparty = trim((string) $tx->counterparty_account_name) === '';
            if ($needsSender || $needsCounterparty) {
                $phones[] = $phone;
            }
        }

        $byPhone = [];
        if ($phones !== []) {
            $byPhone = WhatsappWallet::query()
                ->whereIn('phone_e164', array_values(array_unique($phones)))
                ->get()
                ->keyBy('phone_e164')
                ->all();
        }

        return array_map(function (WhatsappWalletTransaction $tx) use ($byPhone, $wallet) {
            $row = $tx->toArray();
            $phone = trim((string) ($row['counterparty_phone_e164'] ?? ''));
            if ($phone !== '') {
                $w = $byPhone[$phone] ?? null;
                if ($w instanceof WhatsappWallet) {
                    $name = $w->displayName();
                    if ($name !== null) {
                        if ($tx->type === WhatsappWalletTransaction::TYPE_P2P_CREDIT) {
                            if (trim((string) ($row['sender_name'] ?? '')) === '') {
                                $row['sender_name'] = $name;
                            }
                        }
                        if (trim((string) ($row['counterparty_account_name'] ?? '')) === '') {
                            $row['counterparty_account_name'] = $name;
                        }
                    }
                }
            }

            return $this->enrichTransactionRow($tx, $row, $wallet);
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichTransactionRow(WhatsappWalletTransaction $tx, array $row, WhatsappWallet $wallet): array
    {
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];

        if ($tx->type === WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            $bankName = trim((string) ($meta['bank_name'] ?? ''));
            if ($bankName === '' && ! empty($row['counterparty_bank_code'])) {
                $bankName = trim((string) (Bank::query()
                    ->where('code', (string) $row['counterparty_bank_code'])
                    ->value('name') ?? ''));
            }
            if ($bankName !== '') {
                $row['counterparty_bank_name'] = $bankName;
            }

            $sessionId = trim((string) ($meta['payout_session_id'] ?? $meta['session_id'] ?? ''));
            $mevonpay = is_array($meta['mevonpay'] ?? null) ? $meta['mevonpay'] : [];
            $apiResponse = is_array($mevonpay['api_response'] ?? null) ? $mevonpay['api_response'] : [];
            $apiSessionId = trim((string) ($apiResponse['sessionId'] ?? $apiResponse['session_id'] ?? ''));
            $payoutSessionId = trim((string) ($meta['payout_session_id'] ?? ''));

            if ($apiSessionId !== '') {
                $row['api_session_id'] = $apiSessionId;
                $row['session_id'] = $apiSessionId;
            } elseif ($sessionId !== '') {
                $row['session_id'] = $sessionId;
            }
            if ($payoutSessionId !== '') {
                $row['payout_session_id'] = $payoutSessionId;
            }

            $narration = trim((string) ($meta['narration'] ?? ''));
            $apiNarration = trim((string) ($apiResponse['narration'] ?? $apiResponse['Narration'] ?? ''));
            if ($apiNarration !== '') {
                $row['narration'] = $apiNarration;
            } elseif ($narration !== '') {
                $row['narration'] = $narration;
            }

            $ledgerScope = ConsumerWalletTransactionScope::normalize((string) ($row['ledger_scope'] ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL));
            if (trim((string) ($row['sender_name'] ?? '')) === '') {
                $resolvedSender = $this->businessLedger->resolveLedgerSenderName($wallet, $ledgerScope);
                if ($resolvedSender !== null && trim($resolvedSender) !== '') {
                    $row['sender_name'] = trim($resolvedSender);
                }
            }

            $senderAcct = $this->resolveSenderAccountForReceipt($wallet, $ledgerScope);
            if ($senderAcct !== null) {
                $row['sender_account_number'] = $senderAcct;
            }
        }

        if ($tx->type === WhatsappWalletTransaction::TYPE_TOPUP) {
            $payerName = trim((string) ($row['counterparty_account_name'] ?? ''));
            if ($payerName === '') {
                $payerName = trim((string) ($meta['payer_name'] ?? $meta['sender'] ?? ''));
                if ($payerName !== '') {
                    $row['counterparty_account_name'] = $payerName;
                }
            }

            $payerBank = trim((string) ($meta['payer_bank'] ?? $meta['bank_name'] ?? ''));
            if ($payerBank !== '') {
                $row['counterparty_bank_name'] = $payerBank;
            }

            $receiveAcct = trim((string) ($meta['receive_account_number'] ?? ''));
            if ($receiveAcct !== '') {
                $row['receive_account_number'] = $receiveAcct;
            }

            $bankRef = trim((string) ($row['external_reference'] ?? $meta['mevon_reference'] ?? ''));
            if ($bankRef !== '') {
                $row['topup_bank_reference'] = $bankRef;
            }

            $narration = trim((string) ($meta['narration'] ?? ''));
            if ($narration !== '') {
                $row['topup_narration'] = $narration;
            }

            $bankTime = trim((string) ($meta['bank_timestamp'] ?? ''));
            if ($bankTime !== '') {
                $row['topup_bank_timestamp'] = $bankTime;
            }

            $reported = $meta['reported_amount'] ?? null;
            if ($reported !== null && is_numeric($reported) && (float) $reported > 0) {
                $row['topup_reported_amount'] = (float) $reported;
            }
        }

        return $row;
    }

    private function enrichSyntheticActivityRow(array $row, WhatsappWallet $wallet): array
    {
        $type = (string) ($row['type'] ?? '');
        if ($type !== 'merchant_withdrawal_out') {
            return $row;
        }

        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $mevonpay = is_array($meta['mevonpay'] ?? null) ? $meta['mevonpay'] : [];
        $apiResponse = is_array($mevonpay['api_response'] ?? null) ? $mevonpay['api_response'] : [];
        $apiSessionId = trim((string) ($apiResponse['sessionId'] ?? $apiResponse['session_id'] ?? ''));
        $payoutSessionId = trim((string) ($meta['payout_session_id'] ?? ''));

        if ($apiSessionId === '') {
            $raw = is_array($meta['payout_raw_response'] ?? null) ? $meta['payout_raw_response'] : [];
            foreach (['sessionId', 'session_id', 'SessionId'] as $key) {
                $v = trim((string) ($raw[$key] ?? ''));
                if ($v !== '') {
                    $apiSessionId = $v;
                    break;
                }
            }
        }

        if ($apiSessionId !== '') {
            $row['api_session_id'] = $apiSessionId;
            $row['session_id'] = $apiSessionId;
        }
        if ($payoutSessionId !== '') {
            $row['payout_session_id'] = $payoutSessionId;
        }

        $narration = trim((string) ($meta['bank_narration'] ?? $meta['narration'] ?? ''));
        if ($narration !== '') {
            $row['narration'] = $narration;
        }

        $senderName = $this->businessLedger->resolveLedgerSenderName($wallet, ConsumerWalletTransactionScope::SCOPE_BUSINESS);
        if ($senderName !== null && trim($senderName) !== '') {
            $row['sender_name'] = trim($senderName);
        }

        $senderAcct = $this->resolveSenderAccountForReceipt($wallet, ConsumerWalletTransactionScope::SCOPE_BUSINESS);
        if ($senderAcct !== null) {
            $row['sender_account_number'] = $senderAcct;
        }

        return $row;
    }

    private function resolveSenderAccountForReceipt(WhatsappWallet $wallet, string $ledgerScope): ?string
    {
        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);
        if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
            $payIn = $this->businessLedger->resolveBusinessPayInPayload($wallet);
            $acct = trim((string) ($payIn['account_number'] ?? ''));

            return $acct !== '' ? $acct : null;
        }

        $acct = trim((string) $wallet->mevon_virtual_account_number);

        return $acct !== '' ? $acct : null;
    }

    public function issueTopupVirtualAccount(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        $e164 = (string) $wallet->phone_e164;

        if (! $this->walletCountry->isNigeriaPayInWallet($e164)) {
            return response()->json([
                'success' => false,
                'message' => 'Bank top-up virtual accounts are only available for Nigeria wallet numbers.',
            ], 422);
        }

        if (! $wallet->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'This wallet is not active.',
            ], 422);
        }

        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA) {
            $acct = trim((string) $wallet->mevon_virtual_account_number);
            if ($acct === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tier 2 wallet has no dedicated account on file yet. Complete KYC first.',
                ], 422);
            }

            $displayName = trim(trim((string) $wallet->kyc_fname).' '.trim((string) $wallet->kyc_lname));
            if ($displayName === '' && (string) $wallet->rubies_account_type === 'business' && trim((string) $wallet->kyc_cac) !== '') {
                $displayName = 'Business · '.trim((string) $wallet->kyc_cac);
            }
            if ($displayName === '') {
                $displayName = trim((string) ($wallet->sender_name ?? ''));
            }
            if ($displayName === '') {
                $displayName = (string) ($wallet->mevon_reference ?? 'Wallet account');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'kind' => 'permanent',
                    'account_number' => $acct,
                    'account_name' => $displayName,
                    'bank_name' => $wallet->mevon_bank_name ?? 'Rubies MFB',
                    'bank_code' => $wallet->mevon_bank_code,
                    'expires_at' => null,
                    'phone_e164' => $wallet->phone_e164,
                ],
            ]);
        }

        if ((int) $wallet->tier !== WhatsappWallet::TIER_WHATSAPP_ONLY) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported wallet tier for bank top-up.',
            ], 422);
        }

        $issued = $this->tier1TopupVa->issueFreshVa($wallet->fresh());
        if (! ($issued['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($issued['message'] ?? 'Could not create a top-up account.'),
            ], 502);
        }

        $expiresAt = isset($issued['expires_at']) ? (string) $issued['expires_at'] : null;
        $expiresDisplay = null;
        if ($expiresAt !== null && $expiresAt !== '') {
            try {
                $expiresDisplay = Carbon::parse($expiresAt)->timezone((string) config('app.timezone'))->toIso8601String();
            } catch (\Throwable) {
                $expiresDisplay = $expiresAt;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kind' => 'temporary',
                'account_number' => (string) ($issued['account_number'] ?? ''),
                'account_name' => (string) ($issued['account_name'] ?? ''),
                'bank_name' => (string) ($issued['bank_name'] ?? ''),
                'bank_code' => $issued['bank_code'] ?? null,
                'expires_at' => $expiresDisplay,
                'phone_e164' => $wallet->phone_e164,
            ],
        ], 201);
    }

    public function setPin(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->hasPin()) {
            return response()->json([
                'success' => false,
                'message' => 'PIN already set. Use change PIN.',
            ], 422);
        }

        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $wallet->pin_hash = Hash::make((string) $request->input('pin'));
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        $instance = \App\Services\Whatsapp\WhatsappEvolutionConfigResolver::walletInstance();
        if ($instance !== '') {
            $this->pendingP2p->tryClaimForWallet($wallet->fresh(), $instance);
        }

        return response()->json(['success' => true, 'message' => 'PIN saved.']);
    }

    public function changePin(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        if (! $wallet->hasPin()) {
            return response()->json([
                'success' => false,
                'message' => 'Set a PIN first.',
            ], 422);
        }

        $request->validate([
            'current_pin' => ['required', 'regex:/^\d{4}$/'],
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        if (! $this->pinVerifier->verify($wallet, (string) $request->input('current_pin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Current PIN is incorrect.',
            ], 422);
        }

        $wallet->pin_hash = Hash::make((string) $request->input('pin'));
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        return response()->json(['success' => true, 'message' => 'PIN updated.']);
    }

    public function updateSenderName(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $request->validate([
            'sender_name' => 'required|string|min:2|max:120',
        ]);

        $wallet->sender_name = trim((string) $request->input('sender_name'));
        $wallet->save();

        return response()->json(['success' => true, 'message' => 'Display name updated.']);
    }

    public function updateTransferEmailOtp(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        if (! $wallet->isTier2()) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer email codes are only available on Tier 2 wallets.',
            ], 422);
        }

        $request->validate([
            'transfer_email_otp_enabled' => 'required|boolean',
        ]);

        $enabled = (bool) $request->boolean('transfer_email_otp_enabled');
        if ($enabled && $wallet->resolveOtpEmail() === null) {
            return response()->json([
                'success' => false,
                'message' => 'Add a verified email on your wallet (Tier 2 KYC or linked account) before turning this on.',
            ], 422);
        }

        $wallet->transfer_email_otp_enabled = $enabled;
        $wallet->save();

        return response()->json([
            'success' => true,
            'message' => $enabled
                ? 'Email confirmation codes are ON for transfers.'
                : 'Email confirmation codes are OFF. Secure link only.',
            'data' => [
                'transfer_email_otp_enabled' => (bool) $wallet->transfer_email_otp_enabled,
                'transfer_email_otp_effective' => $wallet->wantsTransferEmailOtp(),
            ],
        ]);
    }

    public function updateCardNotifications(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();

        $validated = $request->validate([
            'notify_card_created_email' => 'sometimes|boolean',
            'notify_card_created_whatsapp' => 'sometimes|boolean',
            'notify_card_transaction_email' => 'sometimes|boolean',
            'notify_card_transaction_whatsapp' => 'sometimes|boolean',
        ]);

        if ($validated === []) {
            return response()->json([
                'success' => false,
                'message' => 'No notification settings provided.',
            ], 422);
        }

        foreach ([
            'notify_card_created_email',
            'notify_card_created_whatsapp',
            'notify_card_transaction_email',
            'notify_card_transaction_whatsapp',
        ] as $key) {
            if (array_key_exists($key, $validated)) {
                $wallet->{$key} = (bool) $validated[$key];
            }
        }

        $emailKeys = ['notify_card_created_email', 'notify_card_transaction_email'];
        $wantsEmail = $wallet->wantsCardCreatedEmail() || $wallet->wantsCardTransactionEmail();
        if ($wantsEmail && $wallet->resolveOtpEmail() === null) {
            foreach ($emailKeys as $key) {
                if (array_key_exists($key, $validated) && $validated[$key]) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Add a KYC email on your profile before enabling card email alerts.',
                    ], 422);
                }
            }
        }

        $wallet->save();

        return response()->json([
            'success' => true,
            'message' => 'Card notification settings updated.',
            'data' => [
                'notify_card_created_email' => $wallet->wantsCardCreatedEmail(),
                'notify_card_created_whatsapp' => $wallet->wantsCardCreatedWhatsapp(),
                'notify_card_transaction_email' => $wallet->wantsCardTransactionEmail(),
                'notify_card_transaction_whatsapp' => $wallet->wantsCardTransactionWhatsapp(),
                'card_notify_has_email' => $wallet->resolveOtpEmail() !== null,
            ],
        ]);
    }

    public function registerPushToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'required|string|in:android,ios,web',
        ]);

        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            return response()->json(['success' => false, 'message' => 'Invalid session.'], 401);
        }

        $user->fcm_token = (string) $request->input('token');
        $user->fcm_platform = (string) $request->input('platform');
        $user->fcm_token_updated_at = now();
        $user->save();

        return response()->json(['success' => true, 'message' => 'Push token saved.']);
    }

    public function transferP2p(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'to_phone' => 'required|string|min:10|max:20',
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $result = $this->transfers->p2p(
            $wallet,
            (string) $request->input('to_phone'),
            (float) $request->input('amount')
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function transferBank(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'amount' => 'required|numeric|min:1',
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string|max:20',
            'bank_name' => 'required|string|max:120',
            'account_name' => 'required|string|max:120',
            'remark' => 'nullable|string|max:255',
            'from_ledger' => 'nullable|string|in:personal,business',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $result = $this->transfers->bankTransfer(
            $wallet,
            (float) $request->input('amount'),
            (string) $request->input('account_number'),
            (string) $request->input('bank_code'),
            (string) $request->input('bank_name'),
            (string) $request->input('account_name'),
            $request->filled('remark') ? (string) $request->input('remark') : null,
            ConsumerWalletTransactionScope::normalize((string) $request->input('from_ledger', 'personal')),
        );

        $code = $result['ok'] ? 200 : 422;
        if ($result['ok'] === false && ($result['data']['bucket'] ?? '') === MavonPayTransferService::BUCKET_FAILED) {
            $code = 502;
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $code);
    }

    /**
     * Complete a pending WhatsApp-style bank/P2P debit that was staged with a web confirm token (same cache entry as the secure link).
     */
    public function confirmTransferWebToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:128',
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->waTransferAuth->confirmViaWebPinForConsumerApp(
            $wallet,
            (string) $request->input('token'),
            (string) $request->input('pin')
        );

        $ok = (bool) ($result['ok'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => $ok
                ? (string) ($result['message'] ?? 'Transfer completed.')
                : (string) ($result['error'] ?? 'Could not confirm.'),
            'data' => $ok ? [
                'balance_after' => isset($result['balance_after']) ? (float) $result['balance_after'] : (float) $wallet->fresh()->balance,
            ] : null,
        ], $ok ? 200 : 422);
    }

    public function bankNameEnquiry(Request $request): JsonResponse
    {
        $this->walletFor($request);
        $request->validate([
            'bank_code' => 'required|string|max:20',
            'account_number' => 'required|string|size:10',
        ]);

        $ne = $this->bankPayout->nameEnquiry(
            (string) $request->input('bank_code'),
            (string) $request->input('account_number')
        );
        if ($ne === null) {
            return response()->json([
                'success' => false,
                'message' => 'Name enquiry failed.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account_name' => $ne['account_name'],
                'bank_code' => $ne['bank_code'],
            ],
        ]);
    }

    public function vtuNetworks(): JsonResponse
    {
        $catalog = $this->vtu()->networksCatalog();

        return response()->json([
            'success' => true,
            'data' => [
                'networks' => $catalog['networks'] ?? [],
                'configured' => $this->vtu()->isConfigured(),
                'provider' => $this->vtu()->providerKey(),
                'airtime_min' => (float) ($catalog['airtime_min'] ?? 50),
                'airtime_max' => (float) ($catalog['airtime_max'] ?? 50000),
            ],
        ]);
    }

    public function vtuDataPlans(Request $request): JsonResponse
    {
        $request->validate([
            'network_id' => 'required|string|max:40',
        ]);

        if (! $this->vtu()->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'VTU not configured.'], 503);
        }

        $parsed = $this->vtu()->fetchDataPlans((string) $request->input('network_id'));
        if (! ($parsed['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $parsed['message'] ?? 'Could not load plans.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['plans' => $parsed['plans'] ?? []],
        ]);
    }

    public function vtuAirtime(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'network_id' => 'required|string|max:40',
            'phone' => 'required|string|min:10|max:20',
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json(['success' => false, 'message' => 'Invalid phone.'], 422);
        }

        $out = $this->vtuPurchase->purchaseAirtime(
            $wallet,
            (string) $request->input('network_id'),
            $e164,
            (float) $request->input('amount')
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    public function vtuData(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'network_id' => 'required|string|max:40',
            'phone' => 'required|string|min:10|max:20',
            'variation_id' => 'required|integer|min:1',
            'expected_price' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json(['success' => false, 'message' => 'Invalid phone.'], 422);
        }

        $out = $this->vtuPurchase->purchaseData(
            $wallet,
            (string) $request->input('network_id'),
            $e164,
            (int) $request->input('variation_id'),
            (float) $request->input('expected_price')
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    public function vtuBillCatalog(Request $request): JsonResponse
    {
        $this->walletFor($request);
        $catalog = $this->vtu()->billCatalog();

        return response()->json([
            'success' => true,
            'data' => array_merge($catalog, [
                'configured' => $this->vtu()->isConfigured(),
                'provider' => $this->vtu()->providerKey(),
            ]),
        ]);
    }

    public function vtuElectricityVerify(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|string|max:64',
            'customer_id' => 'required|string|max:64',
            'variation_id' => 'required|string|in:prepaid,postpaid',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($block = $this->consumerVtuPreconditionResponse($wallet)) {
            return $block;
        }
        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.electricity_discos')) {
            return response()->json(['success' => false, 'message' => 'Unknown electricity provider.'], 422);
        }

        $parsed = $this->vtu()->verifyElectricityCustomer(
            $serviceId,
            (string) $request->input('customer_id'),
            (string) $request->input('variation_id'),
        );

        return response()->json([
            'success' => $parsed['ok'],
            'message' => $parsed['message'] ?? ($parsed['ok'] ? 'OK' : 'Verification failed'),
            'data' => $parsed['data'] ?? null,
        ], $parsed['ok'] ? 200 : 422);
    }

    public function vtuElectricity(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'service_id' => 'required|string|max:64',
            'customer_id' => 'required|string|max:64',
            'variation_id' => 'required|string|in:prepaid,postpaid',
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($block = $this->consumerVtuPreconditionResponse($wallet)) {
            return $block;
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.electricity_discos')) {
            return response()->json(['success' => false, 'message' => 'Unknown electricity provider.'], 422);
        }

        $amount = (float) $request->input('amount');
        $min = (float) ($this->vtu()->billCatalog()['electricity_min'] ?? 500);
        if ($amount + 0.00001 < $min) {
            return response()->json([
                'success' => false,
                'message' => 'Amount is below the minimum for electricity purchases (₦'.number_format($min, 0).').',
            ], 422);
        }

        $meter = (string) $request->input('customer_id');
        $variation = (string) $request->input('variation_id');
        $verify = $this->vtu()->verifyElectricityCustomer($serviceId, $meter, $variation);
        if (! ($verify['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($verify['message'] ?? 'Could not verify meter details.'),
            ], 422);
        }

        $data = is_array($verify['data'] ?? null) ? $verify['data'] : [];
        $customerName = isset($data['customer_name']) ? (string) $data['customer_name'] : null;

        $out = $this->vtuPurchase->purchaseElectricity(
            $wallet,
            $serviceId,
            $meter,
            $variation,
            (string) $wallet->phone_e164,
            $amount,
            $customerName,
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    public function vtuTvPlans(Request $request): JsonResponse
    {
        $this->walletFor($request);
        $request->validate([
            'service_id' => 'required|string|max:40',
        ]);
        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.cable_tv_services')) {
            return response()->json(['success' => false, 'message' => 'Unknown cable TV provider.'], 422);
        }
        if (! $this->vtu()->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Bill payments are not configured.'], 503);
        }

        $parsed = $this->vtu()->fetchTvPlans($serviceId);
        if (! ($parsed['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $parsed['message'] ?? 'Could not load TV packages.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['plans' => $parsed['plans'] ?? []],
        ]);
    }

    public function vtuTvVerify(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|string|max:40',
            'customer_id' => 'required|string|max:64',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($block = $this->consumerVtuPreconditionResponse($wallet)) {
            return $block;
        }
        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.cable_tv_services')) {
            return response()->json(['success' => false, 'message' => 'Unknown cable TV provider.'], 422);
        }

        $parsed = $this->vtu()->verifyBillCustomer($serviceId, (string) $request->input('customer_id'));

        return response()->json([
            'success' => $parsed['ok'],
            'message' => $parsed['message'] ?? ($parsed['ok'] ? 'OK' : 'Verification failed'),
            'data' => $parsed['data'] ?? null,
        ], $parsed['ok'] ? 200 : 422);
    }

    public function vtuTv(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'service_id' => 'required|string|max:40',
            'customer_id' => 'required|string|max:64',
            'variation_id' => 'required',
            'expected_price' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($block = $this->consumerVtuPreconditionResponse($wallet)) {
            return $block;
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.cable_tv_services')) {
            return response()->json(['success' => false, 'message' => 'Unknown cable TV provider.'], 422);
        }

        $smart = (string) $request->input('customer_id');
        $verify = $this->vtu()->verifyBillCustomer($serviceId, $smart);
        if (! ($verify['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($verify['message'] ?? 'Could not verify smartcard.'),
            ], 422);
        }

        $variationId = $request->input('variation_id');
        $out = $this->vtuPurchase->purchaseCableTv(
            $wallet,
            $serviceId,
            $smart,
            is_numeric($variationId) ? (int) $variationId : (string) $variationId,
            (float) $request->input('expected_price'),
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    public function vtuBettingVerify(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|string|max:64',
            'customer_id' => 'required|string|max:128',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($block = $this->consumerVtuPreconditionResponse($wallet)) {
            return $block;
        }
        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.betting_services')) {
            return response()->json(['success' => false, 'message' => 'Unknown betting provider.'], 422);
        }

        $parsed = $this->vtu()->verifyBillCustomer($serviceId, (string) $request->input('customer_id'));

        return response()->json([
            'success' => $parsed['ok'],
            'message' => $parsed['message'] ?? ($parsed['ok'] ? 'OK' : 'Verification failed'),
            'data' => $parsed['data'] ?? null,
        ], $parsed['ok'] ? 200 : 422);
    }

    public function vtuBetting(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'service_id' => 'required|string|max:64',
            'customer_id' => 'required|string|max:128',
            'amount' => 'required|numeric|between:100,100000',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($block = $this->consumerVtuPreconditionResponse($wallet)) {
            return $block;
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $serviceId = (string) $request->input('service_id');
        if (! $this->consumerVtuServiceAllowed($serviceId, 'vtu.betting_services')) {
            return response()->json(['success' => false, 'message' => 'Unknown betting provider.'], 422);
        }

        $customerId = (string) $request->input('customer_id');
        $verify = $this->vtu()->verifyBillCustomer($serviceId, $customerId);
        if (! ($verify['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($verify['message'] ?? 'Could not verify betting account.'),
            ], 422);
        }

        $amount = (float) $request->input('amount');
        $out = $this->vtuPurchase->purchaseBetting($wallet, $serviceId, $customerId, $amount);

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    private function consumerVtuPreconditionResponse(WhatsappWallet $wallet): ?JsonResponse
    {
        $e164 = (string) $wallet->phone_e164;
        if (! $this->walletCountry->isNigeriaPayInWallet($e164)) {
            return response()->json([
                'success' => false,
                'message' => 'Bill payments are only available for Nigeria wallet numbers (NGN).',
            ], 422);
        }
        if (! $this->vtu()->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Bill payments are not configured.'], 503);
        }

        return null;
    }

    private function consumerVtuServiceAllowed(string $serviceId, string $configKey): bool
    {
        return $this->vtu()->serviceAllowed($serviceId, $configKey);
    }

    public function kycTier2Status(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        $out = $this->kyc->tier2Status($wallet);

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $out['data'] ?? null,
        ]);
    }

    public function kycTier2Personal(Request $request): JsonResponse
    {
        $request->validate([
            'fname' => 'required|string|max:128',
            'lname' => 'required|string|max:128',
            'dob' => 'required|date_format:Y-m-d',
            'gender' => 'required|string|in:male,female,M,F',
            'email' => 'required|email|max:255',
            'bvn' => 'required_without:nin|nullable|string|size:11',
            'nin' => 'required_without:bvn|nullable|string|size:11',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $g = strtolower((string) $request->input('gender', ''));
        if ($g === 'm') {
            $g = 'male';
        }
        if ($g === 'f') {
            $g = 'female';
        }

        $out = $this->kyc->submitPersonalTier2($wallet, array_merge($request->only([
            'fname', 'lname', 'dob', 'email', 'bvn', 'nin',
        ]), ['gender' => $g]));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
    }

    public function kycTier2Business(Request $request): JsonResponse
    {
        $request->validate([
            'cac' => 'required|string|max:100',
            'dob' => 'required|date_format:Y-m-d',
            'email' => 'required|email|max:255',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $out = $this->kyc->submitBusinessTier2($wallet, $request->only(['cac', 'dob', 'email']));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
    }

    /**
     * Default business activity window when History omits from/to (last 12 months).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveBusinessActivityDateRange(string $from, string $to, string $tz): array
    {
        $now = Carbon::now($tz);

        if ($from === '' && $to === '') {
            return [
                $now->copy()->subMonths(12)->format('Y-m-d'),
                $now->format('Y-m-d'),
            ];
        }

        if ($from === '' && $to !== '') {
            $toDate = Carbon::parse($to, $tz);

            return [
                $toDate->copy()->subMonths(12)->format('Y-m-d'),
                $to,
            ];
        }

        if ($from !== '' && $to === '') {
            return [$from, $now->format('Y-m-d')];
        }

        return [$from, $to];
    }
}
