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
    protected $signature = 'payment:monitor-emails {--since= : Fetch emails since this date (Y-m-d H:i:s)}';

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
            $this->warn('No active email accounts found in database.');
            $this->info('💡 Add email accounts in Admin → Email Accounts');
            $this->info('💡 Or use Zapier webhook: ' . url('/api/v1/email/webhook'));
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
                } elseif ($method === 'native_imap') {
                    // Use native PHP IMAP functions (faster, direct parsing)
                    $this->monitorEmailAccountNativeImap($emailAccount);
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
            
            // Fetch emails from the last fetch time (not from oldest pending transaction)
            // This ensures we don't re-fetch emails that have already been processed
            $sinceDate = null;
            
            if ($emailAccount) {
                // Check if email account has a last_fetched_at timestamp
                // We'll store this in a notes field or add a column later
                // For now, use the most recent stored email date
                $lastStoredEmail = \App\Models\ProcessedEmail::where('email_account_id', $emailAccount->id)
                    ->orderBy('email_date', 'desc')
                    ->first();
                
                if ($lastStoredEmail && $lastStoredEmail->email_date) {
                    // Fetch emails after the last stored email
                    $sinceDate = $lastStoredEmail->email_date;
                } else {
                    // If no stored emails, fetch from oldest pending payment (if exists)
                    $oldestPendingPayment = \App\Models\Payment::pending()
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                    if ($oldestPendingPayment) {
                        $sinceDate = $oldestPendingPayment->created_at->subMinutes(5);
                    } else {
                        // If no pending payments and no stored emails, fetch last 24 hours
                        $sinceDate = now()->subDay();
                    }
                }
            } else {
                // Fallback: use oldest pending payment or last 24 hours
                $oldestPendingPayment = \App\Models\Payment::pending()
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                $sinceDate = $oldestPendingPayment 
                    ? $oldestPendingPayment->created_at->subMinutes(5)
                    : now()->subDay();
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
            $alreadyStoredCount = 0;
            
            // Get all existing message IDs for this account (fast lookup)
            $existingMessageIds = \App\Models\ProcessedEmail::where('email_account_id', $emailAccount?->id)
                ->pluck('message_id')
                ->toArray();
            
            // Get last processed message ID for fast skipping
            $lastProcessedMessageId = $emailAccount?->last_processed_message_id;
            $foundLastProcessed = false;
            
            foreach ($messages as $message) {
                try {
                    // Get message ID FIRST (before fetching body - much faster)
                    $messageId = (string)($message->getUid() ?? $message->getMessageId() ?? '');
                    
                    if (!$messageId) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // FAST CHECK: Skip if already stored (check before fetching body)
                    if (in_array($messageId, $existingMessageIds)) {
                        $alreadyStoredCount++;
                        continue; // Skip immediately - don't fetch body
                    }
                    
                    // FAST CHECK: Skip if we've already processed this message ID
                    if ($lastProcessedMessageId && $messageId === $lastProcessedMessageId) {
                        $foundLastProcessed = true;
                        continue;
                    }
                    
                    // If we found the last processed message, skip all previous ones
                    if ($foundLastProcessed) {
                        continue;
                    }
                    
                    // Only fetch email body if we need to process it
                    $fromEmail = $message->getFrom()[0]->mail ?? '';
                    
                    // Filter by allowed senders if configured
                    if ($emailAccount && !$emailAccount->isSenderAllowed($fromEmail)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Store email in database
                    $this->storeEmail($message, $emailAccount);
                    
                    // Update last processed message ID
                    if ($emailAccount) {
                        $emailAccount->update([
                            'last_processed_message_id' => $messageId,
                            'last_processed_at' => now(),
                        ]);
                    }
                    
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
                    
                    // Check if this is a GTBank transaction notification
                    $gtbankParser = new \App\Services\GtbankTransactionParser();
                    if ($gtbankParser->isGtbankTransaction($emailData)) {
                        // Get message ID for lookup
                        $messageId = $message->getUid() ?? $message->getMessageId();
                        
                        // Parse and store GTBank transaction
                        $storedEmail = ProcessedEmail::where('message_id', $messageId)
                            ->where('email_account_id', $emailAccount?->id)
                            ->first();
                        
                        if ($storedEmail) {
                            // Find GTBank template
                            $gtbankTemplate = \App\Models\BankEmailTemplate::where('bank_name', 'GTBank')
                                ->orWhere('bank_name', 'Guaranty Trust Bank')
                                ->active()
                                ->orderBy('priority', 'desc')
                                ->first();
                            
                            $gtbankParser->parseTransaction($emailData, $storedEmail, $gtbankTemplate);
                        }
                    }
                    
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
            
            // Check if --since option was provided (from cron job)
            $sinceOption = $this->option('since');
            if ($sinceOption) {
                try {
                    $since = \Carbon\Carbon::parse($sinceOption)->setTimezone(config('app.timezone'));
                } catch (\Exception $e) {
                    Log::warning('Invalid --since date provided for Gmail API, using default logic', ['since' => $sinceOption]);
                    $sinceOption = null; // Fall back to default logic
                }
            }
            
            // If no --since option, use default logic
            if (!isset($since)) {
                // Fetch emails from the last fetch time (not from oldest pending transaction)
                // This ensures we don't re-fetch emails that have already been processed
                // Check for the most recent email we've already stored
                $lastStoredEmail = ProcessedEmail::where('email_account_id', $emailAccount->id)
                    ->orderBy('email_date', 'desc')
                    ->first();
                
                if ($lastStoredEmail && $lastStoredEmail->email_date) {
                    // Fetch emails after the last stored email (don't re-fetch old emails)
                    $since = $lastStoredEmail->email_date->subMinutes(1);
                } else {
                    // If no stored emails, fetch from oldest pending payment (if exists)
                    $oldestPendingPayment = \App\Models\Payment::pending()
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                    if ($oldestPendingPayment) {
                        $since = $oldestPendingPayment->created_at->subMinutes(5);
                    } else {
                        // If no pending payments and no stored emails, fetch last 24 hours
                        $since = now()->subDay();
                    }
                }
            }
            
            // Ensure sinceDate is not in the future
            if ($since->greaterThan(now())) {
                $since = now()->subMinutes(1);
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
                    
                    // Filter out noreply@xtrapay.ng emails
                    if (strtolower($fromEmail) === 'noreply@xtrapay.ng') {
                        $this->line("⏭️  Skipping email from noreply@xtrapay.ng: " . ($emailData['subject'] ?? 'No subject'));
                        $skippedCount++;
                        continue;
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
                    
                    // Check if this is a GTBank transaction notification
                    $gtbankParser = new \App\Services\GtbankTransactionParser();
                    if ($gtbankParser->isGtbankTransaction($emailData)) {
                        // Parse and store GTBank transaction
                        $storedEmail = ProcessedEmail::where('message_id', $emailData['message_id'] ?? null)
                            ->where('email_account_id', $emailAccount->id)
                            ->first();
                        
                        if ($storedEmail) {
                            // Find GTBank template
                            $gtbankTemplate = \App\Models\BankEmailTemplate::where('bank_name', 'GTBank')
                                ->orWhere('bank_name', 'Guaranty Trust Bank')
                                ->active()
                                ->orderBy('priority', 'desc')
                                ->first();
                            
                            $gtbankParser->parseTransaction($emailData, $storedEmail, $gtbankTemplate);
                        }
                    }
                    
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
    protected function storeEmail($message, ?EmailAccount $emailAccount): ?ProcessedEmail
    {
        try {
            $this->line("🔍 Attempting to store email...");
            
            $messageId = (string)($message->getUid() ?? $message->getMessageId() ?? '');
            if (!$messageId) {
                return null;
            }
            
            // FAST CHECK: Skip if already exists (shouldn't happen due to pre-check, but safety)
            $existing = ProcessedEmail::where('message_id', $messageId)
                ->where('email_account_id', $emailAccount?->id)
                ->exists();
            
            if ($existing) {
                return null; // Already stored - skip immediately
            }
            
            $from = $message->getFrom()[0] ?? null;
            $fromEmail = $from->mail ?? '';
            $fromName = $from->personal ?? '';
            $subject = $message->getSubject() ?? 'No Subject';
            
            // Filter out noreply@xtrapay.ng emails
            if (strtolower($fromEmail) === 'noreply@xtrapay.ng') {
                return null;
            }
            
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
            
            // Try to extract payment info, but store email even if extraction fails
            $extractedInfo = null;
            try {
                $extractedInfo = $matchingService->extractPaymentInfo($emailData);
            } catch (\Exception $e) {
                Log::debug('Payment info extraction failed, storing email anyway', [
                    'error' => $e->getMessage(),
                    'subject' => $subject,
                ]);
            }
            
            // Store email even if extraction failed or returned null
            try {
                return ProcessedEmail::create([
                    'email_account_id' => $emailAccount?->id,
                    'source' => 'imap', // Mark as IMAP source
                    'message_id' => $messageId,
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
            } catch (\Exception $e) {
                Log::error('Failed to store email in database', [
                    'error' => $e->getMessage(),
                    'subject' => $subject,
                    'from' => $fromEmail,
                    'message_id' => $messageId,
                    'trace' => $e->getTraceAsString(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error storing email in database', [
                'error' => $e->getMessage(),
                'email_account_id' => $emailAccount?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Store Gmail API email in database
     */
    protected function storeGmailApiEmail(array $emailData, EmailAccount $emailAccount): ?ProcessedEmail
    {
        try {
            $messageId = (string)($emailData['message_id'] ?? $emailData['id'] ?? '');
            if (!$messageId) {
                return null;
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
                return null; // Already stored
            }
            
            // Store email even if extraction failed or returned null
            try {
                return ProcessedEmail::create([
                    'email_account_id' => $emailAccount->id,
                    'source' => 'gmail_api', // Mark as Gmail API source
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
            } catch (\Exception $e) {
                Log::error('Failed to store Gmail API email in database', [
                    'error' => $e->getMessage(),
                    'subject' => $emailData['subject'] ?? '',
                    'from' => $fromEmail,
                    'message_id' => $messageId,
                    'trace' => $e->getTraceAsString(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error storing Gmail API email in database', [
                'error' => $e->getMessage(),
                'email_account_id' => $emailAccount->id,
            ]);
            return null;
        }
    }

    /**
     * Monitor email account using native PHP IMAP functions
     * This method provides direct IMAP access and GTBank-specific parsing
     */
    protected function monitorEmailAccountNativeImap(EmailAccount $emailAccount): void
    {
        try {
            $this->info("Using native IMAP for: {$emailAccount->email}");
            
            // Build IMAP connection string
            $host = $emailAccount->host ?? 'mail.check-outpay.com';
            $port = $emailAccount->port ?? 993;
            $encryption = $emailAccount->encryption ?? 'ssl';
            $folder = $emailAccount->folder ?? 'INBOX';
            
            $connectionString = "{{$host}:{$port}/imap/{$encryption}}{$folder}";
            
            // Connect to IMAP
            $imap = @imap_open($connectionString, $emailAccount->email, $emailAccount->password);
            
            if (!$imap) {
                $error = imap_last_error();
                Log::error('Native IMAP connection failed', [
                    'email_account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                    'error' => $error,
                ]);
                $this->error("IMAP connection failed: {$error}");
                return;
            }
            
            // Search for unseen emails
            $emails = imap_search($imap, 'UNSEEN');
            
            if (!$emails) {
                $this->info("No new emails found for {$emailAccount->email}");
                imap_close($imap);
                return;
            }
            
            $this->info("Found " . count($emails) . " new email(s)");
            
            foreach ($emails as $emailNumber) {
                try {
                    $overview = imap_fetch_overview($imap, $emailNumber, 0)[0];
                    
                    // Get email body (try multipart first, then plain)
                    $body = imap_fetchbody($imap, $emailNumber, 1.1) 
                        ?: imap_fetchbody($imap, $emailNumber, 1);
                    
                    // Decode if needed
                    $body = imap_base64($body) ?: $body;
                    $cleanBody = trim(strip_tags($body));
                    
                    // Parse GTBank email fields
                    $payload = $this->parseGtbankEmail($overview, $cleanBody, $body);
                    
                    if ($payload) {
                        // Forward to webhook endpoint (same as Zapier)
                        $this->forwardToWebhook($payload, $emailAccount);
                        
                        // Mark as seen
                        imap_setflag_full($imap, $emailNumber, "\\Seen");
                        
                        $this->info("✓ Processed email: {$overview->subject}");
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing native IMAP email', [
                        'email_account_id' => $emailAccount->id,
                        'email_number' => $emailNumber,
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("Error processing email #{$emailNumber}: {$e->getMessage()}");
                    continue;
                }
            }
            
            imap_close($imap);
            $this->info("Native IMAP monitoring completed for {$emailAccount->email}");
        } catch (\Exception $e) {
            Log::error('Error in native IMAP monitoring', [
                'email_account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);
            $this->error("Native IMAP error: {$e->getMessage()}");
        }
    }
    
    /**
     * Parse GTBank email and extract payment information
     */
    protected function parseGtbankEmail($overview, string $cleanBody, string $htmlBody): ?array
    {
        try {
            $payload = [];
            
            // Extract sender email
            preg_match('/<(.+?)>/', $overview->from ?? '', $emailMatch);
            $payload['email'] = $emailMatch[1] ?? $overview->from ?? null;
            
            if (empty($payload['email'])) {
                return null; // Can't process without sender email
            }
            
            // Filter out noreply@xtrapay.ng emails
            if (strtolower($payload['email']) === 'noreply@xtrapay.ng') {
                return null;
            }
            
            // Amount (handle both "NGN 500" and "₦500" formats)
            preg_match('/Amount\s*:\s*(NGN|₦|N)\s*([\d,\.]+)/i', $cleanBody, $amountMatch);
            if (isset($amountMatch[2])) {
                $amount = str_replace(',', '', $amountMatch[2]);
                $currency = strtoupper($amountMatch[1] ?? 'NGN');
                $payload['amount'] = $currency . ' ' . $amount;
            } else {
                // Try alternative patterns
                preg_match('/(NGN|₦|N)\s*([\d,\.]+)/i', $cleanBody, $altAmountMatch);
                if (isset($altAmountMatch[2])) {
                    $amount = str_replace(',', '', $altAmountMatch[2]);
                    $currency = strtoupper($altAmountMatch[1] ?? 'NGN');
                    $payload['amount'] = $currency . ' ' . $amount;
                } else {
                    return null; // Can't process without amount
                }
            }
            
            // Sender name (from Description field: "FROM <NAME> TO")
            preg_match('/FROM\s+([A-Z\s]+)\s+TO/i', $cleanBody, $nameMatch);
            $payload['sender_name'] = trim($nameMatch[1] ?? '');
            
            // Time sent
            preg_match('/Time of Transaction\s*:\s*([\d:APM\s]+)/i', $cleanBody, $timeMatch);
            if (isset($timeMatch[1])) {
                $payload['time_sent'] = trim($timeMatch[1]);
            } else {
                // Fallback to email date
                $payload['time_sent'] = date('h:i:s A', strtotime($overview->date ?? 'now'));
            }
            
            // Additional fields for logging/debugging
            preg_match('/Account Number\s*:\s*(\d+)/i', $cleanBody, $accountMatch);
            $payload['account_number'] = $accountMatch[1] ?? null;
            
            preg_match('/Transaction Location\s*:\s*([\d\w]+)/i', $cleanBody, $locationMatch);
            $payload['transaction_location'] = $locationMatch[1] ?? null;
            
            preg_match('/Description\s*:\s*(.+?)(?:\s{2,}|\n|$)/is', $cleanBody, $descMatch);
            $payload['description'] = trim($descMatch[1] ?? '');
            
            preg_match('/Value Date\s*:\s*([\d\-]+)/i', $cleanBody, $valueDateMatch);
            $payload['value_date'] = $valueDateMatch[1] ?? null;
            
            preg_match('/Remarks\s*:\s*(.+?)(?:\s{2,}|\n|$)/is', $cleanBody, $remarksMatch);
            $payload['remarks'] = trim($remarksMatch[1] ?? '');
            
            preg_match('/Current Balance\s*:\s*(NGN|₦|N)\s*([\d,\.]+)/i', $cleanBody, $curBalMatch);
            if (isset($curBalMatch[2])) {
                $balance = str_replace(',', '', $curBalMatch[2]);
                $currency = strtoupper($curBalMatch[1] ?? 'NGN');
                $payload['current_balance'] = $currency . ' ' . $balance;
            }
            
            preg_match('/Available Balance\s*:\s*(NGN|₦|N)\s*([\d,\.]+)/i', $cleanBody, $availBalMatch);
            if (isset($availBalMatch[2])) {
                $balance = str_replace(',', '', $availBalMatch[2]);
                $currency = strtoupper($availBalMatch[1] ?? 'NGN');
                $payload['available_balance'] = $currency . ' ' . $balance;
            }
            
            preg_match('/Document Number\s*:\s*(.+)/i', $cleanBody, $docMatch);
            $payload['document_number'] = trim($docMatch[1] ?? '');
            
            // Include full email content for webhook processing
            $payload['email_content'] = $htmlBody ?: $cleanBody;
            
            return $payload;
        } catch (\Exception $e) {
            Log::error('Error parsing GTBank email', [
                'error' => $e->getMessage(),
                'subject' => $overview->subject ?? '',
            ]);
            return null;
        }
    }
    
    /**
     * Forward parsed payload to webhook endpoint
     */
    protected function forwardToWebhook(array $payload, EmailAccount $emailAccount): void
    {
        try {
            $webhookUrl = url('/api/v1/email/webhook');
            
            // Get webhook secret if configured
            $webhookSecret = \App\Models\Setting::get('zapier_webhook_secret');
            
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
            
            if ($webhookSecret) {
                $headers['X-Zapier-Secret'] = $webhookSecret;
            }
            
            $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                ->post($webhookUrl, $payload);
            
            if ($response->successful()) {
                $this->info("✓ Webhook forwarded successfully");
                Log::info('Email forwarded to webhook', [
                    'email_account_id' => $emailAccount->id,
                    'sender' => $payload['sender_name'] ?? '',
                    'amount' => $payload['amount'] ?? '',
                ]);
            } else {
                Log::warning('Webhook forwarding failed', [
                    'email_account_id' => $emailAccount->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                $this->warn("⚠ Webhook forwarding failed: {$response->status()}");
            }
        } catch (\Exception $e) {
            Log::error('Error forwarding to webhook', [
                'email_account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);
            $this->error("Error forwarding to webhook: {$e->getMessage()}");
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
            // If it's already a Carbon instance, ensure it's in app timezone
            if ($dateValue instanceof \Carbon\Carbon) {
                return $dateValue->setTimezone(config('app.timezone'));
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

            // If we have a string value, parse it and set to app timezone
            if (is_string($dateValue) && !empty($dateValue)) {
                $carbon = \Carbon\Carbon::parse($dateValue);
                // Set to app timezone (Africa/Lagos) to ensure consistency
                return $carbon->setTimezone(config('app.timezone'));
            }

            // Fallback to current time in app timezone
            return now()->setTimezone(config('app.timezone'));
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
