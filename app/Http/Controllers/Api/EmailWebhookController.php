<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcessedEmail;
use App\Models\EmailAccount;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use App\Services\GtbankTransactionParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmailWebhookController extends Controller
{
    /**
     * Receive forwarded email via webhook
     * This endpoint receives emails forwarded from email providers
     * Much faster than IMAP/Gmail API polling!
     */
    public function receive(Request $request)
    {
        try {
            // Zapier sends data in this format:
            // - sender_name
            // - amount
            // - time_sent
            // - email (the email body/content)
            
            // Handle Zapier test connection (empty request)
            $isTestRequest = empty($request->input('email')) && empty($request->input('sender_name'));
            
            if ($isTestRequest) {
                // Return success response for Zapier test connection
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook endpoint is ready. Zapier format: sender_name, amount, time_sent, email',
                    'status' => 'ready',
                    'endpoint' => 'email/webhook',
                    'expected_fields' => ['sender_name', 'amount', 'time_sent', 'email'],
                ], 200);
            }

            // Extract Zapier payload
            $senderName = $request->input('sender_name', '');
            $amount = $request->input('amount', '');
            $timeSent = $request->input('time_sent', now()->toDateTimeString());
            $emailContent = $request->input('email', ''); // This is the full email body/content
            
            // Validate required fields
            if (empty($emailContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: email field is required',
                    'received_fields' => array_keys($request->all()),
                ], 400);
            }

            // Parse email content to extract details
            // Zapier sends the email body, we need to extract from/to/subject from it
            // For now, we'll use the email content as both text and HTML
            $text = $emailContent;
            $html = $emailContent; // Zapier might send HTML, use same for both
            
            // Try to extract email address from content (look for common patterns)
            $fromEmail = '';
            $toEmail = '';
            $subject = 'Transaction Notification';
            
            // Extract from email patterns
            if (preg_match('/from[:\s]+([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})/i', $emailContent, $matches)) {
                $fromEmail = strtolower($matches[1]);
            } elseif (preg_match('/([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})/i', $emailContent, $matches)) {
                $fromEmail = strtolower($matches[1]);
            }
            
            // Default to GTBank if no email found
            if (empty($fromEmail)) {
                $fromEmail = 'alerts@gtbank.com'; // Default bank email
            }
            
            // Extract subject if present
            if (preg_match('/subject[:\s]+(.+?)(?:\n|$)/i', $emailContent, $matches)) {
                $subject = trim($matches[1]);
            }
            
            // Generate message ID from content hash
            $messageId = md5($emailContent . $timeSent . $senderName);
            
            // Use sender_name from Zapier payload
            $fromName = $senderName;

            // Use extracted email address
            // $fromEmail already extracted above

            // Filter out noreply@xtrapay.ng emails
            if (strtolower($fromEmail) === 'noreply@xtrapay.ng') {
                return response()->json([
                    'success' => true,
                    'message' => 'Skipped noreply@xtrapay.ng email',
                    'status' => 'skipped',
                ], 200);
            }

            // Find email account (try to match by sender email domain or use first active)
            $emailAccount = EmailAccount::where('is_active', true)->first();
            
            // Try to find account matching sender domain
            if ($fromEmail) {
                $domain = '@' . explode('@', $fromEmail)[1] ?? '';
                $matchedAccount = EmailAccount::where('is_active', true)
                    ->where(function ($q) use ($fromEmail, $domain) {
                        $q->where('email', 'like', '%' . $domain)
                          ->orWhere('email', 'like', '%' . str_replace('@', '', $domain) . '%');
                    })
                    ->first();
                
                if ($matchedAccount) {
                    $emailAccount = $matchedAccount;
                }
            }

            // Check if email already exists
            $existing = ProcessedEmail::where('message_id', $messageId)
                ->when($emailAccount, function ($q) use ($emailAccount) {
                    $q->where('email_account_id', $emailAccount->id);
                })
                ->exists();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email already processed',
                    'duplicate' => true,
                    'status' => 'duplicate',
                ], 200);
            }

            // Extract payment info
            $matchingService = new PaymentMatchingService(new TransactionLogService());
            $emailData = [
                'subject' => $subject,
                'from' => $fromEmail,
                'text' => $text,
                'html' => $html,
                'date' => $timeSent,
                'email_account_id' => $emailAccount?->id,
            ];

            $extractedInfo = null;
            
            // If Zapier provided amount directly, use it
            if (!empty($amount) && is_numeric($amount)) {
                $extractedInfo = [
                    'amount' => (float) $amount,
                    'sender_name' => $senderName ? strtolower(trim($senderName)) : null,
                    'account_number' => null,
                    'email_subject' => $subject,
                    'email_from' => $fromEmail,
                ];
            } else {
                // Try to extract from email content
                try {
                    $extractedInfo = $matchingService->extractPaymentInfo($emailData);
                    // Override sender_name with Zapier value if provided
                    if ($senderName && empty($extractedInfo['sender_name'])) {
                        $extractedInfo['sender_name'] = strtolower(trim($senderName));
                    }
                } catch (\Exception $e) {
                    Log::debug('Payment info extraction failed', [
                        'error' => $e->getMessage(),
                        'subject' => $subject,
                    ]);
                }
            }

            // Store email (mark as webhook source)
            $processedEmail = ProcessedEmail::create([
                'email_account_id' => $emailAccount?->id,
                'source' => 'webhook', // Mark as webhook source (Zapier)
                'message_id' => $messageId,
                'subject' => $subject,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'text_body' => $text,
                'html_body' => $html,
                'email_date' => $timeSent,
                'amount' => $extractedInfo['amount'] ?? null,
                'sender_name' => $extractedInfo['sender_name'] ?? null,
                'account_number' => $extractedInfo['account_number'] ?? null,
                'extracted_data' => $extractedInfo,
            ]);

            // Check if this is a GTBank transaction
            $gtbankParser = new GtbankTransactionParser();
            if ($gtbankParser->isGtbankTransaction($emailData)) {
                $gtbankTemplate = \App\Models\BankEmailTemplate::where('bank_name', 'GTBank')
                    ->orWhere('bank_name', 'Guaranty Trust Bank')
                    ->active()
                    ->orderBy('priority', 'desc')
                    ->first();

                $gtbankParser->parseTransaction($emailData, $processedEmail, $gtbankTemplate);
            }

            // Try to match payment immediately
            if ($extractedInfo && $extractedInfo['amount']) {
                $matchedPayment = $matchingService->matchEmail($emailData);

                if ($matchedPayment) {
                    // Mark email as matched
                    $processedEmail->markAsMatched($matchedPayment);

                    // Approve payment
                    $matchedPayment->approve([
                        'subject' => $subject,
                        'from' => $fromEmail,
                        'text' => $text,
                        'html' => $html,
                        'date' => $date,
                    ]);

                    // Update business balance
                    if ($matchedPayment->business_id) {
                        $matchedPayment->business->increment('balance', $matchedPayment->amount);
                    }

                    // Dispatch event to send webhook
                    event(new \App\Events\PaymentApproved($matchedPayment));

                    return response()->json([
                        'success' => true,
                        'message' => 'Email received and payment matched',
                        'matched' => true,
                        'payment_id' => $matchedPayment->id,
                        'transaction_id' => $matchedPayment->transaction_id,
                        'status' => 'matched',
                    ], 200);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Email received and stored',
                'matched' => false,
                'email_id' => $processedEmail->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error receiving email webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing email: ' . $e->getMessage(),
            ], 500);
        }
    }
}
