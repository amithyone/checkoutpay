<?php

namespace App\Console\Commands;

use App\Models\BusinessLendingOffer;
use Illuminate\Console\Command;

class CleanupDuplicateLendingOffers extends Command
{
    protected $signature = 'peer-lending:cleanup-duplicate-offers {--dry-run}';

    protected $description = 'Remove duplicate pending lending offers (same lender+amount+rate+term+repayment created within 5 minutes).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $duplicates = BusinessLendingOffer::query()
            ->where('status', BusinessLendingOffer::STATUS_PENDING_ADMIN)
            ->orderBy('lender_business_id')
            ->orderBy('amount')
            ->orderBy('interest_rate_percent')
            ->orderBy('term_days')
            ->orderBy('repayment_type')
            ->orderBy('created_at')
            ->get();

        $seen = [];
        $toDelete = [];

        foreach ($duplicates as $offer) {
            $key = implode('|', [
                $offer->lender_business_id,
                (string) $offer->amount,
                (string) $offer->interest_rate_percent,
                $offer->term_days,
                $offer->repayment_type,
            ]);

            if (isset($seen[$key])) {
                $previous = $seen[$key];
                if ($offer->created_at->diffInMinutes($previous->created_at) <= 5) {
                    $toDelete[] = $offer->id;
                    continue;
                }
            }

            $seen[$key] = $offer;
        }

        $this->info('Duplicate pending offers found: '.count($toDelete));

        if (! empty($toDelete) && ! $dry) {
            BusinessLendingOffer::whereIn('id', $toDelete)->delete();
            $this->info('Deleted '.count($toDelete).' duplicate offer(s).');
        } elseif ($dry) {
            $this->line('Dry run — no records deleted.');
            foreach ($toDelete as $id) {
                $this->line('Would delete offer #'.$id);
            }
        }

        return self::SUCCESS;
    }
}
