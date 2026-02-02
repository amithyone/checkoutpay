<?php

namespace App\Console\Commands;

use App\Models\AccountNumber;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreAccountNumbersFromPayments extends Command
{
    protected $signature = 'account-numbers:restore-from-payments {--dry-run : Show what would be created without actually creating}';
    protected $description = 'Restore account numbers from payments table and add them to pool';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Extracting account numbers from payments...');
        
        // Get distinct account numbers from payments
        $accountNumbers = DB::table('payments')
            ->select('account_number', DB::raw('COUNT(*) as payment_count'))
            ->whereNotNull('account_number')
            ->groupBy('account_number')
            ->orderBy('payment_count', 'desc')
            ->get();
        
        if ($accountNumbers->isEmpty()) {
            $this->warn('No account numbers found in payments table.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$accountNumbers->count()} unique account numbers in payments.");
        
        $created = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($accountNumbers as $acc) {
            $accountNumber = $acc->account_number;
            
            // Check if already exists
            if (AccountNumber::where('account_number', $accountNumber)->exists()) {
                $skipped++;
                continue;
            }
            
            if ($dryRun) {
                $this->line("Would create: {$accountNumber} (used in {$acc->payment_count} payments)");
                $created++;
            } else {
                try {
                    // Create account number in pool
                    // We'll use placeholder values for account_name and bank_name
                    // Admin can update these later with correct details
                    AccountNumber::create([
                        'account_number' => $accountNumber,
                        'account_name' => 'Account ' . $accountNumber, // Placeholder - admin should update
                        'bank_name' => 'Unknown Bank', // Placeholder - admin should update
                        'business_id' => null,
                        'is_pool' => true,
                        'is_invoice_pool' => false,
                        'is_active' => true,
                        'usage_count' => $acc->payment_count,
                    ]);
                    
                    $created++;
                    $this->info("✓ Created: {$accountNumber}");
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("✗ Failed to create {$accountNumber}: {$e->getMessage()}");
                    Log::error('Failed to restore account number from payments', [
                        'account_number' => $accountNumber,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        $this->newLine();
        $this->info('Summary:');
        $this->info("  Created: {$created}");
        $this->info("  Skipped: {$skipped}");
        $this->info("  Errors: {$errors}");
        
        if ($dryRun) {
            $this->warn("\nThis was a dry run. Use without --dry-run to create account numbers.");
        } else {
            // Invalidate caches
            app(\App\Services\AccountNumberService::class)->invalidateAllCaches();
            $this->info("\nCache invalidated. Account numbers are now available for assignment.");
        }
        
        return Command::SUCCESS;
    }
}
