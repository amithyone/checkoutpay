<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailPayment;
use App\Models\EmailAccount;
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
                $client = $this->getImapClientForAccount($emailAccount);
                $this->monitorEmailAccount($client, $emailAccount);
            } catch (\Exception $e) {
                Log::error('Error monitoring email account', [
                    'email_account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
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
            $messages = $folder->query()->unseen()->since(now()->subDay())->get();

            $this->info("Found {$messages->count()} new email(s) in {$emailAccount?->email ?? 'default account'}");

            foreach ($messages as $message) {
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
