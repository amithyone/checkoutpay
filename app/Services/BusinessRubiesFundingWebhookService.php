<?php

namespace App\Services;

use App\Events\PaymentApproved;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Credits a merchant {@see Business} when MevonPay sends funding.success to their Rubies business VA.
 */
class BusinessRubiesFundingWebhookService
{
    /**
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    public function tryFulfillFromWebhook(
        string $accountNumber,
        float $amount,
        string $reference,
        array $webhookMeta = [],
    ): bool {
        if ($amount <= 0) {
            return false;
        }

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            return false;
        }

        $handled = false;

        DB::transaction(function () use ($accountNumber, $amount, $reference, $webhookMeta, &$handled) {
            $business = Business::query()
                ->where('rubies_business_account_number', $accountNumber)
                ->lockForUpdate()
                ->first();

            if (! $business || trim((string) $business->rubies_business_account_number) === '') {
                return;
            }

            if ($reference !== '') {
                $existing = Payment::query()
                    ->where('business_id', $business->id)
                    ->where('account_number', $accountNumber)
                    ->where('external_reference', $reference)
                    ->where('payment_source', Payment::SOURCE_BUSINESS_RUBIES_VA)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->status === Payment::STATUS_APPROVED) {
                        $handled = true;

                        return;
                    }
                    $this->finalizeRubiesDeposit($existing, $business, $amount, $reference, $webhookMeta);
                    $handled = true;

                    return;
                }
            }

            $baseUrl = rtrim((string) config('app.url'), '/');
            if ($baseUrl === '') {
                $baseUrl = 'https://localhost';
            }

            $sender = trim((string) ($webhookMeta['sender'] ?? ''));
            $bank = trim((string) ($webhookMeta['bank_name'] ?? ''));

            $payment = Payment::query()->create([
                'transaction_id' => 'BRB'.strtoupper(str_replace('-', '', (string) Str::uuid())),
                'amount' => $amount,
                'payer_name' => $sender !== '' ? strtolower($sender) : strtolower('rubies deposit '.$business->id),
                'bank' => $bank !== '' ? $bank : null,
                'webhook_url' => $baseUrl.'/internal/business-rubies-funding',
                'account_number' => $accountNumber,
                'business_id' => $business->id,
                'status' => Payment::STATUS_PENDING,
                'payment_source' => Payment::SOURCE_BUSINESS_RUBIES_VA,
                'external_reference' => $reference !== '' ? $reference : null,
                'expires_at' => null,
                'email_data' => [
                    'rubies_business_va' => true,
                    'mevonpay_reference' => $reference,
                    'reported_amount' => $amount,
                ],
            ]);

            $this->finalizeRubiesDeposit($payment, $business, $amount, $reference, $webhookMeta);
            $handled = true;
        });

        return $handled;
    }

    /**
     * @param  array{sender?: string, bank_name?: string}  $webhookMeta
     */
    private function finalizeRubiesDeposit(
        Payment $payment,
        Business $business,
        float $amount,
        string $reference,
        array $webhookMeta,
    ): void {
        $payment->approve([
            'source' => 'mevonpay_webhook',
            'reference' => $reference,
            'account_number' => $payment->account_number,
            'amount' => $amount,
            'bank' => (string) ($webhookMeta['bank_name'] ?? ''),
            'payer_name' => (string) ($webhookMeta['sender'] ?? ''),
            'timestamp' => now()->toISOString(),
            'rubies_business_va' => true,
        ], false, $amount, null);

        $payment->update([
            'payment_source' => Payment::SOURCE_EXTERNAL_MEVONPAY,
            'external_reference' => $reference !== '' ? $reference : $payment->external_reference,
        ]);

        $business->refresh();

        if ($payment->business_id) {
            $business->incrementBalanceWithCharges($payment->amount, $payment, $amount);
            $business->refresh();
            $business->notify(new \App\Notifications\NewDepositNotification($payment));
            $business->triggerAutoWithdrawal();
        }

        $payment->refresh();
        $payment->load(['business.websites', 'website']);
        event(new PaymentApproved($payment));

        Log::info('business.rubies_funding_webhook.credited', [
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'reference' => $reference,
        ]);
    }
}
