<?php

namespace App\Console\Commands;

use App\Models\AccountNumber;
use App\Services\NubanValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ValidateAndUpdateAccountNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account-numbers:validate-and-update-names 
                            {--limit=100 : Maximum number of account numbers to process}
                            {--force : Force update even if account name already exists}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate and update account names using NUBAN API for existing account numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $nubanService = app(NubanValidationService::class);

        // Get account numbers to process
        $query = AccountNumber::query();
        
        if (!$force) {
            // Only process accounts with placeholder or missing account names
            $query->where(function($q) {
                $q->whereNull('account_name')
                  ->orWhere('account_name', 'like', 'Account %')
                  ->orWhere('account_name', 'like', 'Unknown%')
                  ->orWhere('account_name', '=', '')
                  ->orWhere('bank_name', 'like', 'Unknown%');
            });
        }

        $accountNumbers = $query->limit($limit)->get();

        if ($accountNumbers->isEmpty()) {
            $this->info('No account numbers found to process.');
            return 0;
        }

        $this->info("Processing {$accountNumbers->count()} account number(s)...");
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($accountNumbers->count());
        $progressBar->start();

        foreach ($accountNumbers as $accountNumber) {
            try {
                // Skip if account number is invalid format
                if (strlen($accountNumber->account_number) !== 10) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Try to get bank code from bank name if available
                $bankCode = null;
                if ($accountNumber->bank_name && $accountNumber->bank_name !== 'Unknown Bank') {
                    $bankCode = $this->getBankCodeFromName($accountNumber->bank_name);
                }

                // Validate account number using NUBAN API
                $result = $nubanService->validate($accountNumber->account_number, $bankCode);

                if ($result && isset($result['account_name']) && !empty($result['account_name'])) {
                    $updateData = [];
                    $changes = [];

                    // Update account name
                    if ($force || empty($accountNumber->account_name) || 
                        str_starts_with($accountNumber->account_name, 'Account ') ||
                        str_starts_with($accountNumber->account_name, 'Unknown')) {
                        $updateData['account_name'] = $result['account_name'];
                        $changes[] = "name: '{$accountNumber->account_name}' → '{$result['account_name']}'";
                    }

                    // Update bank name if returned and different
                    if (isset($result['bank_name']) && !empty($result['bank_name']) && 
                        ($force || $accountNumber->bank_name === 'Unknown Bank' || empty($accountNumber->bank_name))) {
                        $updateData['bank_name'] = $result['bank_name'];
                        $changes[] = "bank: '{$accountNumber->bank_name}' → '{$result['bank_name']}'";
                    }

                    if (!empty($updateData) && !$dryRun) {
                        $accountNumber->update($updateData);
                        $updated++;
                        Log::info('Account name updated via validation command', [
                            'account_number_id' => $accountNumber->id,
                            'account_number' => $accountNumber->account_number,
                            'changes' => $updateData,
                        ]);
                    } elseif (!empty($updateData)) {
                        $updated++;
                        $this->line("Would update: Account {$accountNumber->account_number}");
                        foreach ($changes as $change) {
                            $this->line("  - {$change}");
                        }
                    } else {
                        $skipped++;
                    }
                } else {
                    $failed++;
                    Log::warning('Failed to validate account number', [
                        'account_number_id' => $accountNumber->id,
                        'account_number' => $accountNumber->account_number,
                    ]);
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Error validating account number', [
                    'account_number_id' => $accountNumber->id,
                    'account_number' => $accountNumber->account_number,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Failed', $failed],
                ['Skipped', $skipped],
                ['Total', $accountNumbers->count()],
            ]
        );

        if ($updated > 0 && !$dryRun) {
            // Invalidate caches
            app(\App\Services\AccountNumberService::class)->invalidateAllCaches();
            $this->info('Cache invalidated.');
        }

        return 0;
    }

    /**
     * Get bank code from bank name using config
     */
    private function getBankCodeFromName(string $bankName): ?string
    {
        $banks = config('banks', []);
        
        foreach ($banks as $bank) {
            if (isset($bank['bank_name']) && strtolower($bank['bank_name']) === strtolower($bankName)) {
                return $bank['code'] ?? null;
            }
        }

        return null;
    }
}
