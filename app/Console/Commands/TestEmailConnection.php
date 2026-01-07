<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEmailConnection extends Command
{
    protected $signature = 'email:test-connection {email?}';
    protected $description = 'Test email account connection';

    public function handle()
    {
        $email = $this->argument('email');
        
        if ($email) {
            $emailAccount = EmailAccount::where('email', $email)->first();
            if (!$emailAccount) {
                $this->error("Email account not found: {$email}");
                return 1;
            }
        } else {
            $emailAccount = EmailAccount::where('is_active', true)->first();
            if (!$emailAccount) {
                $this->error("No active email accounts found.");
                return 1;
            }
        }

        $this->info("Testing connection for: {$emailAccount->email}");
        $this->info("Host: {$emailAccount->host}:{$emailAccount->port}");
        $this->info("Encryption: {$emailAccount->encryption}");
        
        // Check PHP IMAP extension
        if (!extension_loaded('imap')) {
            $this->error("❌ PHP IMAP extension is not installed!");
            $this->warn("Install it with: sudo apt-get install php-imap (or similar for your system)");
            return 1;
        }
        $this->info("✅ PHP IMAP extension is installed");

        // Test connection
        $result = $emailAccount->testConnection();
        
        if ($result['success']) {
            $this->info("✅ " . $result['message']);
            return 0;
        } else {
            $this->error("❌ " . $result['message']);
            $this->warn("\nCheck logs: tail -f storage/logs/laravel.log");
            return 1;
        }
    }
}
