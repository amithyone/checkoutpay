<?php

namespace App\Http\Controllers\Api;

use App\Events\PaymentApproved;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MevonPayWebhookController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        $secret = (string) env('MEVONPAY_WEBHOOK_SECRET', (string) env('SLA_WEBHOOK_SECRET', (string) env('MAVONPAY_WEBHOOK_SECRET', '')));
        if ($secret !== '') {
            $token = (string) preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', ''));
            if (!hash_equals($secret, $token)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
        }

        $payload = $request->all();
        $event = (string) data_get($payload, 'event', '');
        if ($event !== 'funding.success') {
            return response()->json(['success' => true, 'message' => 'Ignored']);
        }

        $accountNumber = trim((string) data_get($payload, 'data.account_number', ''));
        $amount = (float) data_get($payload, 'data.amount', 0);
        $reference = (string) data_get($payload, 'data.reference', '');

        if ($accountNumber === '' || $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
        }

        $payment = Payment::where('status', Payment::STATUS_PENDING)
            ->whereIn('payment_source', [
                Payment::SOURCE_EXTERNAL_MEVONPAY,
                Payment::SOURCE_EXTERNAL_SLA,
                Payment::SOURCE_EXTERNAL_MAVONPAY,
            ])
            ->where('account_number', $accountNumber)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->first();

        if (! $payment) {
            Log::warning('MEVONPAY webhook could not find pending payment by account number', [
                'account_number' => $accountNumber,
                'reference' => $reference,
            ]);
            return response()->json(['success' => true, 'message' => 'No pending payment']);
        }

        $payment->approve([
            'source' => 'mevonpay_webhook',
            'reference' => $reference,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'bank' => (string) data_get($payload, 'data.bank_name', ''),
            'payer_name' => (string) data_get($payload, 'data.sender', ''),
            'timestamp' => (string) data_get($payload, 'data.timestamp', now()->toISOString()),
        ], false, $amount, null);

        $payment->update([
            'payment_source' => Payment::SOURCE_EXTERNAL_MEVONPAY,
            'external_reference' => $reference !== '' ? $reference : null,
        ]);

        if ($payment->business_id) {
            $payment->business->incrementBalanceWithCharges($payment->amount, $payment, $amount);
            $payment->business->refresh();
            $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
            $payment->business->triggerAutoWithdrawal();
        }

        $payment->refresh();
        $payment->load(['business.websites', 'website']);
        event(new PaymentApproved($payment));

        return response()->json(['success' => true]);
    }
}
