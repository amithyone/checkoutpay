<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailPayment;
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

        try {
            $client = $this->getImapClient();
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->since(now()->subDay())->get();

            $this->info("Found {$messages->count()} new email(s)");

            foreach ($messages as $message) {
                $this->processMessage($message);
                
                // Mark as read
                $message->setFlag('Seen');
            }

            $client->disconnect();
            $this->info('Email monitoring completed');
        } catch (\Exception $e) {
            Log::error('Error monitoring emails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Error monitoring emails: ' . $e->getMessage());
        }
    }

    /**
     * Get IMAP client instance
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
                    'validate_cert' => config('payment.email_validate_cert', false), // Gmail sometimes has cert issues
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
    protected function processMessage($message): void
    {
        try {
            $emailData = [
                'subject' => $message->getSubject(),
                'from' => $message->getFrom()[0]->mail ?? '',
                'text' => $message->getTextBody(),
                'html' => $message->getHTMLBody(),
                'date' => $message->getDate()->toDateTimeString(),
            ];

            $this->info("Processing email: {$emailData['subject']}");

            // Dispatch job to process email
            ProcessEmailPayment::dispatch($emailData);

            $this->info("Dispatched job for email: {$emailData['subject']}");
        } catch (\Exception $e) {
            Log::error('Error processing email message', [
                'error' => $e->getMessage(),
            ]);

            $this->warn("Error processing email: {$e->getMessage()}");
        }
    }
}
