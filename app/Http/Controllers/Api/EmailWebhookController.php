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
            // Handle Zapier test connection (Zapier sends a test request with minimal data)
            // Zapier test requests often have empty or missing fields, so we handle them gracefully
            $isTestRequest = empty($request->input('from')) && empty($request->input('From'));
            
            if ($isTestRequest) {
                // Return success response for Zapier test connection
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook endpoint is ready. Send email data with fields: from, to, subject, text, html, date, message_id',
                    'status' => 'ready',
                    'endpoint' => 'email/webhook',
                    'required_fields' => ['from', 'to'],
                    'optional_fields' => ['subject', 'text', 'html', 'date', 'message_id'],
                ], 200);
            }

            // Validate request (relaxed validation for Zapier compatibility)
            $validator = Validator::make($request->all(), [
                'from' => 'required_without:From|string',
                'From' => 'required_without:from|string',
                'to' => 'required_without:To|string',
                'To' => 'required_without:to|string',
                'subject' => 'nullable|string',
                'Subject' => 'nullable|string',
                'text' => 'nullable|string',
                'html' => 'nullable|string',
                'date' => 'nullable|date',
                'message_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'received_data' => array_keys($request->all()),
                    'help' => 'Required fields: from (or From) and to (or To). Optional: subject, text, html, date, message_id',
                ], 400);
            }

            // Handle Zapier format (supports multiple field name variations)
            $from = $request->input('from') ?? $request->input('From') ?? '';
            $to = $request->input('to') ?? $request->input('To') ?? '';
            $subject = $request->input('subject') ?? $request->input('Subject') ?? 'No Subject';
            $text = $request->input('text') ?? $request->input('Plain Body') ?? $request->input('Body Plain') ?? $request->input('body') ?? '';
            $html = $request->input('html') ?? $request->input('HTML Body') ?? $request->input('Body HTML') ?? $request->input('html_body') ?? '';
            $date = $request->input('date') ?? $request->input('Date') ?? now()->toDateTimeString();
            $messageId = $request->input('message_id') ?? $request->input('Message ID') ?? md5($from . $subject . $date);

            // Extract email address from "Name <email@example.com>" format
            $fromEmail = $from;
            $fromName = '';
            if (preg_match('/<(.+?)>/', $from, $matches)) {
                $fromEmail = $matches[1];
                $fromName = trim(str_replace($matches[0], '', $from));
            } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $fromEmail, $matches)) {
                    $fromEmail = $matches[0];
                }
            }

            // Filter out noreply@xtrapay.ng emails
            if (strtolower($fromEmail) === 'noreply@xtrapay.ng') {
                return response()->json([
                    'success' => true,
                    'message' => 'Skipped noreply@xtrapay.ng email',
                    'status' => 'skipped',
                ], 200);
            }

            // Find email account by forwarding address (to field)
            $emailAccount = EmailAccount::where('email', $to)
                ->orWhere('email', 'like', '%' . explode('@', $to)[0] . '%')
                ->first();

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
                'date' => $date,
                'email_account_id' => $emailAccount?->id,
            ];

            $extractedInfo = null;
            try {
                $extractedInfo = $matchingService->extractPaymentInfo($emailData);
            } catch (\Exception $e) {
                Log::debug('Payment info extraction failed', [
                    'error' => $e->getMessage(),
                    'subject' => $subject,
                ]);
            }

            // Store email
            $processedEmail = ProcessedEmail::create([
                'email_account_id' => $emailAccount?->id,
                'message_id' => $messageId,
                'subject' => $subject,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'text_body' => $text,
                'html_body' => $html,
                'email_date' => $date,
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
