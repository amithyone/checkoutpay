<?php

namespace Database\Seeders;

use App\Models\AccountNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if account numbers already exist
        if (AccountNumber::count() > 0) {
            $this->command->warn('Account numbers already exist. Skipping seeder.');
            return;
        }

        $this->command->info('Seeding account numbers...');

        // Get account numbers from payments if they exist
        $accountNumbersFromPayments = DB::table('payments')
            ->select('account_number', DB::raw('COUNT(*) as payment_count'))
            ->whereNotNull('account_number')
            ->groupBy('account_number')
            ->orderBy('payment_count', 'desc')
            ->get();

        $created = 0;
        $skipped = 0;

        if ($accountNumbersFromPayments->isNotEmpty()) {
            $this->command->info("Found {$accountNumbersFromPayments->count()} account numbers in payments. Creating pool accounts...");
            
            foreach ($accountNumbersFromPayments as $acc) {
                try {
                    AccountNumber::create([
                        'account_number' => $acc->account_number,
                        'account_name' => 'Account ' . $acc->account_number,
                        'bank_name' => 'Unknown Bank', // Admin should update with correct bank name
                        'business_id' => null,
                        'is_pool' => true,
                        'is_invoice_pool' => false,
                        'is_active' => true,
                        'usage_count' => $acc->payment_count,
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    $skipped++;
                    $this->command->warn("Skipped {$acc->account_number}: {$e->getMessage()}");
                }
            }
        } else {
            // Default pool accounts if no payments exist
            $defaultAccounts = [
                [
                    'account_number' => '9008771210',
                    'account_name' => 'Payment Gateway Pool Account 1',
                    'bank_name' => 'GTBank',
                    'is_pool' => true,
                    'is_invoice_pool' => false,
                ],
                [
                    'account_number' => '3003372707',
                    'account_name' => 'Payment Gateway Pool Account 2',
                    'bank_name' => 'GTBank',
                    'is_pool' => true,
                    'is_invoice_pool' => false,
                ],
                [
                    'account_number' => '5212816594',
                    'account_name' => 'Payment Gateway Pool Account 3',
                    'bank_name' => 'GTBank',
                    'is_pool' => true,
                    'is_invoice_pool' => false,
                ],
            ];

            foreach ($defaultAccounts as $account) {
                try {
                    AccountNumber::create(array_merge($account, [
                        'business_id' => null,
                        'is_active' => true,
                        'usage_count' => 0,
                    ]));
                    $created++;
                } catch (\Exception $e) {
                    $skipped++;
                    $this->command->warn("Skipped {$account['account_number']}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("Account numbers seeded successfully!");
        $this->command->info("  Created: {$created}");
        if ($skipped > 0) {
            $this->command->warn("  Skipped: {$skipped}");
        }

        // Invalidate caches
        if (app()->bound(\App\Services\AccountNumberService::class)) {
            app(\App\Services\AccountNumberService::class)->invalidateAllCaches();
            $this->command->info("Cache invalidated.");
        }
    }
}
