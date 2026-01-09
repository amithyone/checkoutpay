<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class WatchEmails extends Command
{
    protected $signature = 'payment:watch-emails {--interval=10 : Check interval in seconds} {--once : Run once and exit}';
    protected $description = 'Continuously watch for new emails and process them in real-time';

    protected $lastCheckTime = [];
    protected $lastFileCounts = [];

    public function handle(): void
    {
        $interval = (int) $this->option('interval');
        $runOnce = $this->option('once');

        $this->info("🔍 Starting email watcher (checking every {$interval} seconds)");
        $this->info("Press Ctrl+C to stop");
        $this->newLine();

        do {
            try {
                $this->checkForNewEmails();
                
                if (!$runOnce) {
                    sleep($interval);
                }
            } catch (\Exception $e) {
                Log::error('Error in email watcher', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("Error: {$e->getMessage()}");
                
                if (!$runOnce) {
                    sleep($interval);
                }
            }
        } while (!$runOnce);

        $this->info("✅ Email watcher stopped");
    }

    protected function checkForNewEmails(): void
    {
        $emailAccounts = EmailAccount::where('is_active', true)->get();

        if ($emailAccounts->isEmpty()) {
            $this->warn('No active email accounts found');
            return;
        }

        foreach ($emailAccounts as $emailAccount) {
            $this->checkEmailAccount($emailAccount);
        }
    }

    protected function checkEmailAccount(EmailAccount $emailAccount): void
    {
        $accountKey = $emailAccount->id . '_' . $emailAccount->email;
        
        // Find mail directory
        $mailPaths = $this->findMailDirectory($emailAccount->email);

        if (empty($mailPaths)) {
            return;
        }

        foreach ($mailPaths as $mailPath) {
            // Check if directory exists
            if (!is_dir($mailPath) && !is_file($mailPath)) {
                continue;
            }

            // Count files in directory
            $fileCount = $this->countEmailFiles($mailPath);
            
            // Check if we've seen this directory before
            if (!isset($this->lastFileCounts[$mailPath])) {
                $this->lastFileCounts[$mailPath] = $fileCount;
                continue; // First check, just record the count
            }

            // If file count increased, we have new emails!
            if ($fileCount > $this->lastFileCounts[$mailPath]) {
                $newFileCount = $fileCount - $this->lastFileCounts[$mailPath];
                
                $this->info("📧 New emails detected! ({$newFileCount} new file(s))");
                $this->info("   Account: {$emailAccount->email}");
                $this->info("   Path: {$mailPath}");

                // Read and process new emails
                \Illuminate\Support\Facades\Artisan::call('payment:read-emails-direct', [
                    '--email' => $emailAccount->email,
                ]);

                $output = \Illuminate\Support\Facades\Artisan::output();
                if ($output) {
                    $this->line($output);
                }

                // Also trigger regular email monitoring
                \Illuminate\Support\Facades\Artisan::call('payment:monitor-emails');

                // Update last file count
                $this->lastFileCounts[$mailPath] = $fileCount;

                Log::info('New emails detected and processed', [
                    'email_account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                    'mail_path' => $mailPath,
                    'new_file_count' => $newFileCount,
                ]);
            }

            // Update last check time
            $this->lastCheckTime[$mailPath] = now();
        }
    }

    protected function findMailDirectory(string $email): array
    {
        $paths = [];
        list($localPart, $domain) = explode('@', $email);
        $username = $this->getUsername();

        $commonPaths = [
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/",
            "/home/{$username}/mail/{$domain}/{$localPart}/",
        ];

        foreach ($commonPaths as $path) {
            if (is_dir($path) || is_file($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    protected function countEmailFiles(string $path): int
    {
        if (is_file($path)) {
            // mbox format - count emails in file
            $content = file_get_contents($path);
            if (!$content) {
                return 0;
            }
            // Count "From " lines (mbox separator)
            return substr_count($content, "\nFrom ");
        }

        if (!is_dir($path)) {
            return 0;
        }

        // Maildir format - count files in directory
        $files = scandir($path);
        if (!$files) {
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . '/' . $file;
            
            // Check if it's a subdirectory (like Maildir/cur, Maildir/new)
            if (is_dir($filePath)) {
                $count += $this->countEmailFiles($filePath);
            } elseif (is_file($filePath)) {
                $count++;
            }
        }

        return $count;
    }

    protected function getUsername(): ?string
    {
        $username = getenv('USER') ?: getenv('USERNAME');
        
        if (!$username && function_exists('posix_getpwuid')) {
            $username = posix_getpwuid(posix_geteuid())['name'] ?? null;
        }

        if (!$username && function_exists('shell_exec')) {
            $username = trim(shell_exec('whoami') ?: '');
        }

        if (!$username) {
            $home = getenv('HOME');
            if ($home) {
                $parts = explode('/', $home);
                $username = end($parts);
            }
        }

        // Fallback: from earlier debug we know username is checzspw
        return $username ?: 'checzspw';
    }
}
