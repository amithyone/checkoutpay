<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class TestGmailConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:test-gmail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Gmail IMAP connection';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🔍 Testing Gmail Connection...');
        $this->newLine();

        // Check configuration
        $this->info('📋 Configuration Check:');
        $this->line('  Host: ' . config('payment.email_host'));
        $this->line('  Port: ' . config('payment.email_port'));
        $this->line('  Encryption: ' . config('payment.email_encryption'));
        $this->line('  Username: ' . config('payment.email_user') ?: '❌ NOT SET');
        $this->line('  Password: ' . (config('payment.email_password') ? '✅ SET' : '❌ NOT SET'));
        $this->newLine();

        if (empty(config('payment.email_user')) || empty(config('payment.email_password'))) {
            $this->error('❌ Email credentials not configured!');
            $this->line('Please set EMAIL_USER and EMAIL_PASSWORD in your .env file');
            $this->line('See GMAIL_SETUP.md for detailed instructions');
            return;
        }

        // Test connection
        $this->info('🔌 Testing Connection...');
        
        try {
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

            $client = $cm->account('gmail');
            $client->connect();

            $this->info('✅ Successfully connected to Gmail!');
            $this->newLine();

            // Get folder info
            $folder = $client->getFolder('INBOX');
            $this->info('📧 Inbox Information:');
            
            $totalMessages = $folder->query()->all()->get()->count();
            $unreadMessages = $folder->query()->unseen()->get()->count();
            
            $this->line('  Total Messages: ' . $totalMessages);
            $this->line('  Unread Messages: ' . $unreadMessages);
            $this->newLine();

            // Check recent unread emails
            if ($unreadMessages > 0) {
                $this->info('📬 Recent Unread Emails (last 5):');
                $recentEmails = $folder->query()
                    ->unseen()
                    ->since(now()->subWeek())
                    ->limit(5)
                    ->get();

                foreach ($recentEmails as $message) {
                    $this->line('  • ' . $message->getSubject() . ' (from: ' . ($message->getFrom()[0]->mail ?? 'Unknown') . ')');
                }
            } else {
                $this->line('  No unread emails found');
            }

            $client->disconnect();
            $this->newLine();
            $this->info('✅ Gmail connection test completed successfully!');
            $this->line('Your Gmail account is ready for payment monitoring.');

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Connection Failed!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            
            $this->warn('Common Issues:');
            $this->line('  1. Make sure 2FA is enabled on your Google Account');
            $this->line('  2. Use an App Password (not your regular password)');
            $this->line('  3. Generate App Password at: https://myaccount.google.com/apppasswords');
            $this->line('  4. Check that EMAIL_USER and EMAIL_PASSWORD are correct in .env');
            $this->line('  5. Verify internet connection');
            $this->newLine();
            $this->line('See GMAIL_SETUP.md for detailed troubleshooting guide');
        }
    }
}
