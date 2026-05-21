<?php

namespace App\Services\Support;

use App\Models\Payment;
use Illuminate\Support\Str;

final class SupportPaymentLookupService
{
    /**
     * @return array{ok: bool, message?: string, payment?: Payment, summary?: array<string, mixed>}
     */
    public function lookup(string $transactionId): array
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return ['ok' => false, 'message' => 'Bank session ID is required.'];
        }

        $payment = Payment::query()
            ->where('transaction_id', $transactionId)
            ->first();

        if (! $payment) {
            return ['ok' => false, 'message' => 'No payment found for this reference. Our team can still match it from your bank session ID.'];
        }

        return [
            'ok' => true,
            'payment' => $payment,
            'summary' => $this->publicSummary($payment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSummary(Payment $payment): array
    {
        $statusLabel = match ($payment->status) {
            Payment::STATUS_PENDING => $payment->isExpired() ? 'expired' : 'pending',
            Payment::STATUS_APPROVED => 'approved',
            Payment::STATUS_REJECTED => 'rejected',
            default => (string) $payment->status,
        };

        return [
            'transaction_id' => $payment->transaction_id,
            'amount' => (float) $payment->amount,
            'amount_formatted' => $this->formatMoney((float) $payment->amount),
            'status' => $payment->status,
            'status_label' => $statusLabel,
            'is_pending' => $payment->isPending() && ! $payment->isExpired(),
            'is_expired' => $payment->isExpired(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'matched_at' => $payment->matched_at?->toIso8601String(),
            'received_amount' => $payment->received_amount !== null ? (float) $payment->received_amount : null,
            'payer_name' => $payment->payer_name ? Str::limit((string) $payment->payer_name, 40) : null,
        ];
    }

    public function formatMoney(float $amount): string
    {
        return '₦'.number_format($amount, 2);
    }

    /**
     * Build admin-ready opening message for payment-related issues.
     */
    public function buildPaymentIssueMessage(
        string $issueLabel,
        string $transactionId,
        ?float $reportedAmount,
        ?Payment $payment,
        ?string $extraNote = null
    ): string {
        $lines = [
            '[Quick support — '.$issueLabel.']',
            'Bank session ID: '.$transactionId,
        ];

        if ($reportedAmount !== null) {
            $lines[] = 'Amount paid (customer): '.$this->formatMoney($reportedAmount);
        }

        if ($payment) {
            $lines[] = 'System amount: '.$this->formatMoney((float) $payment->amount);
            $lines[] = 'Status: '.($payment->isExpired() ? 'expired (was pending)' : $payment->status);
            if ($payment->received_amount !== null) {
                $lines[] = 'Received amount: '.$this->formatMoney((float) $payment->received_amount);
            }
            if ($payment->payer_name) {
                $lines[] = 'Payer name on payment: '.$payment->payer_name;
            }
            if ($reportedAmount !== null && abs((float) $payment->amount - $reportedAmount) > 0.01) {
                $lines[] = 'Note: reported amount differs from session amount.';
            }
        } else {
            $lines[] = 'Payment record: not found in system (admin: search by bank session ID or narration).';
        }

        if ($extraNote !== null && trim($extraNote) !== '') {
            $lines[] = 'Customer note: '.trim($extraNote);
        }

        return implode("\n", $lines);
    }
}
