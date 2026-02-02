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
    protected $description = 'Fix payments that have null account_numbers and/or null websites by assigning them';

    /**
     * Execute the console command.
     */
    public function handle(AccountNumberService $accountNumberService): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Finding payments with null account_numbers or null websites...");
        
        $payments = Payment::where(function($query) {
                $query->whereNull('account_number')
                      ->orWhereNull('business_website_id');
            })
            ->where('status', Payment::STATUS_PENDING)
            ->limit($limit)
            ->get();
        
        if ($payments->isEmpty()) {
            $this->info("No payments found with null account_numbers or null websites.");
            return Command::SUCCESS;
        }
        
        $this->info("Found {$payments->count()} payment(s) with null account_numbers or null websites.");
        
        $fixedAccount = 0;
        $fixedWebsite = 0;
        $failed = 0;
        
        foreach ($payments as $payment) {
            try {
                $business = $payment->business;
                
                if (!$business) {
                    $this->warn("Payment {$payment->id} (TXN: {$payment->transaction_id}) has no business. Skipping.");
                    $failed++;
                    continue;
                }
                
                $updateData = [];
                $fixes = [];
                
                // Fix account number if null
                if (!$payment->account_number) {
                    $accountNumber = $accountNumberService->assignAccountNumber($business);
                    
                    if ($accountNumber) {
                        $updateData['account_number'] = $accountNumber->account_number;
                        $fixes[] = "account: {$accountNumber->account_number}";
                        $fixedAccount++;
                    } else {
                        $this->error("✗ Failed to assign account number to payment {$payment->id} (TXN: {$payment->transaction_id}) - No available accounts");
                        Log::error('Failed to fix null account_number for payment - no available accounts', [
                            'payment_id' => $payment->id,
                            'transaction_id' => $payment->transaction_id,
                            'business_id' => $business->id,
                        ]);
                    }
                }
                
                // Fix website if null
                if (!$payment->business_website_id) {
                    $website = $this->identifyWebsite($payment, $business);
                    
                    if ($website) {
                        $updateData['business_website_id'] = $website->id;
                        $fixes[] = "website: {$website->website_url}";
                        $fixedWebsite++;
                    }
                }
                
                // Update payment if we have fixes
                if (!empty($updateData)) {
                    $payment->update($updateData);
                    $fixesList = implode(', ', $fixes);
                    $this->info("✓ Fixed payment {$payment->id} (TXN: {$payment->transaction_id}) - {$fixesList}");
                    
                    Log::info('Fixed null account_number/website for payment', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'updates' => $updateData,
                        'business_id' => $business->id,
                    ]);
                } else {
                    if (!$payment->account_number) {
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("✗ Error fixing payment {$payment->id} (TXN: {$payment->transaction_id}): {$e->getMessage()}");
                $failed++;
                
                Log::error('Error fixing null account_number/website for payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("  Fixed account numbers: {$fixedAccount}");
        $this->info("  Fixed websites: {$fixedWebsite}");
        $this->info("  Failed: {$failed}");
        $this->info("  Total processed: {$payments->count()}");
        
        return Command::SUCCESS;
    }
    
    /**
     * Identify website from payment's webhook_url or email_data
     */
    protected function identifyWebsite(Payment $payment, $business)
    {
        // Try to get website from webhook_url
        if ($payment->webhook_url) {
            $webhookHost = parse_url($payment->webhook_url, PHP_URL_HOST);
            if ($webhookHost) {
                $website = $business->websites()
                    ->where(function($q) use ($webhookHost) {
                        $q->where('website_url', 'like', '%' . $webhookHost . '%')
                          ->orWhere('webhook_url', 'like', '%' . $webhookHost . '%');
                    })
                    ->where('is_approved', true)
                    ->first();
                
                if ($website) {
                    return $website;
                }
            }
        }
        
        // Try to get website from email_data (return_url or website_url)
        $emailData = $payment->email_data ?? [];
        $url = $emailData['return_url'] ?? $emailData['website_url'] ?? null;
        
        if ($url) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            if ($urlHost) {
                $website = $business->websites()
                    ->where(function($q) use ($urlHost) {
                        $q->where('website_url', 'like', '%' . $urlHost . '%')
                          ->orWhere('webhook_url', 'like', '%' . $urlHost . '%');
                    })
                    ->where('is_approved', true)
                    ->first();
                
                if ($website) {
                    return $website;
                }
            }
        }
        
        // If business has only one approved website, use that
        $approvedWebsites = $business->websites()->where('is_approved', true)->get();
        if ($approvedWebsites->count() === 1) {
            return $approvedWebsites->first();
        }
        
        return null;
    }
}
