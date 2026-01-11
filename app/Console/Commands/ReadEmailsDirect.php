<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use App\Jobs\ProcessEmailPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReadEmailsDirect extends Command
{
    protected $signature = 'payment:read-emails-direct {--email= : Email account to read (default: notify@check-outpay.com)} {--all : Read from all active email accounts}';
    protected $description = 'Read emails directly from server mail files (bypasses IMAP)';

    public function handle(): void
    {
        $this->info('Reading emails directly from server filesystem...');

        // Get email accounts to process
        if ($this->option('all')) {
            // Process all active email accounts
            $emailAccounts = EmailAccount::where('is_active', true)->get();
            
            if ($emailAccounts->isEmpty()) {
                $this->warn('No active email accounts found in database.');
                return;
            }

            $this->info("Processing {$emailAccounts->count()} active email account(s)...");
        } else {
            // Process specific email account
            $emailAddress = $this->option('email') ?? 'notify@check-outpay.com';
            $emailAccount = EmailAccount::where('email', $emailAddress)->first();

            if (!$emailAccount) {
                $this->error("Email account not found: {$emailAddress}");
                $this->info("Available email accounts:");
                EmailAccount::all()->each(function ($account) {
                    $this->info("  - {$account->email} (Active: " . ($account->is_active ? 'Yes' : 'No') . ")");
                });
                return;
            }

            $emailAccounts = collect([$emailAccount]);
        }

        $totalRead = 0;

        foreach ($emailAccounts as $emailAccount) {
            $this->info("Reading emails for: {$emailAccount->email}");

            // Try to find mail directory
            $mailPaths = $this->findMailDirectory($emailAccount->email);

            if (empty($mailPaths)) {
                $this->warn("Could not find mail directory for {$emailAccount->email}");
                $this->info('Common cPanel mail paths:');
                $username = $this->getUsername();
                list($localPart, $domain) = explode('@', $emailAccount->email);
                $this->info("  /home/{$username}/mail/{$domain}/{$localPart}/Maildir/");
                $this->info("  /home/{$username}/mail/{$domain}/{$localPart}/");
                continue;
            }

            $this->info('Found mail directory(s):');
            foreach ($mailPaths as $path) {
                $this->info("  - {$path}");
            }

            // Read emails from each path
            $accountReadCount = 0;
            $accountSkippedCount = 0;
            $accountFailedCount = 0;
            foreach ($mailPaths as $mailPath) {
                $result = $this->readEmailsFromPath($mailPath, $emailAccount);
                if (is_array($result)) {
                    $accountReadCount += $result['processed'] ?? 0;
                    $accountSkippedCount += $result['skipped'] ?? 0;
                    $accountFailedCount += $result['failed'] ?? 0;
                } else {
                    // Backward compatibility
                    $accountReadCount += $result;
                }
            }
            
            $totalRead += $accountReadCount;
            $totalSkipped += $accountSkippedCount;
            $totalFailed += $accountFailedCount;
            
            $this->info("   ✅ Processed: {$accountReadCount} email(s)");
            if ($accountSkippedCount > 0) {
                $this->warn("   ⏭️  Skipped: {$accountSkippedCount} email(s) (duplicates or sender not allowed)");
            }
            if ($accountFailedCount > 0) {
                $this->error("   ❌ Failed: {$accountFailedCount} email(s) (parsing errors)");
            }
            $this->newLine();
        }

        $this->info("═══════════════════════════════════════════");
        $this->info("✅ Total processed: {$totalRead} email(s)");
        if ($totalSkipped > 0) {
            $this->warn("⏭️  Total skipped: {$totalSkipped} email(s)");
        }
        if ($totalFailed > 0) {
            $this->error("❌ Total failed: {$totalFailed} email(s)");
        }
        $this->info("═══════════════════════════════════════════");
        
        if ($totalRead > 0) {
            $this->info("📧 Emails have been processed and matching jobs dispatched!");
            $this->info("   Processing jobs will automatically match payments if found.");
        } else {
            if ($totalSkipped > 0 || $totalFailed > 0) {
                $this->warn("ℹ️  No new emails processed. Check skipped/failed counts above.");
            } else {
                $this->info("ℹ️  No new emails found (or all emails already processed).");
            }
        }
    }

    /**
     * Find mail directory for email account
     */
    protected function findMailDirectory(string $email): array
    {
        $paths = [];

        // Extract domain and local part
        list($localPart, $domain) = explode('@', $email);
        $username = $this->getUsername();

        // Common cPanel mail paths (try multiple formats)
        $commonPaths = [
            // Standard cPanel Maildir format
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/",
            
            // Alternative Maildir formats
            "/home/{$username}/mail/{$domain}/{$localPart}/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}/",
            
            // cPanel sometimes uses domain-localpart format
            "/home/{$username}/mail/{$domain}/{$localPart}-{$domain}/Maildir/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}-{$domain}/Maildir/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}-{$domain}/Maildir/",
            
            // Root mail paths (mbox format)
            "/var/spool/mail/{$localPart}",
            "/var/mail/{$localPart}",
            "/var/spool/mail/{$username}",
        ];

        foreach ($commonPaths as $path) {
            if (is_dir($path) && is_readable($path)) {
                $this->info("✅ Found mail directory: {$path}");
                $paths[] = $path;
            } elseif (is_file($path) && is_readable($path)) {
                // mbox format
                $this->info("✅ Found mail file: {$path}");
                $paths[] = $path;
            }
        }

        // If no paths found, try to search dynamically
        if (empty($paths)) {
            $this->warn("Standard paths not found. Attempting dynamic search...");
            
            // Search in mail directory
            $mailBase = "/home/{$username}/mail/";
            if (is_dir($mailBase) && is_readable($mailBase)) {
                $foundPaths = $this->searchForEmailDirectory($mailBase, $localPart, $domain);
                $paths = array_merge($paths, $foundPaths);
            }
        }

        // If still nothing, try checking if we need to look at domain differently
        if (empty($paths)) {
            // Some hosts store mail differently - check parent mail directory structure
            $mailParent = "/home/{$username}/mail/";
            if (is_dir($mailParent) && is_readable($mailParent)) {
                $this->info("Exploring mail directory structure...");
                try {
                    $domains = scandir($mailParent);
                    foreach ($domains as $foundDomain) {
                        if ($foundDomain === '.' || $foundDomain === '..') {
                            continue;
                        }
                        $domainPath = $mailParent . $foundDomain;
                        if (is_dir($domainPath)) {
                            $this->info("  Found domain directory: {$foundDomain}");
                            // Check if our email exists in any domain
                            $emailPath = $domainPath . '/' . $localPart . '/Maildir/';
                            if (is_dir($emailPath)) {
                                $this->info("✅ Found email in domain {$foundDomain}: {$emailPath}");
                                $paths[] = $emailPath . 'cur/';
                                $paths[] = $emailPath . 'new/';
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Error exploring mail directory', ['error' => $e->getMessage()]);
                }
            }
        }

        return array_unique($paths); // Remove duplicates
    }

    /**
     * Recursively search for email directory
     */
    protected function searchForEmailDirectory(string $basePath, string $localPart, string $domain): array
    {
        $found = [];
        
        try {
            if (!is_dir($basePath) || !is_readable($basePath)) {
                return $found;
            }

            $items = scandir($basePath);
            if (!$items) {
                return $found;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $basePath . '/' . $item;
                
                // Check if this matches our email structure
                if (is_dir($itemPath)) {
                    // Check if it's the domain directory
                    if ($item === $domain || strpos($item, $domain) !== false) {
                        $emailPath = $itemPath . '/' . $localPart;
                        if (is_dir($emailPath)) {
                            $maildirPath = $emailPath . '/Maildir';
                            if (is_dir($maildirPath)) {
                                $found[] = $maildirPath . '/cur/';
                                $found[] = $maildirPath . '/new/';
                            } else {
                                $found[] = $emailPath . '/cur/';
                                $found[] = $emailPath . '/new/';
                            }
                        }
                    }
                    
                    // Recursively search (limit depth to avoid infinite loops)
                    if (strlen($itemPath) < 200) { // Reasonable path length limit
                        $subFound = $this->searchForEmailDirectory($itemPath, $localPart, $domain);
                        $found = array_merge($found, $subFound);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('Error in recursive mail search', ['path' => $basePath, 'error' => $e->getMessage()]);
        }

        return $found;
    }

    /**
     * Get server username
     */
    protected function getUsername(): ?string
    {
        // Try multiple methods
        $username = getenv('USER') ?: getenv('USERNAME');
        
        if (!$username) {
            $username = posix_getpwuid(posix_geteuid())['name'] ?? null;
        }

        // Try from cPanel username (if available)
        if (!$username && function_exists('shell_exec')) {
            $username = trim(shell_exec('whoami') ?: '');
        }

        // Try from home directory
        if (!$username) {
            $home = getenv('HOME');
            if ($home) {
                $parts = explode('/', $home);
                $username = end($parts);
            }
        }

        // Try from email account - common pattern: checzspw@premium340
        if (!$username) {
            $emailAccount = EmailAccount::where('email', 'like', '%@%')->first();
            if ($emailAccount && strpos($emailAccount->host, 'premium340') !== false) {
                // From earlier debug, username is checzspw
                $username = 'checzspw';
            }
        }

        return $username ?: null;
    }

    /**
     * Read emails from Maildir format
     */
    protected function readEmailsFromPath(string $path, EmailAccount $emailAccount)
    {
        if (!is_dir($path) && !is_file($path)) {
            return ['processed' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $this->info("Reading from: {$path}");

        // Check if it's Maildir format (has cur/ and new/ subdirectories)
        if (is_dir($path . '/cur') || is_dir($path . '/new')) {
            return $this->readMaildirFormat($path, $emailAccount);
        }

        // Check if it's mbox format (single file)
        if (is_file($path)) {
            $count = $this->readMboxFormat($path, $emailAccount);
            return ['processed' => $count, 'skipped' => 0, 'failed' => 0];
        }

        // Try reading files directly from path
        if (is_dir($path)) {
            $count = $this->readDirectory($path, $emailAccount);
            return ['processed' => $count, 'skipped' => 0, 'failed' => 0];
        }

        return ['processed' => 0, 'skipped' => 0, 'failed' => 0];
    }

    /**
     * Read Maildir format emails
     */
    protected function readMaildirFormat(string $basePath, EmailAccount $emailAccount): array
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        // Handle different path formats
        // If path already ends with /cur/ or /new/, use it directly
        // Otherwise, try to find cur/ and new/ subdirectories
        
        $dirsToCheck = [];
        
        if (strpos($basePath, '/cur/') !== false || strpos($basePath, '/new/') !== false) {
            // Path already points to cur/ or new/ directory
            $dirsToCheck[] = $basePath;
        } else {
            // Check if cur/ and new/ subdirectories exist
            if (is_dir($basePath . '/cur')) {
                $dirsToCheck[] = $basePath . '/cur';
            }
            if (is_dir($basePath . '/new')) {
                $dirsToCheck[] = $basePath . '/new';
            }
            
            // If no subdirectories, check if basePath itself is a Maildir structure
            if (empty($dirsToCheck) && is_dir($basePath)) {
                // Try reading directly from basePath (might be cur/ or new/ already)
                $dirsToCheck[] = $basePath;
            }
        }
        
        if (empty($dirsToCheck)) {
            $this->warn("  ⚠️  No Maildir subdirectories found in: {$basePath}");
            return ['processed' => 0, 'skipped' => 0, 'failed' => 0];
        }

        foreach ($dirsToCheck as $dirPath) {
            if (!is_dir($dirPath) || !is_readable($dirPath)) {
                $this->warn("  ⚠️  Cannot read directory: {$dirPath}");
                continue;
            }

            $this->info("  📂 Reading from: {$dirPath}");
            
            try {
                $files = scandir($dirPath);
                if (!$files) {
                    $this->warn("  ⚠️  Could not scan directory: {$dirPath}");
                    continue;
                }

                $emailFiles = array_filter($files, function($file) use ($dirPath) {
                    return $file !== '.' && $file !== '..' && is_file($dirPath . '/' . $file);
                });

                if (empty($emailFiles)) {
                    $this->info("  ℹ️  No email files found in: {$dirPath}");
                    continue;
                }

                $this->info("  📧 Found " . count($emailFiles) . " email file(s)");

                foreach ($emailFiles as $file) {
                    $filePath = $dirPath . '/' . $file;
                    
                    try {
                        $emailContent = file_get_contents($filePath);
                        if (empty($emailContent)) {
                            $this->warn("  ⚠️  Empty file: {$file}");
                            continue;
                        }

                        // Parse email content
                        $result = $this->parseEmailContentWithStats($emailContent, $emailAccount, $file);
                        
                        if ($result['status'] === 'processed') {
                            $processed++;
                            $subject = $result['email']->subject ?? 'No Subject';
                            $this->info("  ✅ Processed: {$subject}");
                        } elseif ($result['status'] === 'skipped') {
                            $skipped++;
                            $reason = $result['reason'] ?? 'unknown';
                            $this->line("  ⏭️  Skipped: {$file} ({$reason})");
                        } else {
                            $failed++;
                            $hasHeaders = preg_match('/^(From|Subject|Date|To|Message-ID|Content-Type):/mi', $emailContent);
                            $this->warn("  ⚠️  Could not parse email from: {$file}");
                            if (!$hasHeaders) {
                                $this->line("     (No recognizable email headers found)");
                            }
                            Log::debug('Email parsing failed', [
                                'file' => $file,
                                'content_length' => strlen($emailContent),
                                'has_headers' => $hasHeaders,
                                'content_preview' => substr($emailContent, 0, 200),
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error reading mail file', [
                            'file' => $filePath,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $this->warn("  ⚠️  Error reading {$file}: {$e->getMessage()}");
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error scanning mail directory', [
                    'path' => $dirPath,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  ⚠️  Error scanning {$dirPath}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Read mbox format emails
     */
    protected function readMboxFormat(string $filePath, EmailAccount $emailAccount): int
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return 0;
        }

        // Split by "From " lines (mbox separator)
        $emails = preg_split('/^From /m', $content);
        $count = 0;

        foreach ($emails as $index => $emailContent) {
            if (empty(trim($emailContent))) {
                continue;
            }

            // Add "From " back for first email (others have it from split)
            if ($index > 0) {
                $emailContent = 'From ' . $emailContent;
            }

            try {
                $parsed = $this->parseEmailContent($emailContent, $emailAccount, "mbox_{$index}");
                if ($parsed) {
                    $count++;
                }
            } catch (\Exception $e) {
                Log::error('Error parsing mbox email', [
                    'file' => $filePath,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Read directory directly
     */
    protected function readDirectory(string $dirPath, EmailAccount $emailAccount): int
    {
        $files = scandir($dirPath);
        if (!$files) {
            return 0;
        }

        $count = 0;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $dirPath . '/' . $file;
            
            if (is_dir($filePath)) {
                // Recursively read subdirectories
                $count += $this->readDirectory($filePath, $emailAccount);
                continue;
            }

            if (!is_file($filePath)) {
                continue;
            }

            try {
                $emailContent = file_get_contents($filePath);
                if (!$emailContent) {
                    continue;
                }

                $parsed = $this->parseEmailContent($emailContent, $emailAccount, $file);
                if ($parsed) {
                    $count++;
                }
            } catch (\Exception $e) {
                Log::debug('Error reading file', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Parse email content with detailed statistics
     */
    protected function parseEmailContentWithStats(string $content, EmailAccount $emailAccount, string $filename): array
    {
        $result = $this->parseEmailContent($content, $emailAccount, $filename);
        
        if ($result) {
            return ['status' => 'processed', 'email' => $result];
        }
        
        // Try to determine why it was skipped
        try {
            $parts = $this->parseEmail($content);
            if (!$parts) {
                return ['status' => 'failed', 'reason' => 'parsing_error'];
            }
            
            $messageId = $parts['message_id'] ?? md5($content . $filename);
            $existing = ProcessedEmail::where('message_id', $messageId)
                ->where('email_account_id', $emailAccount->id)
                ->first();
            
            if ($existing) {
                return ['status' => 'skipped', 'reason' => 'duplicate'];
            }
            
            $fromEmail = $parts['from_email'] ?? '';
            if (!$emailAccount->isSenderAllowed($fromEmail)) {
                return ['status' => 'skipped', 'reason' => 'sender_not_allowed'];
            }
        } catch (\Exception $e) {
            // Ignore errors in stats collection
        }
        
        return ['status' => 'failed', 'reason' => 'unknown'];
    }

    /**
     * Parse email content and store in database
     */
    protected function parseEmailContent(string $content, EmailAccount $emailAccount, string $filename): ?ProcessedEmail
    {
        try {
            // Parse email headers and body
            $parts = $this->parseEmail($content);
            
            if (!$parts) {
                Log::debug('parseEmailContent: parseEmail returned null', [
                    'filename' => $filename,
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 300),
                ]);
                return null;
            }

            // Extract message ID from filename or headers
            $messageId = $parts['message_id'] ?? md5($content . $filename);

            // Check if already stored
            $existing = ProcessedEmail::where('message_id', $messageId)
                ->where('email_account_id', $emailAccount->id)
                ->first();

            if ($existing) {
                Log::debug('Email skipped: already stored', [
                    'message_id' => $messageId,
                    'subject' => $parts['subject'] ?? '',
                    'existing_id' => $existing->id,
                ]);
                return null; // Already stored
            }

            $fromEmail = $parts['from_email'] ?? '';
            $fromName = $parts['from_name'] ?? '';

            // Filter by allowed senders
            if (!$emailAccount->isSenderAllowed($fromEmail)) {
                Log::info('Email skipped: sender not allowed', [
                    'from' => $fromEmail,
                    'subject' => $parts['subject'] ?? '',
                    'email_account' => $emailAccount->email,
                ]);
                return null;
            }

            // Try to extract payment info
            $matchingService = new PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );

            $emailData = [
                'subject' => $parts['subject'] ?? '',
                'from' => $fromEmail,
                'text' => $parts['text_body'] ?? '',
                'html' => $parts['html_body'] ?? '',
                'date' => $parts['date'] ?? now()->toDateTimeString(),
                'email_account_id' => $emailAccount->id,
            ];

            $extractedInfo = null;
            $extractionMethod = null;
            try {
                $extractionResult = $matchingService->extractPaymentInfo($emailData);
                // Handle new format: ['data' => [...], 'method' => '...']
                if (is_array($extractionResult) && isset($extractionResult['data'])) {
                    $extractedInfo = $extractionResult['data'];
                    $extractionMethod = $extractionResult['method'] ?? null;
                } else {
                    // Old format fallback (for backward compatibility)
                    $extractedInfo = $extractionResult;
                    $extractionMethod = 'unknown';
                }
            } catch (\Exception $e) {
                Log::debug('Payment info extraction failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Parse description field to extract account numbers if available
            $descriptionField = $extractedInfo['description_field'] ?? null;
            $parsedFromDescription = $this->parseDescriptionField($descriptionField);
            
            // Use account_number from description field if not already set (description field is PRIMARY source)
            $accountNumber = $extractedInfo['account_number'] ?? $parsedFromDescription['account_number'] ?? null;
            
                    // Update extracted_data to include parsed description field data
                    if ($descriptionField) {
                        $extractedInfo['description_field'] = $descriptionField;
                        $extractedInfo['account_number'] = $parsedFromDescription['account_number'] ?? $extractedInfo['account_number'] ?? null;
                        $extractedInfo['payer_account_number'] = $parsedFromDescription['payer_account_number'] ?? $extractedInfo['payer_account_number'] ?? null;
                        // SKIP amount_from_description - not reliable, use amount field instead
                        $extractedInfo['date_from_description'] = $parsedFromDescription['extracted_date'] ?? null;
                    }
            
            // Store in database
            $processedEmail = ProcessedEmail::create([
                'email_account_id' => $emailAccount->id,
                'source' => 'direct_filesystem',
                'message_id' => $messageId,
                'subject' => $parts['subject'] ?? 'No Subject',
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'text_body' => $parts['text_body'] ?? '',
                'html_body' => $parts['html_body'] ?? '',
                'email_date' => $parts['date'] ?? now(),
                'amount' => $extractedInfo['amount'] ?? null, // Use amount from extraction, not from description field
                'sender_name' => $extractedInfo['sender_name'] ?? null,
                'account_number' => $accountNumber, // Use from description field if available (PRIMARY source)
                'description_field' => $descriptionField, // Store the 43-digit description field
                'extracted_data' => $extractedInfo,
                'extraction_method' => $extractionMethod,
            ]);

            // Automatically dispatch job to process email for payment matching
            // Only dispatch if we extracted payment info (has amount)
            if ($extractedInfo && isset($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                // Add processed_email_id to emailData for logging
                $emailData['processed_email_id'] = $processedEmail->id;
                ProcessEmailPayment::dispatch($emailData);
                Log::info('Dispatched ProcessEmailPayment job for filesystem email', [
                    'processed_email_id' => $processedEmail->id,
                    'subject' => $parts['subject'] ?? '',
                    'amount' => $extractedInfo['amount'],
                    'extraction_method' => $extractionMethod,
                ]);
            }

            return $processedEmail;

        } catch (\Exception $e) {
            Log::error('Error parsing email content', [
                'error' => $e->getMessage(),
                'filename' => $filename,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Parse email content into parts
     */
    protected function parseEmail(string $content): ?array
    {
        if (empty(trim($content))) {
            return null;
        }

        // Try multiple methods to split headers and body
        $headers = '';
        $body = '';
        
        // Method 1: Standard RFC 822 format (double CRLF)
        if (strpos($content, "\r\n\r\n") !== false) {
            list($headers, $body) = explode("\r\n\r\n", $content, 2);
        }
        // Method 2: Unix format (double LF)
        elseif (strpos($content, "\n\n") !== false) {
            list($headers, $body) = explode("\n\n", $content, 2);
        }
        // Method 3: Try to find first blank line (more flexible)
        elseif (preg_match('/^(.+?)(\r?\n\r?\n)(.+)$/s', $content, $matches)) {
            $headers = $matches[1];
            $body = $matches[3];
        }
        // Method 4: Look for first header-like pattern, then find body
        elseif (preg_match('/^([A-Za-z-]+:\s*.+?)(\r?\n\r?\n|\r?\n)(.+)$/s', $content, $matches)) {
            $headers = $matches[1];
            $body = $matches[3];
        }
        // Method 5: If content starts with "From " (mbox format), try to parse it
        elseif (preg_match('/^From\s+.+?\r?\n(.+?)(\r?\n\r?\n|\r?\n)(.+)$/s', $content, $matches)) {
            $headers = $matches[1];
            $body = $matches[3];
        }
        // Method 6: Last resort - assume first 2000 chars are headers if they contain colons
        else {
            // Check if content looks like it has headers (contains "From:", "Subject:", etc.)
            $headerPattern = '/^(From|Subject|Date|To|Message-ID|Content-Type):/mi';
            if (preg_match($headerPattern, substr($content, 0, 2000))) {
                // Try to find where headers end by looking for pattern: header: value followed by blank line
                if (preg_match('/^(.+?)(\r?\n\r?\n|\r?\n)(.+)$/s', $content, $matches)) {
                    $headers = $matches[1];
                    $body = $matches[3];
                } else {
                    // If we can't find separator, assume first 2000 chars are headers
                    $headers = substr($content, 0, 2000);
                    $body = substr($content, 2000);
                }
            } else {
                // No recognizable headers - log for debugging
                Log::debug('Email parsing failed: no recognizable headers', [
                    'content_preview' => substr($content, 0, 500),
                    'content_length' => strlen($content),
                ]);
                return null;
            }
        }
        
        // Validate that we have headers
        if (empty(trim($headers))) {
            Log::debug('Email parsing failed: empty headers', [
                'content_preview' => substr($content, 0, 500),
                'content_length' => strlen($content),
            ]);
            return null;
        }
        
        // Validate that we have body
        if (empty(trim($body))) {
            Log::debug('Email parsing failed: empty body after header split', [
                'headers_length' => strlen($headers),
                'headers_preview' => substr($headers, 0, 500),
                'content_length' => strlen($content),
            ]);
            // Don't return null - some emails might have empty bodies, but we should still try to parse headers
        }

        // Parse headers
        $headerLines = preg_split('/\r?\n/', $headers);
        $parsedHeaders = [];

        foreach ($headerLines as $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                continue;
            }
            
            // Handle continuation lines (start with space or tab)
            if (preg_match('/^\s+/', $line)) {
                // This is a continuation of the previous header
                if (!empty($parsedHeaders) && !empty($lastKey)) {
                    $parsedHeaders[$lastKey] .= ' ' . trim($line);
                }
                continue;
            }
            
            // Match header: value format
            if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                $lastKey = $key; // Store for continuation lines
                
                // Handle multi-line headers (same key appears multiple times)
                if (isset($parsedHeaders[$key])) {
                    $parsedHeaders[$key] .= ' ' . $value;
                } else {
                    $parsedHeaders[$key] = $value;
                }
            }
        }

        // Extract fields
        $subject = $parsedHeaders['subject'] ?? '';
        $date = $parsedHeaders['date'] ?? now()->toDateTimeString();
        $messageId = $parsedHeaders['message-id'] ?? null;

        // Parse From header - try multiple sources
        // Maildir format might have Return-Path or envelope-from, try those if From is missing
        $from = $parsedHeaders['from'] ?? $parsedHeaders['return-path'] ?? $parsedHeaders['envelope-from'] ?? '';
        $fromEmail = '';
        $fromName = '';
        
        // Remove angle brackets and clean up
        $from = trim($from, '<>');
        
        if (preg_match('/<(.+?)>/', $from, $matches)) {
            $fromEmail = strtolower(trim($matches[1]));
            $fromName = trim(str_replace($matches[0], '', $from));
        } elseif (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = strtolower(trim($from));
        } elseif (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/i', $from, $matches)) {
            $fromEmail = strtolower(trim($matches[0]));
        }
        
        // If still no from email, try to extract from other headers
        if (empty($fromEmail)) {
            // Check envelope-from in Received headers
            if (isset($parsedHeaders['received'])) {
                if (preg_match('/envelope-from\s+<([^>]+)>/i', $parsedHeaders['received'], $matches)) {
                    $fromEmail = strtolower(trim($matches[1]));
                }
            }
        }

        // Parse body (handle multipart and quoted-printable encoding)
        $textBody = $body;
        $htmlBody = '';

        // Check Content-Transfer-Encoding for quoted-printable
        $transferEncoding = strtolower($parsedHeaders['content-transfer-encoding'] ?? '');

        // Try to parse Content-Type to determine if multipart
        $contentType = $parsedHeaders['content-type'] ?? '';
        
        if (strpos($contentType, 'multipart') !== false) {
            // Extract boundary from Content-Type header (handle quoted and unquoted)
            $boundary = null;
            if (preg_match('/boundary\s*=\s*["\']?([^"\'\s;]+)["\']?/i', $contentType, $boundaryMatch)) {
                $boundary = trim($boundaryMatch[1], '"\'');
            } elseif (preg_match('/--([A-Za-z0-9\'()+_,-.\/:=?]+)/', $body, $boundaryMatch)) {
                // Fallback: extract from body (first occurrence)
                $boundary = trim($boundaryMatch[1], '"\'');
            }
            
            if ($boundary) {
                // Split body by boundary markers (handle both --boundary and --boundary-- formats)
                // Use preg_split with PREG_SPLIT_DELIM_CAPTURE to keep boundaries for analysis
                $parts = preg_split('/\r?\n?--' . preg_quote($boundary, '/') . '(?:--)?\r?\n?/', $body, -1, PREG_SPLIT_NO_EMPTY);
                
                foreach ($parts as $partIndex => $part) {
                    // Skip preamble (before first boundary)
                    if ($partIndex === 0 && !preg_match('/Content-Type:/i', $part)) {
                        continue;
                    }
                    
                    // Extract part headers and body (split on double newline)
                    $headerBodySplit = preg_split('/\r?\n\r?\n/', $part, 2);
                    $partHeaders = $headerBodySplit[0] ?? '';
                    $partBody = $headerBodySplit[1] ?? $part; // If no double newline, assume entire part is body
                    
                    // Check for Content-Transfer-Encoding in this part
                    $partTransferEncoding = '7bit';
                    if (preg_match('/Content-Transfer-Encoding:\s*([^\r\n]+)/i', $partHeaders, $encMatch)) {
                        $partTransferEncoding = strtolower(trim($encMatch[1]));
                    }
                    
                    // Check Content-Type (can be in header line or separate line)
                    $partContentType = '';
                    if (preg_match('/Content-Type:\s*([^\r\n;]+)/i', $partHeaders, $ctMatch)) {
                        $partContentType = strtolower(trim($ctMatch[1]));
                    }
                    
                    // Determine if this is HTML or plain text part
                    $isHtml = strpos($partContentType, 'text/html') !== false || 
                              strpos($partHeaders, 'text/html') !== false;
                    $isPlain = strpos($partContentType, 'text/plain') !== false || 
                               (empty($partContentType) && strpos($partHeaders, 'text/plain') !== false);
                    
                    // Extract text/plain part
                    if ($isPlain && !$isHtml) {
                        // Get full body (everything after headers)
                        $cleanedBody = $partBody;
                        // Remove any trailing boundary markers (--boundary--)
                        $cleanedBody = preg_replace('/--\r?\n?$/', '', $cleanedBody);
                        $cleanedBody = rtrim($cleanedBody, " \r\n\t");
                        $textBody = $cleanedBody;
                        
                        // Decode quoted-printable if needed
                        if (strpos($partTransferEncoding, 'quoted-printable') !== false) {
                            $textBody = $this->decodeQuotedPrintable($textBody);
                        }
                    }
                    // Extract text/html part (prioritize HTML over plain)
                    elseif ($isHtml) {
                        // Get full HTML body (everything after headers, up to next boundary)
                        $cleanedBody = $partBody;
                        
                        // Remove trailing boundary markers but keep all HTML content
                        // Remove closing boundary (--boundary--) if present at end
                        $cleanedBody = preg_replace('/\r?\n--[^\r\n]*--\s*$/', '', $cleanedBody);
                        // Remove any standalone boundary marker at end
                        $cleanedBody = preg_replace('/--[^\r\n]*\s*$/', '', $cleanedBody);
                        // Trim only trailing whitespace/newlines, preserve content
                        $cleanedBody = rtrim($cleanedBody, " \r\n\t");
                        
                        // Store the FULL HTML body (don't trim further)
                        $htmlBody = $cleanedBody;
                        
                        // Decode quoted-printable if needed
                        if (strpos($partTransferEncoding, 'quoted-printable') !== false) {
                            $htmlBody = $this->decodeQuotedPrintable($htmlBody);
                        }
                        
                        // Also decode HTML entities
                        $htmlBody = html_entity_decode($htmlBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        
                        // Log HTML body length for debugging
                        if (strlen($htmlBody) > 0) {
                            \Illuminate\Support\Facades\Log::debug('Extracted HTML body from multipart', [
                                'html_length' => strlen($htmlBody),
                                'html_preview' => substr($htmlBody, 0, 200),
                            ]);
                        }
                    }
                }
            }
        } elseif (strpos($contentType, 'text/html') !== false) {
            $htmlBody = $body;
            // Decode quoted-printable if needed
            if (strpos($transferEncoding, 'quoted-printable') !== false) {
                $htmlBody = $this->decodeQuotedPrintable($htmlBody);
            }
            // Decode HTML entities
            $htmlBody = html_entity_decode($htmlBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Extract text_body from HTML if not already set
            if (empty(trim($textBody))) {
                $textBody = $this->htmlToText($htmlBody);
            }
        } elseif (strpos($contentType, 'text/plain') !== false) {
            // Decode quoted-printable if needed
            if (strpos($transferEncoding, 'quoted-printable') !== false) {
                $textBody = $this->decodeQuotedPrintable($textBody);
            }
        }
        
        // CRITICAL: If text_body is still empty but html_body exists, extract text from HTML
        // This ensures we always have text_body for extraction (handles multipart HTML-only emails)
        if (empty(trim($textBody)) && !empty(trim($htmlBody))) {
            $textBody = $this->htmlToText($htmlBody);
            \Illuminate\Support\Facades\Log::debug('Extracted text_body from html_body', [
                'text_length' => strlen($textBody),
                'html_length' => strlen($htmlBody),
            ]);
        }

        // Parse date
        try {
            $parsedDate = \Carbon\Carbon::parse($date)->setTimezone(config('app.timezone'));
        } catch (\Exception $e) {
            $parsedDate = now();
        }

        return [
            'subject' => $this->decodeHeader($subject),
            'from_email' => $fromEmail,
            'from_name' => $this->decodeHeader($fromName),
            'date' => $parsedDate,
            'message_id' => trim($messageId, '<>') ?? null,
            'text_body' => $textBody,
            'html_body' => $htmlBody,
        ];
    }

    /**
     * Decode email header (handles encoded words)
     */
    protected function decodeHeader(string $header): string
    {
        // Handle =?charset?encoding?encoded-text?= format
        return mb_decode_mimeheader($header) ?: $header;
    }

    /**
     * Decode quoted-printable encoding (e.g., =20 becomes space, =3D becomes =)
     */
    protected function decodeQuotedPrintable(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode quoted-printable format: =XX where XX is hex
        // =20 is space, =0D=0A is CRLF, =3D is =, etc.
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            $hex = hexdec($matches[1]);
            // Convert hex to character (0-255)
            return chr($hex);
        }, $text);
        
        // Handle soft line breaks (trailing = at end of line)
        // This removes = followed by CRLF or LF
        $text = preg_replace('/=\r?\n/', '', $text);
        
        // Also handle standalone = at end of line (in case CRLF is missing)
        $text = preg_replace('/=\s*\n/', "\n", $text);
        
        return $text;
    }

    /**
     * Convert HTML to plain text while preserving important structure
     * Handles tables, divs, and other HTML elements banks use
     */
    protected function htmlToText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove script and style tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Convert common HTML elements to text with spacing
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/td>/i', ' ', $html);
        $html = preg_replace('/<\/tr>/i', "\n", $html);
        $html = preg_replace('/<\/th>/i', ' ', $html);
        $html = preg_replace('/<\/li>/i', "\n", $html);
        
        // Remove all remaining HTML tags
        $text = strip_tags($html);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        $text = trim($text);
        
        return $text;
    }
}
