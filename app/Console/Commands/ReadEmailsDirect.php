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
    protected $signature = 'payment:read-emails-direct {--email= : Email account to read}';
    protected $description = 'Read emails directly from server mail files (bypasses IMAP)';

    public function handle(): void
    {
        $this->info('Reading emails directly from server filesystem...');

        // Get email account
        $emailAddress = $this->option('email') ?? 'notify@check-outpay.com';
        $emailAccount = EmailAccount::where('email', $emailAddress)->first();

        if (!$emailAccount) {
            $this->error("Email account not found: {$emailAddress}");
            return;
        }

        $this->info("Reading emails for: {$emailAccount->email}");

        // Try to find mail directory
        $mailPaths = $this->findMailDirectory($emailAccount->email);

        if (empty($mailPaths)) {
            $this->error('Could not find mail directory. Trying common paths...');
            $this->info('Common cPanel mail paths:');
            $this->info('  /home/username/mail/domain.com/email@domain.com/');
            $this->info('  /var/spool/mail/username');
            $this->info('  /home/username/mail/domain.com/email@domain.com/Maildir/');
            return;
        }

        $this->info('Found mail directory(s):');
        foreach ($mailPaths as $path) {
            $this->info("  - {$path}");
        }

        // Read emails from each path
        $totalRead = 0;
        foreach ($mailPaths as $mailPath) {
            $count = $this->readEmailsFromPath($mailPath, $emailAccount);
            $totalRead += $count;
        }

        $this->info("✅ Total emails read: {$totalRead}");
    }

    /**
     * Find mail directory for email account
     */
    protected function findMailDirectory(string $email): array
    {
        $paths = [];

        // Extract domain and local part
        list($localPart, $domain) = explode('@', $email);

        // Common cPanel mail paths
        $commonPaths = [
            // Maildir format (most common on cPanel)
            "/home/{$this->getUsername()}/mail/{$domain}/{$localPart}/Maildir/cur/",
            "/home/{$this->getUsername()}/mail/{$domain}/{$localPart}/Maildir/new/",
            "/home/{$this->getUsername()}/mail/{$domain}/{$localPart}/cur/",
            "/home/{$this->getUsername()}/mail/{$domain}/{$localPart}/new/",
            
            // Alternative paths
            "/home/{$this->getUsername()}/mail/{$domain}/{$localPart}/",
            "/var/spool/mail/{$localPart}",
            "/var/mail/{$localPart}",
        ];

        // Also try with username from env or server
        $username = $this->getUsername();
        if ($username) {
            $commonPaths[] = "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/";
            $commonPaths[] = "/home/{$username}/mail/{$domain}/{$localPart}/";
        }

        foreach ($commonPaths as $path) {
            if (is_dir($path)) {
                $this->info("✅ Found mail directory: {$path}");
                $paths[] = $path;
            } elseif (is_file($path)) {
                // mbox format
                $this->info("✅ Found mail file: {$path}");
                $paths[] = $path;
            }
        }

        return $paths;
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
    protected function readEmailsFromPath(string $path, EmailAccount $emailAccount): int
    {
        if (!is_dir($path) && !is_file($path)) {
            return 0;
        }

        $this->info("Reading from: {$path}");

        // Check if it's Maildir format (has cur/ and new/ subdirectories)
        if (is_dir($path . '/cur') || is_dir($path . '/new')) {
            return $this->readMaildirFormat($path, $emailAccount);
        }

        // Check if it's mbox format (single file)
        if (is_file($path)) {
            return $this->readMboxFormat($path, $emailAccount);
        }

        // Try reading files directly from path
        if (is_dir($path)) {
            return $this->readDirectory($path, $emailAccount);
        }

        return 0;
    }

    /**
     * Read Maildir format emails
     */
    protected function readMaildirFormat(string $basePath, EmailAccount $emailAccount): int
    {
        $count = 0;

        // Read from 'new' (unread) and 'cur' (read) directories
        $dirs = ['new', 'cur'];
        
        foreach ($dirs as $dir) {
            $dirPath = is_dir($basePath . '/' . $dir) ? $basePath . '/' . $dir : $basePath;
            
            if (!is_dir($dirPath)) {
                continue;
            }

            $files = scandir($dirPath);
            if (!$files) {
                continue;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $dirPath . '/' . $file;
                
                if (!is_file($filePath)) {
                    continue;
                }

                try {
                    $emailContent = file_get_contents($filePath);
                    if (!$emailContent) {
                        continue;
                    }

                    // Parse email content
                    $parsed = $this->parseEmailContent($emailContent, $emailAccount, $file);
                    
                    if ($parsed) {
                        $count++;
                        $this->info("  ✅ Read email: {$parsed['subject']}");
                    }
                } catch (\Exception $e) {
                    Log::error('Error reading mail file', [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("  ⚠️  Error reading {$file}: {$e->getMessage()}");
                }
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
     * Parse email content and store in database
     */
    protected function parseEmailContent(string $content, EmailAccount $emailAccount, string $filename): ?ProcessedEmail
    {
        try {
            // Parse email headers and body
            $parts = $this->parseEmail($content);
            
            if (!$parts) {
                return null;
            }

            // Extract message ID from filename or headers
            $messageId = $parts['message_id'] ?? md5($content . $filename);

            // Check if already stored
            $existing = ProcessedEmail::where('message_id', $messageId)
                ->where('email_account_id', $emailAccount->id)
                ->first();

            if ($existing) {
                return null; // Already stored
            }

            $fromEmail = $parts['from_email'] ?? '';
            $fromName = $parts['from_name'] ?? '';

            // Filter by allowed senders
            if (!$emailAccount->isSenderAllowed($fromEmail)) {
                Log::debug('Email skipped: sender not allowed', [
                    'from' => $fromEmail,
                    'subject' => $parts['subject'] ?? '',
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
            try {
                $extractedInfo = $matchingService->extractPaymentInfo($emailData);
            } catch (\Exception $e) {
                Log::debug('Payment info extraction failed', [
                    'error' => $e->getMessage(),
                ]);
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
                'amount' => $extractedInfo['amount'] ?? null,
                'sender_name' => $extractedInfo['sender_name'] ?? null,
                'account_number' => $extractedInfo['account_number'] ?? null,
                'extracted_data' => $extractedInfo,
            ]);

            // Automatically dispatch job to process email for payment matching
            // Only dispatch if we extracted payment info (has amount)
            if ($extractedInfo && isset($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                ProcessEmailPayment::dispatch($emailData);
                Log::info('Dispatched ProcessEmailPayment job for filesystem email', [
                    'processed_email_id' => $processedEmail->id,
                    'subject' => $parts['subject'] ?? '',
                    'amount' => $extractedInfo['amount'],
                ]);
            }

            return $processedEmail;

        } catch (\Exception $e) {
            Log::error('Error parsing email content', [
                'error' => $e->getMessage(),
                'filename' => $filename,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Parse email content into parts
     */
    protected function parseEmail(string $content): ?array
    {
        // Split headers and body
        if (strpos($content, "\r\n\r\n") !== false) {
            list($headers, $body) = explode("\r\n\r\n", $content, 2);
        } elseif (strpos($content, "\n\n") !== false) {
            list($headers, $body) = explode("\n\n", $content, 2);
        } else {
            return null;
        }

        // Parse headers
        $headerLines = preg_split('/\r?\n/', $headers);
        $parsedHeaders = [];

        foreach ($headerLines as $line) {
            if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                
                // Handle multi-line headers
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

        // Parse From header
        $from = $parsedHeaders['from'] ?? '';
        $fromEmail = '';
        $fromName = '';
        
        if (preg_match('/<(.+?)>/', $from, $matches)) {
            $fromEmail = $matches[1];
            $fromName = trim(str_replace($matches[0], '', $from));
        } elseif (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $from;
        } elseif (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $from, $matches)) {
            $fromEmail = $matches[0];
        }

        // Parse body (handle multipart)
        $textBody = $body;
        $htmlBody = '';

        // Try to parse Content-Type to determine if multipart
        $contentType = $parsedHeaders['content-type'] ?? '';
        
        if (strpos($contentType, 'multipart') !== false) {
            // Simple multipart parsing (basic)
            if (preg_match('/--([^\r\n]+)/', $contentType . "\n" . $body, $boundaryMatch)) {
                $boundary = $boundaryMatch[1];
                $parts = explode('--' . $boundary, $body);
                
                foreach ($parts as $part) {
                    if (strpos($part, 'text/plain') !== false) {
                        if (preg_match('/\r?\n\r?\n(.*)$/s', $part, $textMatch)) {
                            $textBody = trim($textMatch[1]);
                        }
                    } elseif (strpos($part, 'text/html') !== false) {
                        if (preg_match('/\r?\n\r?\n(.*)$/s', $part, $htmlMatch)) {
                            $htmlBody = trim($htmlMatch[1]);
                        }
                    }
                }
            }
        } elseif (strpos($contentType, 'text/html') !== false) {
            $htmlBody = $body;
            $textBody = strip_tags($body);
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
}
