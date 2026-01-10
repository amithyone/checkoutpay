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
            // SECURITY: Verify webhook secret
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
            
            // Detect payload format:
            // 1. Zapier format: sender_name, amount, time_sent, email
            // 2. Raw email forward (cPanel): raw email content or email headers/body
            
            $isZapierFormat = $request->has('sender_name') || $request->has('email');
            $isRawEmail = $request->has('headers') || $request->has('body') || $request->has('text') || $request->has('html');
            
            // Handle test connection (empty request)
            if (!$isZapierFormat && !$isRawEmail && empty($request->getContent())) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook endpoint is ready',
                    'status' => 'ready',
                    'endpoint' => 'email/webhook',
                    'supported_formats' => [
                        'zapier' => ['sender_name', 'amount', 'time_sent', 'email'],
                        'raw_email' => ['headers', 'body', 'text', 'html', 'from', 'subject', 'date'],
                    ],
                    'security_note' => $webhookSecret ? 'Webhook secret authentication is enabled' : 'Webhook secret authentication is disabled - enable it in admin settings',
                ], 200);
            }

            // Process based on format
            if ($isRawEmail) {
                // Raw email forward format (from cPanel email forwarding)
                return $this->processRawEmail($request);
            } else {
                // Zapier format
                return $this->processZapierPayload($request);
            }
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

    /**
     * Process Zapier format payload
     */
    protected function processZapierPayload(Request $request)
    {
        try {
            // Extract Zapier payload
            $senderName = $request->input('sender_name', '');
            $amountRaw = $request->input('amount', '');
            $timeSent = $request->input('time_sent', now()->toDateTimeString());
            $emailContent = $request->input('email', ''); // This is the full email body/content
            
            // Parse amount - handle formats like "NGN 800", "NGN500", "₦800", "800", etc.
            $amount = $this->parseAmount($amountRaw);
            
            // LOG: Save all incoming payloads to zapier_logs
            $zapierLog = ZapierLog::create([
                'payload' => $request->all(), // Store full payload
                'sender_name' => $senderName,
                'amount' => $amount, // Already parsed, can be null if invalid
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

            // Use Zapier payload directly - no email extraction needed
            // Zapier payload is the standard: sender_name, amount, time_sent
            if ($amount === null || $amount <= 0) {
                $zapierLog->update([
                    'status' => 'rejected',
                    'status_message' => 'Invalid or missing amount in Zapier payload',
                    'error_details' => 'Amount must be a valid number. Received: ' . $amountRaw,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or missing amount in payload. Received: ' . $amountRaw,
                    'status' => 'validation_error',
                ], 400);
            }

            // Build extracted info directly from Zapier payload
            $extractedInfo = [
                'amount' => (float) $amount,
                'sender_name' => $senderName ? strtolower(trim($senderName)) : null,
                'account_number' => null,
                'email_subject' => $subject,
                'email_from' => $fromEmail,
            ];

            // Initialize matching service for payment matching
            $matchingService = new PaymentMatchingService(new TransactionLogService());

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
                'amount' => $extractedInfo['amount'],
                'sender_name' => $extractedInfo['sender_name'],
                'account_number' => $extractedInfo['account_number'],
                'extracted_data' => $extractedInfo,
            ]);
            
            // Update Zapier log with processed email ID
            $zapierLog->update([
                'processed_email_id' => $processedEmail->id,
                'status' => 'processed',
                'status_message' => 'Email processed and stored',
            ]);

            // Try to match payment immediately using Zapier payload directly
            if ($extractedInfo['amount'] > 0) {
                // Get pending payments and sort by:
                // 1. Amount similarity (closest match first)
                // 2. Most recent transaction creation time
                $pendingPayments = \App\Models\Payment::pending()
                    ->orderBy('created_at', 'desc') // Most recent first
                    ->get()
                    ->map(function ($payment) use ($extractedInfo) {
                        // Calculate amount difference for sorting
                        $amountDiff = abs($payment->amount - $extractedInfo['amount']);
                        return [
                            'payment' => $payment,
                            'amount_diff' => $amountDiff,
                        ];
                    })
                    ->sortBy('amount_diff') // Sort by closest amount match
                    ->values(); // Reset keys
                
                // Use webhook received time for matching
                $webhookTime = \Carbon\Carbon::parse($timeSent)->setTimezone(config('app.timezone'));
                
                $bestMatch = null;
                $bestMatchResult = null;
                $allMatches = [];
                
                // Try all payments and find the best match
                foreach ($pendingPayments as $item) {
                    $payment = $item['payment'];
                    
                    $matchResult = $matchingService->matchPayment($payment, $extractedInfo, $webhookTime);
                    
                    // Store all match attempts for debugging
                    $allMatches[] = [
                        'transaction_id' => $payment->transaction_id,
                        'amount' => $payment->amount,
                        'amount_diff' => $item['amount_diff'],
                        'created_at' => $payment->created_at->toDateTimeString(),
                        'matched' => $matchResult['matched'] ?? false,
                        'reason' => $matchResult['reason'] ?? 'No reason provided',
                    ];
                    
                    if ($matchResult['matched']) {
                        // Found a match - use this one (it's already sorted by best match)
                        $bestMatch = $payment;
                        $bestMatchResult = $matchResult;
                        break; // Stop at first match (best match due to sorting)
                    }
                }
                
                // Process the best match if found
                if ($bestMatch && $bestMatchResult) {
                    $payment = $bestMatch;
                    $matchResult = $bestMatchResult;
                    
                    if ($matchResult['matched']) {
                        // Mark email as matched
                        $processedEmail->markAsMatched($payment);
                        
                        // Check if this is a mismatch (amount difference < N500)
                        $isMismatch = $matchResult['is_mismatch'] ?? false;
                        $receivedAmount = $matchResult['received_amount'] ?? null;
                        $mismatchReason = $matchResult['mismatch_reason'] ?? null;
                        
                        // Approve payment (with mismatch flag if applicable)
                        $payment->approve([
                            'subject' => $subject,
                            'from' => $fromEmail,
                            'text' => $text,
                            'html' => $html,
                            'date' => $timeSent,
                        ], $isMismatch, $receivedAmount, $mismatchReason);
                        
                        // Update business balance - use received amount if mismatch, otherwise expected amount
                        if ($payment->business_id) {
                            $balanceAmount = $isMismatch && $receivedAmount ? $receivedAmount : $payment->amount;
                            $payment->business->increment('balance', $balanceAmount);
                        }
                        
                        // Dispatch event to send webhook
                        event(new \App\Events\PaymentApproved($payment));
                        
                        // Update Zapier log with payment match
                        $statusMessage = $isMismatch 
                            ? 'Payment matched with mismatch: ' . $payment->transaction_id . ' - ' . $mismatchReason
                            : 'Payment matched and approved: ' . $payment->transaction_id;
                        
                        $zapierLog->update([
                            'payment_id' => $payment->id,
                            'status' => 'matched',
                            'status_message' => $statusMessage,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => $isMismatch ? 'Payment matched with amount mismatch' : 'Email received and payment matched',
                            'matched' => true,
                            'is_mismatch' => $isMismatch,
                            'payment_id' => $payment->id,
                            'transaction_id' => $payment->transaction_id,
                            'status' => 'matched',
                            'mismatch_reason' => $mismatchReason,
                            'match_attempts' => $allMatches, // Include all match attempts for debugging
                        ], 200);
                    } elseif (isset($matchResult['should_reject']) && $matchResult['should_reject']) {
                        // Amount difference >= N500, reject payment
                        $payment->reject($matchResult['reason']);
                        
                        $zapierLog->update([
                            'payment_id' => $payment->id,
                            'status' => 'rejected',
                            'status_message' => 'Payment rejected: ' . $matchResult['reason'],
                            'error_details' => $matchResult['reason'],
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'Payment rejected due to amount mismatch (>= N500 difference)',
                            'matched' => false,
                            'rejected' => true,
                            'payment_id' => $payment->id,
                            'transaction_id' => $payment->transaction_id,
                            'reason' => $matchResult['reason'],
                            'status' => 'rejected',
                        ], 200);
                    }
                }
            }
            
            // Update Zapier log status if no match found
            if ($zapierLog->status === 'processed') {
                $zapierLog->update([
                    'status' => 'no_match',
                    'status_message' => 'Email processed but no matching payment found',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Email received and stored, no matching payment found',
                'matched' => false,
                'email_id' => $processedEmail->id,
                'status' => 'no_match',
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

    /**
     * Parse amount from various formats:
     * - "NGN 800" -> 800
     * - "NGN500" -> 500
     * - "₦800" -> 800
     * - "800" -> 800
     * - "800.50" -> 800.50
     * - "1,000.50" -> 1000.50
     */
    private function parseAmount($amountRaw): ?float
    {
        if (empty($amountRaw)) {
            return null;
        }

        // Convert to string and trim
        $amountStr = trim((string) $amountRaw);

        // Remove currency symbols and prefixes
        // Remove "NGN", "₦", "N", "$", etc. (case insensitive)
        $amountStr = preg_replace('/^(NGN|₦|N|USD|\$|EUR|€)\s*/i', '', $amountStr);

        // Remove commas (thousand separators)
        $amountStr = str_replace(',', '', $amountStr);

        // Remove any remaining non-numeric characters except decimal point and minus sign
        $amountStr = preg_replace('/[^\d.-]/', '', $amountStr);

        // Trim whitespace
        $amountStr = trim($amountStr);

        // Try to parse as float
        if (is_numeric($amountStr)) {
            $parsedAmount = (float) $amountStr;
            return $parsedAmount > 0 ? $parsedAmount : null;
        }

        return null;
    }

    /**
     * Process raw email forward (from cPanel email forwarding)
     */
    protected function processRawEmail(Request $request)
    {
        // Extract email data from raw email forward
        // cPanel can send emails in different formats, handle both
        
        // Format 1: Separate fields (headers, body, etc.)
        $fromEmail = $request->input('from') ?? $request->input('headers.from') ?? '';
        $subject = $request->input('subject') ?? $request->input('headers.subject') ?? 'Transaction Notification';
        $textBody = $request->input('text') ?? $request->input('body') ?? '';
        $htmlBody = $request->input('html') ?? '';
        $date = $request->input('date') ?? $request->input('headers.date') ?? now()->toDateTimeString();
        
        // Format 2: Raw email content (parse MIME)
        if (empty($textBody) && empty($htmlBody)) {
            $rawContent = $request->getContent() ?? $request->input('email') ?? '';
            if (!empty($rawContent)) {
                $parsed = $this->parseRawEmail($rawContent);
                $fromEmail = $parsed['from'] ?? $fromEmail;
                $subject = $parsed['subject'] ?? $subject;
                $textBody = $parsed['text'] ?? $textBody;
                $htmlBody = $parsed['html'] ?? $htmlBody;
                $date = $parsed['date'] ?? $date;
            }
        }
        
        // Extract sender name from From header if available
        $senderName = '';
        if (preg_match('/^(.+?)\s*<(.+?)>$/', $fromEmail, $matches)) {
            $senderName = trim($matches[1]);
            $fromEmail = strtolower(trim($matches[2]));
        } elseif (preg_match('/([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})/', $fromEmail, $matches)) {
            $fromEmail = strtolower($matches[1]);
        }
        
        // Use PaymentMatchingService to extract payment info from email
        $matchingService = new PaymentMatchingService(new TransactionLogService());
        $emailData = [
            'subject' => $subject,
            'from' => $fromEmail,
            'text' => $textBody,
            'html' => $htmlBody,
            'date' => $date,
        ];
        
        $extractionResult = $matchingService->extractPaymentInfo($emailData);
        $extractedInfo = $extractionResult['data'] ?? null;
        
        if (!$extractedInfo || !isset($extractedInfo['amount']) || empty($extractedInfo['amount'])) {
            // Log the email but don't process if we can't extract payment info
            \Illuminate\Support\Facades\Log::warning('Email webhook: Could not extract payment information from raw email', [
                'subject' => $subject,
                'from' => $fromEmail,
                'ip_address' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Could not extract payment information from email',
                'status' => 'extraction_failed',
            ], 400);
        }
        
        // Convert to webhook format for processing
        $zapierRequest = new Request([
            'sender_name' => $senderName ?: ($extractedInfo['sender_name'] ?? ''),
            'amount' => $extractedInfo['amount'],
            'time_sent' => $date,
            'email' => $htmlBody ?: $textBody,
        ]);
        
        // Copy headers for security
        foreach ($request->headers->all() as $key => $value) {
            $zapierRequest->headers->set($key, $value[0] ?? '');
        }
        
        // Process using Zapier payload handler
        return $this->processZapierPayload($zapierRequest);
    }

    /**
     * Parse raw email content (MIME format)
     */
    protected function parseRawEmail(string $rawContent): array
    {
        $result = [
            'from' => '',
            'subject' => '',
            'text' => '',
            'html' => '',
            'date' => now()->toDateTimeString(),
        ];
        
        // Extract headers
        if (preg_match('/From:\s*(.+?)(?:\r?\n|$)/i', $rawContent, $matches)) {
            $result['from'] = trim($matches[1]);
        }
        
        if (preg_match('/Subject:\s*(.+?)(?:\r?\n|$)/i', $rawContent, $matches)) {
            $result['subject'] = trim($matches[1]);
        }
        
        if (preg_match('/Date:\s*(.+?)(?:\r?\n|$)/i', $rawContent, $matches)) {
            $result['date'] = trim($matches[1]);
        }
        
        // Extract body (after first blank line)
        $parts = preg_split('/\r?\n\r?\n/', $rawContent, 2);
        if (isset($parts[1])) {
            $body = $parts[1];
            
            // Check if HTML
            if (stripos($body, '<html') !== false || stripos($body, '<body') !== false) {
                $result['html'] = $body;
                $result['text'] = strip_tags($body);
            } else {
                $result['text'] = $body;
                $result['html'] = nl2br(htmlspecialchars($body));
            }
        }
        
        return $result;
    }

    /**
     * Health check endpoint (for GET requests)
     */
    public function healthCheck(Request $request)
    {
        $webhookSecret = Setting::get('zapier_webhook_secret');
        
        return response()->json([
            'success' => true,
            'message' => 'Email webhook endpoint is ready',
            'status' => 'ready',
            'endpoint' => '/api/v1/email/webhook',
            'supported_methods' => ['GET', 'POST'],
            'security' => [
                'webhook_secret_enabled' => !empty($webhookSecret),
                'whitelist_enabled' => WhitelistedEmailAddress::where('is_active', true)->exists(),
            ],
            'usage' => [
                'post' => 'Send email data via POST request',
                'get' => 'Health check (this endpoint)',
            ],
            'expected_format' => [
                'zapier' => ['sender_name', 'amount', 'time_sent', 'email'],
                'raw_email' => ['from', 'subject', 'text', 'html', 'date'],
            ],
        ], 200);
    }
}
