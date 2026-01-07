<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessedEmail;
use Illuminate\Http\Request;

class ProcessedEmailController extends Controller
{
    /**
     * Display inbox of stored emails
     */
    public function index(Request $request)
    {
        $query = ProcessedEmail::with('emailAccount', 'matchedPayment')
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'matched') {
                $query->where('is_matched', true);
            } elseif ($request->status === 'unmatched') {
                $query->where('is_matched', false);
            }
        }

        // Filter by email account
        if ($request->has('email_account_id')) {
            $query->where('email_account_id', $request->email_account_id);
        }

        // Search by Subject, From, Amount, and Sender
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                // Search in subject
                $q->where('subject', 'like', "%{$search}%")
                    // Search in from email
                    ->orWhere('from_email', 'like', "%{$search}%")
                    // Search in from name
                    ->orWhere('from_name', 'like', "%{$search}%")
                    // Search in sender name
                    ->orWhere('sender_name', 'like', "%{$search}%");
                
                // If search looks like a number, also search in amount
                $numericSearch = preg_replace('/[^0-9.]/', '', $search);
                if (is_numeric($numericSearch) && $numericSearch > 0) {
                    $amount = (float) $numericSearch;
                    // Allow small tolerance for amount matching (handles formatting like 1000 vs 1000.00)
                    $q->orWhereBetween('amount', [$amount - 0.01, $amount + 0.01]);
                }
            });
        }

        $emails = $query->paginate(20);

        // Statistics
        $stats = [
            'total' => ProcessedEmail::count(),
            'matched' => ProcessedEmail::where('is_matched', true)->count(),
            'unmatched' => ProcessedEmail::where('is_matched', false)->count(),
        ];

        // Get email accounts for filter
        $emailAccounts = \App\Models\EmailAccount::all();

        return view('admin.processed-emails.index', compact('emails', 'stats', 'emailAccounts'));
    }

    /**
     * Show email details
     */
    public function show(ProcessedEmail $processedEmail)
    {
        $processedEmail->load('emailAccount', 'matchedPayment.business');
        
        return view('admin.processed-emails.show', compact('processedEmail'));
    }

    /**
     * Check match for a stored email against pending payments
     */
    public function checkMatch(ProcessedEmail $processedEmail)
    {
        $matchingService = new \App\Services\PaymentMatchingService(
            new \App\Services\TransactionLogService()
        );
        
        $result = $matchingService->recheckStoredEmail($processedEmail);
        
        // If a match is found, approve the payment
        $matchedPayment = null;
        foreach ($result['matches'] as $match) {
            if ($match['matched'] && $match['payment']) {
                $matchedPayment = $match['payment'];
                
                // Mark email as matched
                $processedEmail->markAsMatched($matchedPayment);
                
                // Approve payment
                $matchedPayment->approve([
                    'subject' => $processedEmail->subject,
                    'from' => $processedEmail->from_email,
                    'text' => $processedEmail->text_body,
                    'html' => $processedEmail->html_body,
                    'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                ]);
                
                // Update business balance
                if ($matchedPayment->business_id) {
                    $matchedPayment->business->increment('balance', $matchedPayment->amount);
                }
                
                // Dispatch event to send webhook
                event(new \App\Events\PaymentApproved($matchedPayment));
                
                break;
            }
        }
        
        return response()->json([
            'success' => true,
            'matched' => $matchedPayment !== null,
            'payment' => $matchedPayment ? [
                'id' => $matchedPayment->id,
                'transaction_id' => $matchedPayment->transaction_id,
                'amount' => $matchedPayment->amount,
            ] : null,
            'matches' => $result['matches'],
            'extracted_info' => $result['extracted_info'] ?? null,
            'message' => $matchedPayment 
                ? 'Payment matched and approved successfully!' 
                : 'No matching payment found. Check the matches below for details.',
        ]);
    }
}
