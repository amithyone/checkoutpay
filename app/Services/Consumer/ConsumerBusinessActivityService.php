<?php

namespace App\Services\Consumer;

use App\Models\Business;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Unified merchant business activity for Utility / business-scoped transaction history.
 *
 * Merges wallet business ledger rows with CheckoutPay merchant records (website payments,
 * API checkouts, withdrawals) so statements match the merchant Permanent Account activity.
 */
final class ConsumerBusinessActivityService
{
    public function __construct(
        private ConsumerBusinessWalletLedgerService $businessLedger,
    ) {}

    /**
     * @return array{items: list<array{row: array<string, mixed>, wallet_tx: WhatsappWalletTransaction|null}>, total: int}
     */
    public function paginate(
        WhatsappWallet $wallet,
        Business $business,
        string $from,
        string $to,
        int $page,
        int $perPage,
    ): array {
        $tz = (string) config('app.timezone', 'Africa/Lagos');
        $fromAt = $this->parseBoundary($from, $tz, startOfDay: true);
        $toAt = $this->parseBoundary($to, $tz, startOfDay: false);

        $merged = $this->collectRows($wallet, $business, $fromAt, $toAt);
        $total = count($merged);
        $offset = max(0, ($page - 1) * $perPage);
        $items = array_slice($merged, $offset, $perPage);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<array{row: array<string, mixed>, wallet_tx: WhatsappWalletTransaction|null, sort_at: int}>
     */
    private function collectRows(
        WhatsappWallet $wallet,
        Business $business,
        Carbon $fromAt,
        Carbon $toAt,
    ): array {
        $walletQuery = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id);
        ConsumerWalletTransactionScope::apply($walletQuery, ConsumerWalletTransactionScope::SCOPE_BUSINESS);
        $walletQuery->where('created_at', '>=', $fromAt)->where('created_at', '<=', $toAt);
        $walletTxns = $walletQuery->orderByDesc('id')->get();

        $coveredPaymentIds = [];
        $rows = [];

        foreach ($walletTxns as $tx) {
            $meta = is_array($tx->meta) ? $tx->meta : [];
            if ($tx->type === WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN) {
                $paymentId = (int) ($meta['payment_id'] ?? 0);
                if ($paymentId > 0) {
                    $coveredPaymentIds[$paymentId] = true;
                }
            }
            $rows[] = [
                'row' => $tx->toArray(),
                'wallet_tx' => $tx,
                'sort_at' => $tx->created_at?->getTimestamp() ?? 0,
            ];
        }

        $payments = Payment::query()
            ->where('business_id', $business->id)
            ->where('status', Payment::STATUS_APPROVED)
            ->with('website:id,website_url')
            ->where(function ($query) use ($fromAt, $toAt) {
                $query->whereBetween('matched_at', [$fromAt, $toAt])
                    ->orWhere(function ($fallback) use ($fromAt, $toAt) {
                        $fallback->whereNull('matched_at')
                            ->whereBetween('created_at', [$fromAt, $toAt]);
                    });
            })
            ->orderByDesc('id')
            ->get();

        foreach ($payments as $payment) {
            if (isset($coveredPaymentIds[(int) $payment->id])) {
                continue;
            }

            $occurredAt = $payment->matched_at ?? $payment->created_at;
            $amount = round((float) ($payment->business_receives ?? $payment->amount), 2);
            $label = $this->paymentActivityLabel($payment);

            $rows[] = [
                'row' => [
                    'id' => -1 * (int) $payment->id,
                    'whatsapp_wallet_id' => $wallet->id,
                    'type' => 'merchant_payment_in',
                    'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_BUSINESS,
                    'amount' => $amount,
                    'balance_after' => null,
                    'counterparty_account_name' => trim((string) ($payment->payer_name ?? '')) ?: null,
                    'external_reference' => trim((string) ($payment->transaction_id ?? '')) ?: null,
                    'created_at' => $occurredAt?->toIso8601String(),
                    'meta' => array_filter([
                        'payment_id' => (int) $payment->id,
                        'business_id' => (int) $business->id,
                        'status' => (string) $payment->status,
                        'payment_source' => (string) ($payment->payment_source ?? ''),
                        'website_url' => $payment->website?->website_url,
                        'label' => $label,
                        'gross_amount' => round((float) $payment->amount, 2),
                        'business_receives' => $amount,
                    ], static fn ($v) => $v !== null && $v !== ''),
                ],
                'wallet_tx' => null,
                'sort_at' => $occurredAt?->getTimestamp() ?? 0,
            ];
        }

        $withdrawals = WithdrawalRequest::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [
                WithdrawalRequest::STATUS_PENDING,
                WithdrawalRequest::STATUS_APPROVED,
                WithdrawalRequest::STATUS_PROCESSED,
            ])
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->orderByDesc('id')
            ->get();

        foreach ($withdrawals as $withdrawal) {
            $occurredAt = $withdrawal->processed_at ?? $withdrawal->updated_at ?? $withdrawal->created_at;
            $statusLabel = ucfirst((string) $withdrawal->status);
            $bank = trim((string) ($withdrawal->bank_name ?? ''));

            $rows[] = [
                'row' => [
                    'id' => -1_000_000_000 - (int) $withdrawal->id,
                    'whatsapp_wallet_id' => $wallet->id,
                    'type' => 'merchant_withdrawal_out',
                    'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_BUSINESS,
                    'amount' => round((float) $withdrawal->amount, 2),
                    'balance_after' => null,
                    'counterparty_account_name' => trim((string) ($withdrawal->account_name ?? '')) ?: null,
                    'counterparty_bank_name' => $bank !== '' ? $bank : null,
                    'external_reference' => trim((string) ($withdrawal->payout_reference ?? '')) ?: null,
                    'created_at' => $occurredAt?->toIso8601String(),
                    'meta' => array_filter([
                        'withdrawal_id' => (int) $withdrawal->id,
                        'business_id' => (int) $business->id,
                        'status' => (string) $withdrawal->status,
                        'status_label' => $statusLabel,
                        'label' => 'Withdrawal · '.$statusLabel.($bank !== '' ? ' · '.$bank : ''),
                        'account_number' => trim((string) ($withdrawal->account_number ?? '')) ?: null,
                        'payout_status' => trim((string) ($withdrawal->payout_status ?? '')) ?: null,
                    ], static fn ($v) => $v !== null && $v !== ''),
                ],
                'wallet_tx' => null,
                'sort_at' => $occurredAt?->getTimestamp() ?? 0,
            ];
        }

        usort($rows, static fn (array $a, array $b) => $b['sort_at'] <=> $a['sort_at']);

        return array_map(static fn (array $item) => [
            'row' => $item['row'],
            'wallet_tx' => $item['wallet_tx'],
        ], $rows);
    }

    private function paymentActivityLabel(Payment $payment): string
    {
        if ($payment->business_website_id) {
            $url = trim((string) ($payment->website?->website_url ?? ''));
            if ($url !== '') {
                return 'Website payment · '.Str::limit($url, 48);
            }

            return 'Website payment';
        }

        return match ((string) ($payment->payment_source ?? '')) {
            Payment::SOURCE_BUSINESS_RUBIES_VA => 'Rubies pay-in deposit',
            Payment::SOURCE_WHATSAPP_WALLET => 'WhatsApp checkout',
            Payment::SOURCE_PARTNER_WALLET_API, Payment::SOURCE_TAGINE_APP => 'Partner checkout',
            Payment::SOURCE_EXTERNAL_MEVONPAY, Payment::SOURCE_EXTERNAL_MAVONPAY, Payment::SOURCE_EXTERNAL_SLA => 'External checkout',
            default => 'Checkout payment',
        };
    }

    private function parseBoundary(string $date, string $tz, bool $startOfDay): Carbon
    {
        $parsed = Carbon::parse($date, $tz);

        return $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();
    }
}
