<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TransactionCheckController extends Controller
{
    /**
     * Check for transaction updates via API
     * This endpoint can be called by external sites to trigger email checking
     */
    public function checkTransaction(Request $request)
    {
        try {
            $transactionId = $request->input('transaction_id');
            
            // If transaction_id provided, check that specific transaction
            if ($transactionId) {
                $payment = Payment::where('transaction_id', $transactionId)->first();
                
                if (!$payment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaction not found',
                    ], 404);
                }
                
                // If payment is already approved/rejected, return status
                if ($payment->status !== Payment::STATUS_PENDING) {
                    return response()->json([
                        'success' => true,
                        'transaction_id' => $payment->transaction_id,
                        'status' => $payment->status,
                        'message' => 'Transaction already processed',
                    ]);
                }
                
                // Check stored emails for this specific payment
                $matchingService = new PaymentMatchingService(new TransactionLogService());
                
                // Get unmatched stored emails with matching amount
                $storedEmails = \App\Models\ProcessedEmail::unmatched()
                    ->withAmount($payment->amount)
                    ->get();
                
                $matched = false;
                foreach ($storedEmails as $storedEmail) {
                    // Re-extract from html_body
                    $emailData = [
                        'subject' => $storedEmail->subject,
                        'from' => $storedEmail->from_email,
                        'text' => $storedEmail->text_body ?? '',
                        'html' => $storedEmail->html_body ?? '',
                        'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                    ];
                    
                    $extractionResult = $matchingService->extractPaymentInfo($emailData);
                    $extractedInfo = $extractionResult['data'] ?? null;
                    
                    if ($extractedInfo && isset($extractedInfo['amount']) && $extractedInfo['amount']) {
                        $emailDate = $storedEmail->email_date ? Carbon::parse($storedEmail->email_date) : null;
                        $match = $matchingService->matchPayment($payment, $extractedInfo, $emailDate);
                        
                        if ($match['matched']) {
                            // Mark email as matched
                            $storedEmail->markAsMatched($payment);
                            
                            // Approve payment
                            $payment->approve([
                                'subject' => $storedEmail->subject,
                                'from' => $storedEmail->from_email,
                                'text' => $storedEmail->text_body,
                                'html' => $storedEmail->html_body,
                                'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                                'sender_name' => $storedEmail->sender_name, // Map sender_name to payer_name
                            ]);
                            
                            // Update business balance
                            if ($payment->business_id) {
                                $payment->business->increment('balance', $payment->amount);
                            }
                            
                            // Dispatch event to send webhook
                            event(new \App\Events\PaymentApproved($payment));
                            
                            $matched = true;
                            break;
                        }
                    }
                }
                
                // If not matched, trigger global match to check all emails and payments
                if (!$matched) {
                    try {
                        // Trigger global match in background
                        \Illuminate\Support\Facades\Http::timeout(1)->get(url('/cron/global-match'))->throw();
                    } catch (\Exception $e) {
                        // Silently fail - don't block the response
                        Log::debug('Global match trigger failed (non-critical)', [
                            'transaction_id' => $transactionId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // Also fetch new emails
                    Artisan::call('payment:monitor-emails');
                }
                
                // Refresh payment
                $payment->refresh();
                
                return response()->json([
                    'success' => true,
                    'transaction_id' => $payment->transaction_id,
                    'status' => $payment->status,
                    'matched' => $matched,
                    'message' => $matched ? 'Payment matched and approved' : 'No matching email found yet',
                ]);
            }
            
            // If no transaction_id, just trigger email fetch
            Artisan::call('payment:monitor-emails');
            $output = Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Email check triggered successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in transaction check API', [
                'error' => $e->getMessage(),
                'transaction_id' => $request->input('transaction_id'),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking transaction: ' . $e->getMessage(),
            ], 500);
        }
    }
}
