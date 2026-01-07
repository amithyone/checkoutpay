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
            $messages = $folder->query()->unseen()->since($sinceDate)->get();

            $accountEmail = $emailAccount ? $emailAccount->email : 'default account';
            $this->info("Found {$messages->count()} new email(s) in {$accountEmail} (after {$sinceDate->format('Y-m-d H:i:s')})");

            foreach ($messages as $message) {
                // Filter by allowed senders if configured
                $fromEmail = $message->getFrom()[0]->mail ?? '';
                if ($emailAccount && !$emailAccount->isSenderAllowed($fromEmail)) {
                    $this->info("Skipping email from {$fromEmail} (not in allowed senders list)");
                    continue;
                }
                
                $this->processMessage($message, $emailAccount);
                
                // Mark as read
                $message->setFlag('Seen');
            }

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
            $messages = $gmailService->getMessagesSince($since);
            
            $this->info("Found " . count($messages) . " new email(s) in {$emailAccount->email} (Gmail API) (after {$since->format('Y-m-d H:i:s')})");
            
            foreach ($messages as $emailData) {
                // Filter by allowed senders if configured
                $fromEmail = $emailData['from'] ?? '';
                if (!$emailAccount->isSenderAllowed($fromEmail)) {
                    $this->info("Skipping email from {$fromEmail} (not in allowed senders list)");
                    continue;
                }
                
                $this->processGmailApiMessage($emailData, $emailAccount);
                
                // Mark as read
                if (isset($emailData['id'])) {
                    $gmailService->markAsRead($emailData['id']);
                }
            }
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
