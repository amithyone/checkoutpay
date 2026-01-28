<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupEmailData extends Command
{
    protected $signature = 'payments:cleanup-email-data {--dry-run : Show what would be cleaned without actually doing it}';
    protected $description = 'Clean up email_data field in payments table by removing large text/html bodies';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }
        
        // Find payments with large email_data
        $payments = Payment::whereNotNull('email_data')
            ->get();
        
        $this->info("Found {$payments->count()} payments with email_data");
        
        $cleaned = 0;
        $totalBytesSaved = 0;
        
        foreach ($payments as $payment) {
            $emailData = $payment->email_data ?? [];
            
            // Check if it has large text/html fields
            $hasLargeFields = false;
            $originalSize = strlen(json_encode($emailData));
            
            if (isset($emailData['text']) && strlen($emailData['text']) > 500) {
                $hasLargeFields = true;
            }
            if (isset($emailData['html']) && strlen($emailData['html']) > 500) {
                $hasLargeFields = true;
            }
            
            if ($hasLargeFields) {
                $sanitized = Payment::sanitizeEmailData($emailData);
                $newSize = strlen(json_encode($sanitized));
                $bytesSaved = $originalSize - $newSize;
                
                $this->line("Payment ID {$payment->id}: {$originalSize} bytes -> {$newSize} bytes (saved {$bytesSaved} bytes)");
                
                if (!$dryRun) {
                    $payment->update(['email_data' => $sanitized]);
                }
                
                $cleaned++;
                $totalBytesSaved += $bytesSaved;
            }
        }
        
        $this->newLine();
        if ($dryRun) {
            $this->info("Would clean {$cleaned} payments");
            $this->info("Would save approximately " . number_format($totalBytesSaved / 1024, 2) . " KB");
        } else {
            $this->info("Cleaned {$cleaned} payments");
            $this->info("Saved approximately " . number_format($totalBytesSaved / 1024, 2) . " KB");
        }
        
        return 0;
    }
}
