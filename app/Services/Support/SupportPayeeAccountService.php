<?php

namespace App\Services\Support;

use App\Models\AccountNumber;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingTopup;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SupportPayeeAccountService
{
    public function __construct(
        private SupportPaymentLookupService $payments,
    ) {}

    public static function normalizeAccountNumber(string $account): string
    {
        return preg_replace('/\D+/', '', $account) ?? '';
    }

    /**
     * @return array{
     *   payment_found: bool,
     *   payment?: Payment,
     *   account_on_session: bool,
     *   account_in_platform: bool,
     *   payee_name_matches: bool,
     *   whatsapp_eligible: bool,
     *   payment_summary?: array<string, mixed>,
     *   account_meta?: array<string, mixed>
     * }
     */
    public function evaluate(string $sessionId, string $reportedAccount): array
    {
        $sessionId = trim($sessionId);
        $normalizedReported = self::normalizeAccountNumber($reportedAccount);

        if ($normalizedReported === '') {
            return $this->emptyEvaluation(false);
        }

        $platformMeta = $this->resolvePlatformAccount($normalizedReported);
        $accountInPlatform = $platformMeta !== null;

        $payment = $this->findPaymentBySession($sessionId);
        $paymentFound = $payment !== null;

        $accountOnSession = false;
        if ($payment && $payment->account_number) {
            $accountOnSession = self::normalizeAccountNumber((string) $payment->account_number) === $normalizedReported;
        }

        $payeeNameMatches = $platformMeta !== null
            ? $this->accountNameMatchesGateway((string) ($platformMeta['account_name'] ?? ''))
            : false;

        $whatsappEligible = $paymentFound && $accountOnSession;

        $result = [
            'payment_found' => $paymentFound,
            'account_on_session' => $accountOnSession,
            'account_in_platform' => $accountInPlatform,
            'payee_name_matches' => $payeeNameMatches,
            'whatsapp_eligible' => $whatsappEligible,
            'account_meta' => $platformMeta,
        ];

        if ($payment) {
            $result['payment'] = $payment;
            $result['payment_summary'] = $this->payments->publicSummary($payment);
        }

        return $result;
    }

    public function isAccountInPlatform(string $accountNumber): bool
    {
        return $this->resolvePlatformAccount(self::normalizeAccountNumber($accountNumber)) !== null;
    }

    /**
     * @return array{account_number: string, account_name: string|null, source: string}|null
     */
    public function resolvePlatformAccount(string $normalizedAccount): ?array
    {
        if ($normalizedAccount === '') {
            return null;
        }

        $accountRow = AccountNumber::query()
            ->where('account_number', $normalizedAccount)
            ->where('is_active', true)
            ->first();

        if ($accountRow) {
            return [
                'account_number' => $normalizedAccount,
                'account_name' => $accountRow->account_name,
                'bank_name' => $accountRow->bank_name,
                'is_external' => (bool) $accountRow->is_external,
                'is_pool' => (bool) $accountRow->is_pool,
                'source' => $accountRow->is_external ? 'external_api' : 'account_number',
            ];
        }

        $pendingTopup = WhatsappWalletPendingTopup::query()
            ->where('account_number', $normalizedAccount)
            ->where('expires_at', '>', now())
            ->whereNull('fulfilled_at')
            ->orderByDesc('id')
            ->first();

        if ($pendingTopup) {
            return [
                'account_number' => $normalizedAccount,
                'account_name' => $pendingTopup->account_name,
                'source' => 'wallet_pending_topup',
            ];
        }

        $wallet = WhatsappWallet::query()
            ->where('mevon_virtual_account_number', $normalizedAccount)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if ($wallet) {
            return [
                'account_number' => $normalizedAccount,
                'account_name' => $wallet->mevon_account_name,
                'source' => 'wallet_permanent_va',
            ];
        }

        return null;
    }

    public function accountNameMatchesGateway(string $accountName): bool
    {
        $name = strtolower(trim($accountName));
        if ($name === '') {
            return false;
        }

        $patterns = config('support.payee_name_patterns', ['checkout now', 'checkoutpay']);

        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_contains($name, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function findPaymentBySession(string $sessionId): ?Payment
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        $payment = Payment::query()
            ->where('transaction_id', $sessionId)
            ->first();

        if ($payment) {
            return $payment;
        }

        return Payment::query()
            ->where('external_reference', $sessionId)
            ->orderByDesc('id')
            ->first();
    }

    public function buildStatusMessage(?Payment $payment): string
    {
        if (! $payment) {
            return (string) config('support.intake_messages.session_not_found', 'We could not find this session ID in our system. You can still chat with our team in this window.');
        }

        $summary = $this->payments->publicSummary($payment);
        $statusLabel = (string) ($summary['status_label'] ?? $payment->status);

        if (! empty($summary['is_pending'])) {
            return (string) config(
                'support.intake_messages.payment_pending',
                'Your payment is still pending. We will ask our banking partner to trace it using your bank session ID.'
            );
        }

        if ($statusLabel === 'approved') {
            return (string) config(
                'support.intake_messages.payment_approved',
                'This payment shows as approved in our system. If the merchant site did not update, tell us below and our team will help.'
            );
        }

        if (! empty($summary['is_expired'])) {
            return (string) config(
                'support.intake_messages.payment_expired',
                'This payment session has expired. If you already transferred, our team can still review with your bank session ID and receipt.'
            );
        }

        return 'Payment status: '.$statusLabel.'. Our team can review the details you provided.';
    }

    /**
     * @param  array<string, mixed>|null  $platformMeta
     */
    public function detectCollectionBankKey(?array $platformMeta): ?string
    {
        if ($platformMeta === null) {
            return null;
        }

        if (($platformMeta['source'] ?? '') === 'external_api' || ($platformMeta['is_external'] ?? false)) {
            return 'rubies_mfb';
        }

        $bankName = strtolower(trim((string) ($platformMeta['bank_name'] ?? '')));
        if ($bankName !== '') {
            if (str_contains($bankName, 'moniepoint')) {
                return 'moniepoint_mfb';
            }
            if (str_contains($bankName, 'kuda')) {
                return 'kuda';
            }
            if (str_contains($bankName, 'rubies')) {
                return 'rubies_mfb';
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function findPendingPaymentsForAccount(string $accountNumber, int $limit = 10): Collection
    {
        $account = self::normalizeAccountNumber($accountNumber);
        if ($account === '') {
            return collect();
        }

        return Payment::query()
            ->where('account_number', $account)
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{
     *   account_meta: array<string, mixed>,
     *   collection_bank_key: string|null,
     *   pending_payments: Collection<int, Payment>,
     *   pending_count: int
     * }
     */
    public function evaluateDestinationAccount(string $accountNumber): array
    {
        $normalized = self::normalizeAccountNumber($accountNumber);
        $meta = $this->resolvePlatformAccount($normalized) ?? [];
        $bankKey = $this->detectCollectionBankKey($meta);
        $pending = $this->findPendingPaymentsForAccount($normalized);

        return [
            'account_meta' => $meta,
            'collection_bank_key' => $bankKey,
            'pending_payments' => $pending,
            'pending_count' => $pending->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyEvaluation(bool $accountInPlatform): array
    {
        return [
            'payment_found' => false,
            'account_on_session' => false,
            'account_in_platform' => $accountInPlatform,
            'payee_name_matches' => false,
            'whatsapp_eligible' => false,
        ];
    }
}
