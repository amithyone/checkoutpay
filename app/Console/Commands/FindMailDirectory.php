<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FindMailDirectory extends Command
{
    protected $signature = 'payment:find-mail-directory {email? : Email address to find directory for}';
    protected $description = 'Find mail directory paths for email accounts (diagnostic tool)';

    public function handle(): void
    {
        $email = $this->argument('email') ?? 'notify@check-outpay.com';
        
        $this->info("Searching for mail directory for: {$email}");
        $this->newLine();

        list($localPart, $domain) = explode('@', $email);

        // Get username
        $username = $this->getUsername();
        $this->info("Detected username: " . ($username ?: 'UNKNOWN'));
        $this->newLine();

        // List all possible paths
        $possiblePaths = [
            // cPanel standard paths
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}/Maildir/",
            "/home/{$username}/mail/{$domain}/{$localPart}/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}/new/",
            "/home/{$username}/mail/{$domain}/{$localPart}/",
            
            // Alternative domain formats
            "/home/{$username}/mail/{$domain}/{$localPart}-{$domain}/Maildir/cur/",
            "/home/{$username}/mail/{$domain}/{$localPart}-{$domain}/Maildir/new/",
            
            // Root mail paths
            "/var/spool/mail/{$localPart}",
            "/var/mail/{$localPart}",
            "/var/spool/mail/{$username}",
            
            // Check parent mail directory
            "/home/{$username}/mail/",
        ];

        $found = false;
        
        $this->info("Checking possible paths:");
        foreach ($possiblePaths as $path) {
            $realPath = $path;
            if (is_dir($realPath)) {
                $this->info("  ✅ FOUND DIRECTORY: {$realPath}");
                $this->listDirectoryContents($realPath, 1);
                $found = true;
            } elseif (is_file($realPath)) {
                $this->info("  ✅ FOUND FILE: {$realPath}");
                $size = filesize($realPath);
                $this->info("      Size: " . $this->formatBytes($size));
                $found = true;
            } else {
                $this->line("  ❌ Not found: {$realPath}");
            }
        }

        if (!$found) {
            $this->warn("No mail directory found! Let's explore manually...");
            $this->newLine();
            
            // Try to explore parent directories
            $explorePaths = [
                "/home/{$username}/mail/",
                "/home/{$username}/",
            ];
            
            foreach ($explorePaths as $explorePath) {
                if (is_dir($explorePath)) {
                    $this->info("Exploring: {$explorePath}");
                    $this->listDirectoryContents($explorePath, 2);
                }
            }
        }

        $this->newLine();
        $this->info("To manually check, run on server:");
        $this->line("  ls -la /home/{$username}/mail/");
        $this->line("  find /home/{$username}/mail -name '*{$localPart}*' -type d");
        $this->line("  find /home/{$username} -name '*mail*' -type d | head -20");
    }

    protected function listDirectoryContents(string $path, int $maxDepth = 1, int $currentDepth = 0): void
    {
        if ($currentDepth >= $maxDepth) {
            return;
        }

        if (!is_readable($path)) {
            $this->warn("      ⚠️  Permission denied: {$path}");
            return;
        }

        try {
            $items = scandir($path);
            if (!$items) {
                return;
            }

            $dirs = [];
            $files = [];
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $path . '/' . $item;
                if (is_dir($itemPath)) {
                    $dirs[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            if (!empty($dirs)) {
                $this->line("      📁 Directories: " . implode(', ', array_slice($dirs, 0, 10)));
                if (count($dirs) > 10) {
                    $this->line("         ... and " . (count($dirs) - 10) . " more");
                }
            }

            if (!empty($files)) {
                $this->line("      📄 Files: " . count($files));
                if ($currentDepth < $maxDepth - 1 && !empty($dirs)) {
                    // Explore subdirectories
                    foreach (array_slice($dirs, 0, 5) as $dir) {
                        $subPath = $path . '/' . $dir;
                        if (is_dir($subPath)) {
                            $this->listDirectoryContents($subPath, $maxDepth, $currentDepth + 1);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("      ⚠️  Error reading directory: {$e->getMessage()}");
        }
    }

    protected function getUsername(): ?string
    {
        $username = getenv('USER') ?: getenv('USERNAME');
        
        if (!$username) {
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

        if (!$username) {
            $username = 'checzspw'; // Fallback based on your setup
        }

        return $username;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
