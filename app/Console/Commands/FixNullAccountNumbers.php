<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\AccountNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixNullAccountNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:fix-null-account-numbers {--limit=100 : Maximum number of payments to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix payments that have null account_numbers by assigning them from the pool';

    /**
     * Execute the console command.
     */
    public function handle(AccountNumberService $accountNumberService): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Finding payments with null account_numbers...");
        
        $payments = Payment::whereNull('account_number')
            ->where('status', Payment::STATUS_PENDING)
            ->limit($limit)
            ->get();
        
        if ($payments->isEmpty()) {
            $this->info("No payments found with null account_numbers.");
            return Command::SUCCESS;
        }
        
        $this->info("Found {$payments->count()} payment(s) with null account_numbers.");
        
        $fixed = 0;
        $failed = 0;
        
        foreach ($payments as $payment) {
            try {
                $business = $payment->business;
                
                if (!$business) {
                    $this->warn("Payment {$payment->id} (TXN: {$payment->transaction_id}) has no business. Skipping.");
                    $failed++;
                    continue;
                }
                
                // Try to assign account number
                $accountNumber = $accountNumberService->assignAccountNumber($business);
                
                if ($accountNumber) {
                    $payment->update(['account_number' => $accountNumber->account_number]);
                    $this->info("✓ Fixed payment {$payment->id} (TXN: {$payment->transaction_id}) - Assigned account: {$accountNumber->account_number}");
                    $fixed++;
                    
                    Log::info('Fixed null account_number for payment', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'account_number' => $accountNumber->account_number,
                        'business_id' => $business->id,
                    ]);
                } else {
                    $this->error("✗ Failed to assign account number to payment {$payment->id} (TXN: {$payment->transaction_id}) - No available accounts");
                    $failed++;
                    
                    Log::error('Failed to fix null account_number for payment - no available accounts', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'business_id' => $business->id,
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("✗ Error fixing payment {$payment->id} (TXN: {$payment->transaction_id}): {$e->getMessage()}");
                $failed++;
                
                Log::error('Error fixing null account_number for payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("  Fixed: {$fixed}");
        $this->info("  Failed: {$failed}");
        $this->info("  Total: {$payments->count()}");
        
        return Command::SUCCESS;
    }
}
