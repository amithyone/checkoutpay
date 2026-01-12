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
        try {
            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            
            $result = $matchingService->recheckStoredEmail($processedEmail);
            
            // If a match is found, approve the payment
            $matchedPayment = null;
            if (isset($result['matches']) && is_array($result['matches'])) {
                foreach ($result['matches'] as $match) {
                    if (isset($match['matched']) && $match['matched'] && isset($match['payment']) && $match['payment']) {
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
                            'sender_name' => $processedEmail->sender_name, // Map sender_name to payer_name
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
            }
            
            return response()->json([
                'success' => true,
                'matched' => $matchedPayment !== null,
                'payment' => $matchedPayment ? [
                    'id' => $matchedPayment->id,
                    'transaction_id' => $matchedPayment->transaction_id,
                    'amount' => $matchedPayment->amount,
                ] : null,
                'matches' => $result['matches'] ?? [],
                'extracted_info' => $result['extracted_info'] ?? null,
                'message' => $matchedPayment 
                    ? 'Payment matched and approved successfully!' 
                    : 'No matching payment found. Check the matches below for details.',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in checkMatch', [
                'email_id' => $processedEmail->id,
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

    /**
     * Update sender name for a processed email
     */
    public function updateName(Request $request, ProcessedEmail $processedEmail)
    {
        $request->validate([
            'sender_name' => 'required|string|max:255',
        ]);

        try {
            $senderName = strtolower(trim($request->sender_name));
            
            // Get current extracted_data or initialize empty array
            $extractedData = $processedEmail->extracted_data ?? [];
            
            // Update sender_name in extracted_data
            $extractedData['sender_name'] = $senderName;
            
            // Also update if it's nested in a 'data' key (some extraction methods use this structure)
            if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                $extractedData['data']['sender_name'] = $senderName;
            }
            
            $processedEmail->update([
                'sender_name' => $senderName,
                'extracted_data' => $extractedData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sender name and extracted data updated successfully',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating sender name', [
                'email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating sender name: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update sender name and rematch the email
     */
    public function updateAndRematch(Request $request, ProcessedEmail $processedEmail)
    {
        $request->validate([
            'sender_name' => 'nullable|string|max:255',
        ]);

        try {
            $senderName = !empty($request->sender_name) ? strtolower(trim($request->sender_name)) : null;
            
            // SECONDARY TRY: If sender_name is empty or not provided, try extracting from text snippet (first 500 chars)
            if (empty($senderName) && !empty($processedEmail->text_body)) {
                $textSnippet = mb_substr($processedEmail->text_body, 0, 500);
                $nameExtractor = new \App\Services\SenderNameExtractor();
                $extractedName = $nameExtractor->extractFromText($textSnippet, $processedEmail->subject ?? '');
                
                if (!empty($extractedName)) {
                    $senderName = $extractedName;
                    \Illuminate\Support\Facades\Log::info('Extracted sender name from text snippet on rematch', [
                        'email_id' => $processedEmail->id,
                        'extracted_name' => $extractedName,
                    ]);
                }
            }
            
            // Get current extracted_data or initialize empty array
            $extractedData = $processedEmail->extracted_data ?? [];
            
            // Update sender_name in extracted_data
            $extractedData['sender_name'] = $senderName;
            
            // Also update if it's nested in a 'data' key (some extraction methods use this structure)
            if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                $extractedData['data']['sender_name'] = $senderName;
            }
            
            // Update the sender name and extracted_data
            $processedEmail->update([
                'sender_name' => $senderName,
                'extracted_data' => $extractedData,
            ]);

            // Refresh to get updated data
            $processedEmail->refresh();

            // Now rematch using the matching service
            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            
            $result = $matchingService->recheckStoredEmail($processedEmail);
            
            // If a match is found, approve the payment
            $matchedPayment = null;
            if (isset($result['matches']) && is_array($result['matches'])) {
                foreach ($result['matches'] as $match) {
                    if (isset($match['matched']) && $match['matched'] && isset($match['payment']) && $match['payment']) {
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
                            'sender_name' => $processedEmail->sender_name, // Map sender_name to payer_name
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
            }

            // Get latest match reason if no match found
            $latestReason = null;
            if (!$matchedPayment && isset($result['matches']) && is_array($result['matches'])) {
                foreach ($result['matches'] as $match) {
                    if (isset($match['reason'])) {
                        $latestReason = $match['reason'];
                        break;
                    }
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
                'message' => $matchedPayment 
                    ? 'Sender name updated and payment matched successfully!' 
                    : 'Sender name updated. No matching payment found.',
                'latest_reason' => $latestReason,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating and rematching', [
                'email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating and rematching: ' . $e->getMessage(),
            ], 500);
        }
    }
}
