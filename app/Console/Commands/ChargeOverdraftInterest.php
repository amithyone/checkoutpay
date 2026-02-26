<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ChargeOverdraftInterest extends Command
{
    protected $signature = 'overdraft:charge-interest';
    protected $description = 'Charge 5% weekly interest on overdrawn balances (after 7 days)';

    public function handle(): int
    {
        $this->info('Charging overdraft interest where applicable...');

        $businesses = Business::where('balance', '<', 0)
            ->whereNotNull('overdraft_approved_at')
            ->where('overdraft_limit', '>', 0)
            ->get();

        $charged = 0;
        foreach ($businesses as $business) {
            $lastCharged = $business->overdraft_interest_last_charged_at;
            $eligible = false;
            if ($lastCharged) {
                $eligible = now()->diffInDays($lastCharged) >= 7;
            } else {
                // First charge: only after 7 days since overdraft was approved (or since they could have gone negative)
                $eligible = $business->overdraft_approved_at && now()->diffInDays($business->overdraft_approved_at) >= 7;
            }
            if (!$eligible) {
                continue;
            }

            $overdrawn = abs((float) $business->balance);
            $interest = round($overdrawn * 0.05, 2);
            if ($interest < 0.01) {
                continue;
            }

            $business->decrement('balance', $interest);
            $business->update(['overdraft_interest_last_charged_at' => now()]);
            $charged++;
            Log::info('Overdraft interest charged', [
                'business_id' => $business->id,
                'amount' => $interest,
                'new_balance' => $business->fresh()->balance,
            ]);
        }

        $this->info("Charged interest for {$charged} business(es).");
        return 0;
    }
}
