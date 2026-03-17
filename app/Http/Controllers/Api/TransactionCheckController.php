<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TransactionCheckController extends Controller
{
    /**
     * Check transaction status by triggering email monitoring
     * This endpoint allows external sites to trigger email checking
     */
    public function checkTransaction(Request $request): JsonResponse
    {
        try {
            $transactionId = $request->input('transaction_id');
            
            // Run email monitoring to fetch and process new emails
            Artisan::call('payment:monitor-emails');
            $monitorOutput = Artisan::output();
            
            $response = [
                'success' => true,
                'message' => 'Transaction check completed',
                'timestamp' => now()->toISOString(),
            ];
            
            // If transaction_id provided, check its status
            if ($transactionId) {
                $payment = \App\Models\Payment::where('transaction_id', $transactionId)->first();
                
                if ($payment) {
                    $hasReceivedAmount = $payment->received_amount !== null && (float) $payment->received_amount !== (float) $payment->amount;
                    $response['transaction'] = [
                        'transaction_id' => $payment->transaction_id,
                        'status' => $payment->status,
                        // Expected/original amount requested when creating the payment
                        'amount' => (float) $payment->amount,
                        // Actual amount received on the bank/email side (may differ when there is a mismatch or manual/manual approval)
                        'received_amount' => $payment->received_amount !== null ? (float) $payment->received_amount : null,
                        // Convenience flag so integrators can quickly see if amount was updated
                        'is_amount_updated' => $hasReceivedAmount,
                        'matched_at' => $payment->matched_at?->toISOString(),
                    ];
                } else {
                    $response['transaction'] = null;
                    $response['message'] = 'Transaction not found';
                }
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error checking transaction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking transaction: ' . $e->getMessage(),
            ], 500);
        }
    }
}
