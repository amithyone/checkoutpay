<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailPayment;
use App\Models\EmailAccount;
use App\Models\ProcessedEmail;
use App\Services\GmailApiService;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class MonitorEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:monitor-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor email inbox for bank transfer notifications';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Checking for new emails...');

        // Get all active email accounts from database
        $emailAccounts = EmailAccount::where('is_active', true)->get();

        if ($emailAccounts->isEmpty()) {
            // Fallback to .env configuration if no email accounts in database
            $this->warn('No email accounts found in database. Using .env configuration...');
            try {
                $client = $this->getImapClient();
                $this->monitorEmailAccount($client, null);
            } catch (\Exception $e) {
                Log::error('Error monitoring emails from .env', [
                    'error' => $e->getMessage(),
                ]);
                $this->error('Error monitoring emails: ' . $e->getMessage());
            }
            return;
        }

        // Monitor each email account
        foreach ($emailAccounts as $emailAccount) {
            try {
                $this->info("Monitoring email account: {$emailAccount->name} ({$emailAccount->email})");
                
                // Check which method to use
                $method = $emailAccount->method ?? 'imap';
                
                if ($method === 'gmail_api') {
                    $this->monitorEmailAccountGmailApi($emailAccount);
                } else {
                    $client = $this->getImapClientForAccount($emailAccount);
                    $this->monitorEmailAccount($client, $emailAccount);
                }
            } catch (\Exception $e) {
                Log::error('Error monitoring email account', [
                    'email_account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                    'method' => $emailAccount->method ?? 'imap',
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Error monitoring {$emailAccount->email}: {$e->getMessage()}");
                continue;
            }
        }

        $this->info('Email monitoring completed');
    }

    /**
     * Monitor a specific email account
     */
    protected function monitorEmailAccount(Client $client, ?EmailAccount $emailAccount): void
    {
        try {
            $client->connect();
            $folder = $client->getFolder($emailAccount ? $emailAccount->folder : 'INBOX');
            
            // Check emails from the last 7 days (or since oldest pending payment if exists)
            // Store all payment-related emails in database for later matching
            $oldestPendingPayment = \App\Models\Payment::pending()
                ->orderBy('created_at', 'asc')
                ->first();
            
            // Use oldest pending payment date or last 7 days, whichever is older
            if ($oldestPendingPayment) {
                $sinceDate = $oldestPendingPayment->created_at->subMinutes(5); // 5 min buffer
            } else {
                // If no pending payments, check last 7 days of emails and store them
                $sinceDate = now()->subDays(7);
            }
            
            // Also check for the most recent email we've already stored
            $lastStoredEmail = \App\Models\ProcessedEmail::where('email_account_id', $emailAccount?->id)
                ->orderBy('email_date', 'desc')
                ->first();
            
            // If we have stored emails, only check emails after the last stored one
            if ($lastStoredEmail && $lastStoredEmail->email_date > $sinceDate) {
                $sinceDate = $lastStoredEmail->email_date;
            }
            
            // Check ALL emails (read and unread) after the payment request date
            // Fetch ALL emails without filtering - store everything for debugging
            $query = $folder->query()->since($sinceDate);
            
            // Get ALL emails without keyword filtering
            // This ensures we don't miss any emails that might contain payment info
            $messages = $query->get();

            $accountEmail = $emailAccount ? $emailAccount->email : 'default account';
            $this->info("Found {$messages->count()} email(s) in {$accountEmail} (after {$sinceDate->format('Y-m-d H:i:s')})");
            $this->line("📧 Starting to process and store emails...");

            $processedCount = 0;
            $skippedCount = 0;
            
            foreach ($messages as $message) {
                try {
                    $fromEmail = $message->getFrom()[0]->mail ?? '';
                    
                    // Filter by allowed senders if configured
                    if ($emailAccount && !$emailAccount->isSenderAllowed($fromEmail)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Store ALL emails in database (no keyword filtering)
                    // This helps debug and ensures we don't miss any payment emails
                    // Store even if no payment info extracted - we'll troubleshoot from inbox
                    $this->storeEmail($message, $emailAccount);
                    
                    // Try to extract payment info - if it has payment data, process it
                    $matchingService = new PaymentMatchingService(
                        new \App\Services\TransactionLogService()
                    );
                    
                    // Convert date attribute to Carbon instance
                    $dateValue = $message->getDate();
                    $emailDate = $this->parseEmailDate($dateValue);
                    
                    $emailData = [
                        'subject' => $message->getSubject(),
                        'from' => $fromEmail,
                        'text' => $message->getTextBody(),
                        'html' => $message->getHTMLBody(),
                        'date' => $emailDate->toDateTimeString(),
                        'email_account_id' => $emailAccount?->id,
                    ];
                    $extractedInfo = $matchingService->extractPaymentInfo($emailData);
                    
                    // Only process if we extracted payment info (amount found)
                    if ($extractedInfo && isset($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                        $this->processMessage($message, $emailAccount);
                        $processedCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing email message', [
                        'error' => $e->getMessage(),
                        'email_account_id' => $emailAccount?->id,
                    ]);
                    $skippedCount++;
                    continue;
                }
            }
            
            // Count actually stored emails
            $storedCount = ProcessedEmail::where('email_account_id', $emailAccount?->id)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->count();
            
            $this->info("✅ Attempted: {$messages->count()} emails | Actually stored: {$storedCount} | Processed for matching: {$processedCount} (with payment info) | Not processed: {$skippedCount} (no payment info extracted - check inbox to troubleshoot)");

            $client->disconnect();
        } catch (\Exception $e) {
            if ($client->isConnected()) {
                $client->disconnect();
            }
            throw $e;
        }
    }

    /**
     * Get IMAP client instance for email account
     */
    protected function getImapClientForAccount(EmailAccount $emailAccount): Client
    {
        $accountKey = 'account_' . $emailAccount->id;

        $cm = new ClientManager([
            'default' => $accountKey,
            'accounts' => [
                $accountKey => [
                    'host' => $emailAccount->host,
                    'port' => $emailAccount->port,
                    'encryption' => $emailAccount->encryption,
                    'validate_cert' => $emailAccount->validate_cert,
                    'username' => $emailAccount->email,
                    'password' => $emailAccount->password,
                    'protocol' => 'imap',
                ],
            ],
        ]);

        return $cm->account($accountKey);
    }

    /**
     * Get IMAP client instance from .env (fallback)
     */
    protected function getImapClient(): Client
    {
        // Validate email credentials
        if (empty(config('payment.email_user')) || empty(config('payment.email_password'))) {
            throw new \Exception('Email credentials not configured. Please set EMAIL_USER and EMAIL_PASSWORD in .env file');
        }

        $cm = new ClientManager([
            'default' => 'gmail',
            'accounts' => [
                'gmail' => [
                    'host' => config('payment.email_host', 'imap.gmail.com'),
                    'port' => config('payment.email_port', 993),
                    'encryption' => config('payment.email_encryption', 'ssl'),
                    'validate_cert' => config('payment.email_validate_cert', false),
                    'username' => config('payment.email_user'),
                    'password' => config('payment.email_password'),
                    'protocol' => 'imap',
                ],
            ],
        ]);

        return $cm->account('gmail');
    }

    /**
     * Monitor email account using Gmail API
     */
    protected function monitorEmailAccountGmailApi(EmailAccount $emailAccount): void
    {
        try {
            $gmailService = new GmailApiService($emailAccount);
            
            // Check emails from the last 7 days (or since oldest pending payment if exists)
            // Store all payment-related emails in database for later matching
            $oldestPendingPayment = \App\Models\Payment::pending()
                ->orderBy('created_at', 'asc')
                ->first();
            
            // Use oldest pending payment date or last 7 days, whichever is older
            if ($oldestPendingPayment) {
                $since = $oldestPendingPayment->created_at->subMinutes(5); // 5 min buffer
            } else {
                // If no pending payments, check last 7 days of emails and store them
                $since = now()->subDays(7);
            }
            
            // Also check for the most recent email we've already stored
            $lastStoredEmail = ProcessedEmail::where('email_account_id', $emailAccount->id)
                ->orderBy('email_date', 'desc')
                ->first();
            
            // If we have stored emails, only check emails after the last stored one
            if ($lastStoredEmail && $lastStoredEmail->email_date > $since) {
                $since = $lastStoredEmail->email_date;
            }
            
            // Get ALL messages without keyword filtering
            $messages = $gmailService->getMessagesSince($since, [
                'keywords' => [] // Empty keywords = get all emails
            ]);
            
            $this->info("Found " . count($messages) . " email(s) in {$emailAccount->email} (Gmail API) (after {$since->format('Y-m-d H:i:s')})");
            
            $processedCount = 0;
            $skippedCount = 0;
            
            foreach ($messages as $emailData) {
                try {
                    $fromEmail = $emailData['from'] ?? '';
                    
                    // Extract email address from "Name <email@example.com>" format
                    if (preg_match('/<(.+?)>/', $fromEmail, $matches)) {
                        $fromEmail = $matches[1];
                    } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $fromEmail, $matches)) {
                            $fromEmail = $matches[0];
                        }
                    }
                    
                    // Filter by allowed senders if configured
                    if (!$emailAccount->isSenderAllowed($fromEmail)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Store ALL emails in database (no keyword filtering)
                    $this->storeGmailApiEmail($emailData, $emailAccount);
                    
                    // Try to extract payment info - if it has payment data, process it
                    $matchingService = new PaymentMatchingService(
                        new \App\Services\TransactionLogService()
                    );
                    $extractedInfo = $matchingService->extractPaymentInfo($emailData);
                    
                    // Only process if we extracted payment info (amount found)
                    if ($extractedInfo && isset($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                        $this->processGmailApiMessage($emailData, $emailAccount);
                        $processedCount++;
                    } else {
                        $skippedCount++;
                    }
                    
                    // Note: Emails are NOT marked as read immediately
                    // They will be checked again on next run until they match a payment or become too old
                    // This ensures emails that arrive before payment requests are still matched
                } catch (\Exception $e) {
                    Log::error('Error processing Gmail API message', [
                        'error' => $e->getMessage(),
                        'email_account_id' => $emailAccount->id,
                    ]);
                    $skippedCount++;
                    continue;
                }
            }
            
            // Count actually stored emails
            $storedCount = ProcessedEmail::where('email_account_id', $emailAccount->id)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->count();
            
            $this->info("✅ Attempted: " . count($messages) . " emails | Actually stored: {$storedCount} | Processed for matching: {$processedCount} (with payment info) | Not processed: {$skippedCount} (no payment info extracted - check inbox to troubleshoot)");
        } catch (\Exception $e) {
            Log::error('Error monitoring email account with Gmail API', [
                'email_account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process email message from Gmail API
     */
    protected function processGmailApiMessage(array $emailData, EmailAccount $emailAccount): void
    {
        try {
            // Extract email address from "Name <email@example.com>" format
            $from = $emailData['from'] ?? '';
            if (preg_match('/<(.+?)>/', $from, $matches)) {
                $from = $matches[1];
            } elseif (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $from = $from;
            } else {
                // Try to extract email from string
                if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $from, $matches)) {
                    $from = $matches[0];
                }
            }
            
            $processedData = [
                'subject' => $emailData['subject'] ?? '',
                'from' => $from,
                'text' => $emailData['text'] ?? '',
                'html' => $emailData['html'] ?? '',
                'date' => $emailData['date'] ?? now()->toDateTimeString(),
                'email_account_id' => $emailAccount->id,
            ];

            $this->info("Processing email: {$processedData['subject']}");

            // Dispatch job to process email
            ProcessEmailPayment::dispatch($processedData);

            $this->info("Dispatched job for email: {$processedData['subject']}");
        } catch (\Exception $e) {
            Log::error('Error processing Gmail API message', [
                'error' => $e->getMessage(),
                'email_account_id' => $emailAccount->id,
                'email_data' => $emailData,
            ]);

            $this->warn("Error processing email: {$e->getMessage()}");
        }
    }

    /**
     * Process email message
     */
    protected function processMessage($message, ?EmailAccount $emailAccount): void
    {
        try {
            // Convert date attribute to Carbon instance
            $dateValue = $message->getDate();
            $emailDate = $this->parseEmailDate($dateValue);
            
            $emailData = [
                'subject' => $message->getSubject(),
                'from' => $message->getFrom()[0]->mail ?? '',
                'text' => $message->getTextBody(),
                'html' => $message->getHTMLBody(),
                'date' => $emailDate->toDateTimeString(),
                'email_account_id' => $emailAccount?->id,
            ];

            $this->info("Processing email: {$emailData['subject']}");

            // Dispatch job to process email
            ProcessEmailPayment::dispatch($emailData);

            $this->info("Dispatched job for email: {$emailData['subject']}");
        } catch (\Exception $e) {
            Log::error('Error processing email message', [
                'error' => $e->getMessage(),
                'email_account_id' => $emailAccount?->id,
            ]);

            $this->warn("Error processing email: {$e->getMessage()}");
        }
    }

    /**
     * Store IMAP email in database
     */
    protected function storeEmail($message, ?EmailAccount $emailAccount): void
    {
        try {
            $this->line("🔍 Attempting to store email...");
            
            $messageId = $message->getUid() ?? $message->getMessageId();
            if (!$messageId) {
                $this->warn("⚠️  No message ID found, skipping email");
                return;
            }
            
            $this->line("📧 Message ID: {$messageId}");
            
            $from = $message->getFrom()[0] ?? null;
            $fromEmail = $from->mail ?? '';
            $fromName = $from->personal ?? '';
            
            $subject = $message->getSubject() ?? 'No Subject';
            $this->line("📝 Subject: {$subject} | From: {$fromEmail}");
            
            // Extract payment info to store
            $matchingService = new PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            
            // Convert date attribute to Carbon instance
            $dateValue = $message->getDate();
            $emailDate = $this->parseEmailDate($dateValue);
            
            $emailData = [
                'subject' => $subject,
                'from' => $fromEmail,
                'text' => $message->getTextBody(),
                'html' => $message->getHTMLBody(),
                'date' => $emailDate->toDateTimeString(),
                'email_account_id' => $emailAccount?->id,
            ];
            
            $this->line("🔍 Extracting payment info...");
            $extractedInfo = $matchingService->extractPaymentInfo($emailData);
            $this->line("💰 Extracted amount: " . ($extractedInfo['amount'] ?? 'null'));
            
            // Check if email already exists
            $this->line("🔍 Checking if email already exists...");
            $existing = ProcessedEmail::where('message_id', (string)$messageId)
                ->where('email_account_id', $emailAccount?->id)
                ->first();
            
            if ($existing) {
                $this->line("⏭️  Email already stored: {$subject} (ID: {$existing->id})");
                return; // Already stored
            }
            
            // Store email even if extraction failed or returned null
            $this->line("💾 Creating database record...");
            try {
                $stored = ProcessedEmail::create([
                    'email_account_id' => $emailAccount?->id,
                    'message_id' => (string)$messageId,
                    'subject' => $subject,
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'text_body' => $message->getTextBody(),
                    'html_body' => $message->getHTMLBody(),
                    'email_date' => $emailDate,
                    'amount' => $extractedInfo['amount'] ?? null,
                    'sender_name' => $extractedInfo['sender_name'] ?? null,
                    'account_number' => $extractedInfo['account_number'] ?? null,
                    'extracted_data' => $extractedInfo,
                ]);
                
                $this->line("✅ Stored email: {$subject} | From: {$fromEmail} | ID: {$stored->id}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to store email: {$subject} | Error: {$e->getMessage()}");
                $this->error("❌ Stack trace: " . $e->getTraceAsString());
                Log::error('Failed to store email in database', [
                    'error' => $e->getMessage(),
                    'subject' => $subject,
                    'from' => $fromEmail,
                    'message_id' => $messageId,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } catch (\Exception $e) {
            $this->error("❌ Error in storeEmail(): {$e->getMessage()}");
            $this->error("❌ Stack trace: " . $e->getTraceAsString());
            Log::error('Error storing email in database', [
                'error' => $e->getMessage(),
                'email_account_id' => $emailAccount?->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Store Gmail API email in database
     */
    protected function storeGmailApiEmail(array $emailData, EmailAccount $emailAccount): void
    {
        try {
            $messageId = $emailData['id'] ?? null;
            if (!$messageId) {
                return;
            }
            
            // Extract email address from "Name <email@example.com>" format
            $from = $emailData['from'] ?? '';
            $fromEmail = $from;
            $fromName = '';
            if (preg_match('/<(.+?)>/', $from, $matches)) {
                $fromEmail = $matches[1];
                $fromName = trim(str_replace($matches[0], '', $from));
            } elseif (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = $from;
            }
            
            // Try to extract payment info, but store email even if extraction fails
            $extractedInfo = null;
            try {
                $matchingService = new PaymentMatchingService(
                    new \App\Services\TransactionLogService()
                );
                $extractedInfo = $matchingService->extractPaymentInfo($emailData);
            } catch (\Exception $e) {
                Log::debug('Payment info extraction failed, storing email anyway', [
                    'error' => $e->getMessage(),
                    'subject' => $emailData['subject'] ?? '',
                ]);
            }
            
            // Check if email already exists
            $existing = ProcessedEmail::where('message_id', $messageId)
                ->where('email_account_id', $emailAccount->id)
                ->first();
            
            if ($existing) {
                $this->line("⏭️  Email already stored: " . ($emailData['subject'] ?? 'No subject'));
                return; // Already stored
            }
            
            // Store email even if extraction failed or returned null
            try {
                $stored = ProcessedEmail::create([
                    'email_account_id' => $emailAccount->id,
                    'message_id' => $messageId,
                    'subject' => $emailData['subject'] ?? '',
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'text_body' => $emailData['text'] ?? '',
                    'html_body' => $emailData['html'] ?? '',
                    'email_date' => $emailData['date'] ?? now(),
                    'amount' => $extractedInfo['amount'] ?? null,
                    'sender_name' => $extractedInfo['sender_name'] ?? null,
                    'account_number' => $extractedInfo['account_number'] ?? null,
                    'extracted_data' => $extractedInfo,
                ]);
                
                $this->line("✅ Stored email: " . ($emailData['subject'] ?? 'No subject') . " | From: {$fromEmail} | ID: {$stored->id}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to store email: " . ($emailData['subject'] ?? 'No subject') . " | Error: {$e->getMessage()}");
                Log::error('Failed to store Gmail API email in database', [
                    'error' => $e->getMessage(),
                    'subject' => $emailData['subject'] ?? '',
                    'from' => $fromEmail,
                    'message_id' => $messageId,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error storing Gmail API email in database', [
                'error' => $e->getMessage(),
                'email_account_id' => $emailAccount->id,
            ]);
        }
    }

    /**
     * Parse email date from various formats (Attribute, array, string, Carbon)
     */
    protected function parseEmailDate($dateValue): \Carbon\Carbon
    {
        if (!$dateValue) {
            return now();
        }

        try {
            // If it's already a Carbon instance, return it
            if ($dateValue instanceof \Carbon\Carbon) {
                return $dateValue;
            }

            // If it's an Attribute object, get its value
            if (is_object($dateValue)) {
                if (method_exists($dateValue, 'get')) {
                    $dateValue = $dateValue->get();
                } elseif (property_exists($dateValue, 'value')) {
                    $dateValue = $dateValue->value;
                } elseif (method_exists($dateValue, '__toString')) {
                    $dateValue = (string)$dateValue;
                } else {
                    // Try to convert object to string
                    $dateValue = json_decode(json_encode($dateValue), true);
                }
            }

            // If it's an array, try to extract the date string
            if (is_array($dateValue)) {
                // Try common array keys for date
                $dateValue = $dateValue['date'] ?? $dateValue['value'] ?? $dateValue['datetime'] ?? $dateValue[0] ?? null;
                
                // If still an array, try to get first string value
                if (is_array($dateValue)) {
                    foreach ($dateValue as $val) {
                        if (is_string($val) && !empty($val)) {
                            $dateValue = $val;
                            break;
                        }
                    }
                }
            }

            // If we have a string value, parse it
            if (is_string($dateValue) && !empty($dateValue)) {
                return \Carbon\Carbon::parse($dateValue);
            }

            // Fallback to current time
            return now();
        } catch (\Exception $e) {
            Log::debug('Error parsing email date', [
                'error' => $e->getMessage(),
                'date_value_type' => gettype($dateValue),
                'date_value' => is_array($dateValue) ? json_encode($dateValue) : (is_object($dateValue) ? get_class($dateValue) : $dateValue),
            ]);
            return now();
        }
    }
}
