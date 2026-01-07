<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcessedEmail;
use App\Models\EmailAccount;
use App\Models\WhitelistedEmailAddress;
use App\Models\ZapierLog;
use App\Models\Setting;
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
            // SECURITY: Verify Zapier webhook secret
            $webhookSecret = Setting::get('zapier_webhook_secret');
            if ($webhookSecret) {
                $providedSecret = $request->header('X-Zapier-Secret') ?? $request->input('webhook_secret');
                
                if (empty($providedSecret) || $providedSecret !== $webhookSecret) {
                    Log::warning('Unauthorized webhook request - invalid or missing secret', [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'has_secret_header' => $request->hasHeader('X-Zapier-Secret'),
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Invalid webhook secret',
                        'status' => 'unauthorized',
                    ], 401);
                }
            }
            
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
                    'security_note' => $webhookSecret ? 'Webhook secret authentication is enabled' : 'Webhook secret authentication is disabled - enable it in admin settings',
                ], 200);
            }

            // Extract Zapier payload
            $senderName = $request->input('sender_name', '');
            $amount = $request->input('amount', '');
            $timeSent = $request->input('time_sent', now()->toDateTimeString());
            $emailContent = $request->input('email', ''); // This is the full email body/content
            
            // LOG: Save all incoming payloads to zapier_logs
            $zapierLog = ZapierLog::create([
                'payload' => $request->all(), // Store full payload
                'sender_name' => $senderName,
                'amount' => !empty($amount) && is_numeric($amount) ? (float) $amount : null,
                'time_sent' => $timeSent,
                'email_content' => $emailContent,
                'status' => 'received',
                'status_message' => 'Payload received from Zapier',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // Validate required fields
            if (empty($emailContent)) {
                $zapierLog->update([
                    'status' => 'error',
                    'status_message' => 'Validation failed: email field is required',
                    'error_details' => 'Received fields: ' . implode(', ', array_keys($request->all())),
                ]);
                
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
            
            // SECURITY: Check if email is whitelisted
            if (empty($fromEmail)) {
                // Try to extract from email content more aggressively
                if (preg_match('/<([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})>/i', $emailContent, $matches)) {
                    $fromEmail = strtolower($matches[1]);
                }
            }
            
            // If still no email found, reject the request
            if (empty($fromEmail)) {
                $zapierLog->update([
                    'status' => 'error',
                    'status_message' => 'Could not extract sender email address from email content',
                    'error_details' => 'Email content preview: ' . substr($emailContent, 0, 500),
                ]);
                
                Log::warning('Webhook request rejected - could not extract sender email', [
                    'ip' => $request->ip(),
                    'email_content_preview' => substr($emailContent, 0, 200),
                    'zapier_log_id' => $zapierLog->id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Could not extract sender email address from email content',
                    'status' => 'validation_error',
                ], 400);
            }
            
            // Update log with extracted email
            $zapierLog->update(['extracted_from_email' => $fromEmail]);
            
            // Check whitelist
            if (!WhitelistedEmailAddress::isWhitelisted($fromEmail)) {
                $zapierLog->update([
                    'status' => 'rejected',
                    'status_message' => 'Email address not whitelisted: ' . $fromEmail,
                    'error_details' => 'Add this email address to the whitelist in admin panel: Settings > Whitelisted Emails',
                ]);
                
                Log::warning('Webhook request rejected - email not whitelisted', [
                    'from_email' => $fromEmail,
                    'ip' => $request->ip(),
                    'zapier_log_id' => $zapierLog->id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Email address not whitelisted: ' . $fromEmail,
                    'status' => 'not_whitelisted',
                    'from_email' => $fromEmail,
                    'help' => 'Add this email address to the whitelist in admin panel: Settings > Whitelisted Emails',
                ], 403);
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
                $zapierLog->update([
                    'status' => 'rejected',
                    'status_message' => 'Skipped noreply@xtrapay.ng email',
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Skipped noreply@xtrapay.ng email',
                    'status' => 'skipped',
                ], 200);
            }

            // For Zapier webhook, email_account_id is not required (set to null)
            // We use Zapier logs instead of email accounts for tracking
            $emailAccount = null;

            // Check if email already exists (by message_id)
            $existing = ProcessedEmail::where('message_id', $messageId)->exists();

            if ($existing) {
                $zapierLog->update([
                    'status' => 'rejected',
                    'status_message' => 'Email already processed (duplicate)',
                ]);
                
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

            // Store email (mark as webhook source, no email_account_id needed for Zapier)
            $processedEmail = ProcessedEmail::create([
                'email_account_id' => null, // Not needed for Zapier webhook
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
            
            // Update Zapier log with processed email ID
            $zapierLog->update([
                'processed_email_id' => $processedEmail->id,
                'status' => 'processed',
                'status_message' => 'Email processed and stored',
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
                        'date' => $timeSent,
                    ]);

                    // Update business balance
                    if ($matchedPayment->business_id) {
                        $matchedPayment->business->increment('balance', $matchedPayment->amount);
                    }

                    // Dispatch event to send webhook
                    event(new \App\Events\PaymentApproved($matchedPayment));
                    
                    // Update Zapier log with payment match
                    $zapierLog->update([
                        'payment_id' => $matchedPayment->id,
                        'status' => 'matched',
                        'status_message' => 'Payment matched and approved: ' . $matchedPayment->transaction_id,
                    ]);

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
            
            // Update Zapier log status
            $zapierLog->update([
                'status' => 'processed',
                'status_message' => 'Email processed but no payment matched',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email received and stored',
                'matched' => false,
                'email_id' => $processedEmail->id,
            ]);

        } catch (\Exception $e) {
            // Log error to Zapier log if it exists
            if (isset($zapierLog)) {
                $zapierLog->update([
                    'status' => 'error',
                    'status_message' => 'Error processing email: ' . $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ]);
            }
            
            Log::error('Error receiving email webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'zapier_log_id' => $zapierLog->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing email: ' . $e->getMessage(),
            ], 500);
        }
    }
}
