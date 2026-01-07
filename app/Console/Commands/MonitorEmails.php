<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailPayment;
use App\Models\EmailAccount;
use App\Services\GmailApiService;
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
            
            // Only check emails after the oldest pending payment was created
            // This ensures we don't process old emails for new transactions
            // Check ALL pending payments (including businesses without email accounts)
            // If business has email account, it will be matched only to that account
            // If business has NO email account, it can be matched from any email account
            $oldestPendingPayment = \App\Models\Payment::pending()
                ->orderBy('created_at', 'asc')
                ->first();
            
            // If no pending payments, don't check emails (nothing to match against)
            if (!$oldestPendingPayment) {
                $this->info("No pending payments found. Skipping email check.");
                $client->disconnect();
                return;
            }
            
            // Only check emails received after the oldest pending payment was created
            $sinceDate = $oldestPendingPayment->created_at->subMinutes(5); // 5 min buffer for email delivery delay
            
            // Build query with date filter and payment-related keywords to reduce emails checked
            $query = $folder->query()->unseen()->since($sinceDate);
            
            // Add keyword filters to only get payment-related emails
            // This significantly reduces the number of emails to process
            // Expanded keywords to match various bank notification formats
            $keywords = [
                'transfer', 'deposit', 'credit', 'payment', 'transaction', 
                'alert', 'notification', 'received', 'credited', 'debit',
                'gens', 'electronic', 'bank', 'account', 'amount', 'ngn',
                'naira', 'value date', 'transaction location', 'document number'
            ];
            $keywordQuery = implode(' OR ', array_map(fn($kw) => "TEXT \"{$kw}\"", $keywords));
            
            // Try to use keyword filter (may not work on all IMAP servers, so we'll also filter manually)
            try {
                $messages = $query->text($keywordQuery)->get();
            } catch (\Exception $e) {
                // If keyword search fails, get all and filter manually
                $messages = $query->get();
            }

            $accountEmail = $emailAccount ? $emailAccount->email : 'default account';
            $this->info("Found {$messages->count()} email(s) in {$accountEmail} (after {$sinceDate->format('Y-m-d H:i:s')})");

            $processedCount = 0;
            $skippedCount = 0;
            
            foreach ($messages as $message) {
                try {
                    $subject = strtolower($message->getSubject() ?? '');
                    $text = strtolower($message->getTextBody() ?? '');
                    $fromEmail = $message->getFrom()[0]->mail ?? '';
                    
                    // Filter by allowed senders if configured
                    if ($emailAccount && !$emailAccount->isSenderAllowed($fromEmail)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Filter by keywords - only process payment-related emails
                    $hasPaymentKeyword = false;
                    foreach ($keywords as $keyword) {
                        if (strpos($subject, $keyword) !== false || strpos($text, $keyword) !== false) {
                            $hasPaymentKeyword = true;
                            break;
                        }
                    }
                    
                    // Also check for amount patterns (numbers with currency symbols)
                    if (!$hasPaymentKeyword && preg_match('/[₦$]?\s*[\d,]+\.?\d*/', $subject . ' ' . $text)) {
                        $hasPaymentKeyword = true;
                    }
                    
                    if (!$hasPaymentKeyword) {
                        $skippedCount++;
                        // Mark as read even if skipped (to avoid checking again)
                        $message->setFlag('Seen');
                        continue;
                    }
                    
                    $this->processMessage($message, $emailAccount);
                    $processedCount++;
                    
                    // Mark as read
                    $message->setFlag('Seen');
                } catch (\Exception $e) {
                    Log::error('Error processing email message', [
                        'error' => $e->getMessage(),
                        'email_account_id' => $emailAccount?->id,
                    ]);
                    $skippedCount++;
                    continue;
                }
            }
            
            $this->info("Processed: {$processedCount}, Skipped: {$skippedCount} (non-payment emails)");

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
            
            // Only check emails after the oldest pending payment was created
            // This ensures we don't process old emails for new transactions
            // Check ALL pending payments (including businesses without email accounts)
            // If business has email account, it will be matched only to that account
            // If business has NO email account, it can be matched from any email account
            $oldestPendingPayment = \App\Models\Payment::pending()
                ->orderBy('created_at', 'asc')
                ->first();
            
            // If no pending payments, don't check emails (nothing to match against)
            if (!$oldestPendingPayment) {
                $this->info("No pending payments found. Skipping email check for {$emailAccount->email}.");
                return;
            }
            
            // Only check emails received after the oldest pending payment was created
            $since = $oldestPendingPayment->created_at->subMinutes(5); // 5 min buffer for email delivery delay
            
            // Get messages with keyword filtering
            $messages = $gmailService->getMessagesSince($since, [
                'keywords' => ['transfer', 'deposit', 'credit', 'payment', 'transaction', 'alert', 'notification', 'received', 'credited']
            ]);
            
            $this->info("Found " . count($messages) . " email(s) in {$emailAccount->email} (Gmail API) (after {$since->format('Y-m-d H:i:s')})");
            
            $processedCount = 0;
            $skippedCount = 0;
            
            foreach ($messages as $emailData) {
                try {
                    $fromEmail = $emailData['from'] ?? '';
                    $subject = strtolower($emailData['subject'] ?? '');
                    $text = strtolower($emailData['text'] ?? '');
                    
                    // Filter by allowed senders if configured
                    if (!$emailAccount->isSenderAllowed($fromEmail)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Additional keyword filtering (Gmail API may not filter perfectly)
                    $paymentKeywords = [
                        'transfer', 'deposit', 'credit', 'payment', 'transaction', 
                        'alert', 'notification', 'received', 'credited', 'debit',
                        'gens', 'electronic', 'bank', 'account', 'amount'
                    ];
                    
                    $hasPaymentKeyword = false;
                    $combinedText = strtolower($subject . ' ' . $text);
                    
                    foreach ($paymentKeywords as $keyword) {
                        if (strpos($combinedText, $keyword) !== false) {
                            $hasPaymentKeyword = true;
                            break;
                        }
                    }
                    
                    // Check for amount patterns (NGN format, currency symbols, etc.)
                    if (!$hasPaymentKeyword && (
                        preg_match('/[₦$]?\s*[\d,]+\.?\d*/', $combinedText) ||
                        preg_match('/ngn\s*[\d,]+\.?\d*/i', $combinedText) ||
                        preg_match('/naira\s*[\d,]+\.?\d*/i', $combinedText) ||
                        preg_match('/amount\s*:?\s*[\d,]+\.?\d*/i', $combinedText)
                    )) {
                        $hasPaymentKeyword = true;
                    }
                    
                    // Check for account number patterns
                    if (!$hasPaymentKeyword && preg_match('/account\s*number\s*:?\s*\d{8,}/i', $combinedText)) {
                        $hasPaymentKeyword = true;
                    }
                    
                    if (!$hasPaymentKeyword) {
                        $skippedCount++;
                        // Mark as read even if skipped
                        if (isset($emailData['id'])) {
                            $gmailService->markAsRead($emailData['id']);
                        }
                        continue;
                    }
                    
                    $this->processGmailApiMessage($emailData, $emailAccount);
                    $processedCount++;
                    
                    // Mark as read
                    if (isset($emailData['id'])) {
                        $gmailService->markAsRead($emailData['id']);
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing Gmail API message', [
                        'error' => $e->getMessage(),
                        'email_account_id' => $emailAccount->id,
                    ]);
                    $skippedCount++;
                    continue;
                }
            }
            
            $this->info("Processed: {$processedCount}, Skipped: {$skippedCount} (non-payment emails)");
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
            $emailData = [
                'subject' => $message->getSubject(),
                'from' => $message->getFrom()[0]->mail ?? '',
                'text' => $message->getTextBody(),
                'html' => $message->getHTMLBody(),
                'date' => $message->getDate()->toDateTimeString(),
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
}
