<?php

namespace App\Services;

use App\Models\TransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionLogService
{
    /**
     * Log a transaction event
     */
    public function log(
        string $transactionId,
        string $eventType,
        ?string $description = null,
        ?array $metadata = null,
        ?int $paymentId = null,
        ?int $businessId = null,
        ?Request $request = null
    ): TransactionLog {
        $log = TransactionLog::create([
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'business_id' => $businessId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        // Also log to Laravel log for debugging
        Log::info("Transaction Log: {$eventType}", [
            'transaction_id' => $transactionId,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        return $log;
    }

    /**
     * Log payment request
     */
    public function logPaymentRequest($payment, ?Request $request = null): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_PAYMENT_REQUESTED,
            description: "Payment request created: ₦{$payment->amount}",
            metadata: [
                'amount' => (float) $payment->amount,
                'payer_name' => $payment->payer_name,
                'bank' => $payment->bank,
                'webhook_url' => $payment->webhook_url,
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id,
            request: $request
        );
    }

    /**
     * Log account assignment
     */
    public function logAccountAssignment($payment, $accountNumber): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_ACCOUNT_ASSIGNED,
            description: "Account number assigned: {$accountNumber->account_number}",
            metadata: [
                'account_number' => $accountNumber->account_number,
                'account_name' => $accountNumber->account_name,
                'bank_name' => $accountNumber->bank_name,
                'is_pool' => $accountNumber->is_pool,
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log email received
     */
    public function logEmailReceived(string $transactionId, array $emailData): TransactionLog
    {
        return $this->log(
            transactionId: $transactionId,
            eventType: TransactionLog::EVENT_EMAIL_RECEIVED,
            description: "Email received for payment matching",
            metadata: [
                'email_subject' => $emailData['subject'] ?? null,
                'email_from' => $emailData['from'] ?? null,
            ]
        );
    }

    /**
     * Log payment matched
     */
    public function logPaymentMatched($payment, array $emailData): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_PAYMENT_MATCHED,
            description: "Payment matched with email data",
            metadata: [
                'matched_amount' => $emailData['amount'] ?? null,
                'matched_sender' => $emailData['sender_name'] ?? null,
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log payment approved
     */
    public function logPaymentApproved($payment): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_PAYMENT_APPROVED,
            description: "Payment approved: ₦{$payment->amount}",
            metadata: [
                'amount' => (float) $payment->amount,
                'matched_at' => $payment->matched_at?->toISOString(),
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log payment rejected
     */
    public function logPaymentRejected($payment, string $reason): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_PAYMENT_REJECTED,
            description: "Payment rejected: {$reason}",
            metadata: [
                'reason' => $reason,
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log payment expired
     */
    public function logPaymentExpired($payment): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_PAYMENT_EXPIRED,
            description: "Payment expired - no matching transfer received",
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log webhook sent
     */
    public function logWebhookSent($payment, array $responseData): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_WEBHOOK_SENT,
            description: "Webhook sent successfully",
            metadata: [
                'webhook_url' => $payment->webhook_url,
                'status_code' => $responseData['status_code'] ?? null,
                'response' => $responseData['response'] ?? null,
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log webhook failed
     */
    public function logWebhookFailed($payment, string $error): TransactionLog
    {
        return $this->log(
            transactionId: $payment->transaction_id,
            eventType: TransactionLog::EVENT_WEBHOOK_FAILED,
            description: "Webhook failed: {$error}",
            metadata: [
                'webhook_url' => $payment->webhook_url,
                'error' => $error,
            ],
            paymentId: $payment->id,
            businessId: $payment->business_id
        );
    }

    /**
     * Log withdrawal request
     */
    public function logWithdrawalRequest($withdrawal, ?Request $request = null): TransactionLog
    {
        return $this->log(
            transactionId: "WDR-{$withdrawal->id}",
            eventType: TransactionLog::EVENT_WITHDRAWAL_REQUESTED,
            description: "Withdrawal requested: ₦{$withdrawal->amount}",
            metadata: [
                'amount' => (float) $withdrawal->amount,
                'account_number' => $withdrawal->account_number,
                'account_name' => $withdrawal->account_name,
                'bank_name' => $withdrawal->bank_name,
            ],
            businessId: $withdrawal->business_id,
            request: $request
        );
    }

    /**
     * Log withdrawal approved
     */
    public function logWithdrawalApproved($withdrawal): TransactionLog
    {
        return $this->log(
            transactionId: "WDR-{$withdrawal->id}",
            eventType: TransactionLog::EVENT_WITHDRAWAL_APPROVED,
            description: "Withdrawal approved: ₦{$withdrawal->amount}",
            metadata: [
                'amount' => (float) $withdrawal->amount,
            ],
            businessId: $withdrawal->business_id
        );
    }

    /**
     * Log withdrawal rejected
     */
    public function logWithdrawalRejected($withdrawal, string $reason): TransactionLog
    {
        return $this->log(
            transactionId: "WDR-{$withdrawal->id}",
            eventType: TransactionLog::EVENT_WITHDRAWAL_REJECTED,
            description: "Withdrawal rejected: {$reason}",
            metadata: [
                'reason' => $reason,
            ],
            businessId: $withdrawal->business_id
        );
    }

    /**
     * Log withdrawal processed
     */
    public function logWithdrawalProcessed($withdrawal): TransactionLog
    {
        return $this->log(
            transactionId: "WDR-{$withdrawal->id}",
            eventType: TransactionLog::EVENT_WITHDRAWAL_PROCESSED,
            description: "Withdrawal marked as processed",
            businessId: $withdrawal->business_id
        );
    }
}
