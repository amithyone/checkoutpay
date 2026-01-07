<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::with('business')->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $payments = $query->paginate(20);

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load('business', 'accountNumberDetails');
        return view('admin.payments.show', compact('payment'));
    }

    /**
     * Check match for a payment against stored emails
     */
    public function checkMatch(Payment $payment)
    {
        try {
            // Only check if payment is pending
            if ($payment->status !== Payment::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is already ' . $payment->status,
                ], 400);
            }

            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );

            // Get unmatched stored emails with matching amount
            $storedEmails = \App\Models\ProcessedEmail::unmatched()
                ->withAmount($payment->amount)
                ->get();

            $matchedEmail = null;
            $matches = [];

            foreach ($storedEmails as $storedEmail) {
                // Re-extract payment info from html_body
                $emailData = [
                    'subject' => $storedEmail->subject,
                    'from' => $storedEmail->from_email,
                    'text' => $storedEmail->text_body ?? '',
                    'html' => $storedEmail->html_body ?? '',
                    'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                ];

                $extractedInfo = $matchingService->extractPaymentInfo($emailData);

                if (!$extractedInfo || !$extractedInfo['amount']) {
                    continue;
                }

                $match = $matchingService->matchPayment($payment, $extractedInfo, $storedEmail->email_date);

                $matches[] = [
                    'email_id' => $storedEmail->id,
                    'email_subject' => $storedEmail->subject,
                    'email_from' => $storedEmail->from_email,
                    'matched' => $match['matched'],
                    'reason' => $match['reason'],
                    'time_diff_minutes' => $storedEmail->email_date && $payment->created_at 
                        ? abs($storedEmail->email_date->diffInMinutes($payment->created_at))
                        : null,
                ];

                if ($match['matched']) {
                    $matchedEmail = $storedEmail;

                    // Mark email as matched
                    $storedEmail->markAsMatched($payment);

                    // Approve payment
                    $payment->approve([
                        'subject' => $storedEmail->subject,
                        'from' => $storedEmail->from_email,
                        'text' => $storedEmail->text_body,
                        'html' => $storedEmail->html_body,
                        'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                    ]);

                    // Update business balance
                    if ($payment->business_id) {
                        $payment->business->increment('balance', $payment->amount);
                    }

                    // Dispatch event to send webhook
                    event(new \App\Events\PaymentApproved($payment));

                    break;
                }
            }

            return response()->json([
                'success' => true,
                'matched' => $matchedEmail !== null,
                'payment' => [
                    'id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                ],
                'email' => $matchedEmail ? [
                    'id' => $matchedEmail->id,
                    'subject' => $matchedEmail->subject,
                    'from_email' => $matchedEmail->from_email,
                ] : null,
                'matches' => $matches,
                'message' => $matchedEmail 
                    ? 'Payment matched and approved successfully!' 
                    : 'No matching email found. Check the matches below for details.',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in checkMatch for payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking match: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
