<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Business;
use App\Models\EmailAccount;
use App\Services\PaymentService;
use App\Services\TransactionLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestTransactionController extends Controller
{
    protected $paymentService;
    protected $logService;

    public function __construct(PaymentService $paymentService, TransactionLogService $logService)
    {
        $this->paymentService = $paymentService;
        $this->logService = $logService;
    }

    /**
     * Show the test transaction page
     */
    public function index()
    {
        $businesses = Business::where('is_active', true)->get();
        $emailAccounts = EmailAccount::where('is_active', true)->get();
        
        return view('admin.test-transaction', compact('businesses', 'emailAccounts'));
    }

    /**
     * Create a test payment
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payer_name' => 'nullable|string|max:255',
            'business_id' => 'required|exists:businesses,id',
            'bank' => 'nullable|string|max:255',
        ]);

        try {
            $business = Business::findOrFail($validated['business_id']);
            
            $paymentData = [
                'amount' => $validated['amount'],
                'payer_name' => $validated['payer_name'] ?? null,
                'bank' => $validated['bank'] ?? null,
                'webhook_url' => $business->webhook_url ?? 'https://webhook.site/test',
            ];

            $payment = $this->paymentService->createPayment($paymentData, $business);

            // Load account number details
            $payment->load('accountNumberDetails');
            
            return response()->json([
                'success' => true,
                'message' => 'Test payment created successfully',
                'payment' => [
                    'id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'account_number' => $payment->account_number,
                    'account_name' => $payment->accountNumberDetails->account_name ?? null,
                    'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                    'created_at' => $payment->created_at->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating test payment', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status and logs
     */
    public function getStatus($transactionId)
    {
        try {
            $payment = Payment::with('accountNumberDetails')
                ->where('transaction_id', $transactionId)
                ->firstOrFail();
            
            // Get transaction logs for this payment
            $logs = \App\Models\TransactionLog::where('transaction_id', $transactionId)
                ->orWhere('payment_id', $payment->id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Get payment status
            $status = [
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'payer_name' => $payment->payer_name,
                'account_number' => $payment->account_number,
                'account_name' => $payment->accountNumberDetails->account_name ?? null,
                'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                'created_at' => $payment->created_at->toDateTimeString(),
                'matched_at' => $payment->matched_at?->toDateTimeString(),
                'approved_at' => $payment->approved_at?->toDateTimeString(),
                'expires_at' => $payment->expires_at?->toDateTimeString(),
            ];

            // Determine current step
            $currentStep = $this->getCurrentStep($payment, $logs);

            return response()->json([
                'success' => true,
                'payment' => $status,
                'logs' => $logs->map(function ($log) {
                    return [
                        'event_type' => $log->event_type,
                        'description' => $log->description,
                        'created_at' => $log->created_at->toDateTimeString(),
                        'metadata' => $log->metadata,
                    ];
                }),
                'current_step' => $currentStep,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Manually trigger email check
     */
    public function checkEmail(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
        ]);

        try {
            // Run email monitoring command
            \Artisan::call('payment:monitor-emails');
            
            $output = \Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Email check completed',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking emails: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine current step in the process
     */
    protected function getCurrentStep(Payment $payment, $logs): string
    {
        if ($payment->status === Payment::STATUS_APPROVED) {
            return 'completed';
        }
        
        if ($payment->status === Payment::STATUS_REJECTED) {
            return 'rejected';
        }

        // Check logs to determine step
        $logTypes = $logs->pluck('event_type')->toArray();
        
        if (in_array('webhook_sent', $logTypes)) {
            return 'webhook_sent';
        }
        
        if (in_array('payment_approved', $logTypes)) {
            return 'payment_approved';
        }
        
        if (in_array('payment_matched', $logTypes)) {
            return 'payment_matched';
        }
        
        if (in_array('email_received', $logTypes)) {
            return 'email_received';
        }
        
        if (in_array('account_assigned', $logTypes)) {
            return 'account_assigned';
        }
        
        if (in_array('payment_requested', $logTypes)) {
            return 'payment_requested';
        }

        return 'pending';
    }
}
