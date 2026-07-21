<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Business\PaymentWebsiteAttributionService;
use Illuminate\Console\Command;

class BackfillPaymentWebsites extends Command
{
    protected $signature = 'payments:backfill-websites {--dry-run : Run without making changes}';

    protected $description = 'Backfill missing business_website_id on payments using webhook and website hints';

    public function __construct(
        private PaymentWebsiteAttributionService $attribution,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting website backfill for payments...');

        $payments = Payment::query()
            ->whereNull('business_website_id')
            ->whereNotNull('business_id')
            ->with('business')
            ->orderBy('id')
            ->get();

        $this->info("Found {$payments->count()} payments without business_website_id");

        $updated = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            $website = $this->attribution->resolveWebsite($payment);

            if (! $website) {
                $skipped++;
                $this->warn("✗ Payment {$payment->transaction_id}: Could not identify website");

                continue;
            }

            if (! $dryRun) {
                $payment->update(['business_website_id' => $website->id]);
            }

            $updated++;
            $this->line("✓ Payment {$payment->transaction_id}: Matched to website ID {$website->id}");
        }

        $this->info("\nSummary:");
        $this->info("  Updated: {$updated}");
        $this->info("  Skipped: {$skipped}");

        if ($dryRun) {
            $this->warn("\nThis was a dry run. Use without --dry-run to apply changes.");
        }

        return self::SUCCESS;
    }
}
