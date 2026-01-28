<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhookNotification;
use App\Models\BusinessWebsite;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResendFaddedNetWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:resend-fadded-net {--all : Resend for all approved payments, not just pending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resend webhooks to fadded.net for all approved payments associated with it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find fadded.net website
        $faddedNetWebsite = BusinessWebsite::where('website_url', 'like', '%fadded.net%')
            ->where('is_approved', true)
            ->whereNotNull('webhook_url')
            ->first();

        if (!$faddedNetWebsite) {
            $this->error('Fadded.net website not found or not approved with webhook URL');
            return 1;
        }

        $this->info("Found fadded.net website:");
        $this->info("  ID: {$faddedNetWebsite->id}");
        $this->info("  URL: {$faddedNetWebsite->website_url}");
        $this->info("  Webhook: {$faddedNetWebsite->webhook_url}");
        $this->info("  Business ID: {$faddedNetWebsite->business_id}");

        // Get all approved payments associated with fadded.net
        $query = Payment::where('business_id', $faddedNetWebsite->business_id)
            ->where('status', Payment::STATUS_APPROVED);

        if (!$this->option('all')) {
            // Only resend for pending webhooks
            $query->where(function ($q) {
                $q->whereNull('webhook_status')
                    ->orWhere('webhook_status', 'pending');
            });
        }

        $payments = $query->get();

        $this->info("\nFound {$payments->count()} approved payment(s) to process");

        if ($payments->isEmpty()) {
            $this->info('No payments found to process');
            return 0;
        }

        $bar = $this->output->createProgressBar($payments->count());
        $bar->start();

        $queued = 0;
        $errors = 0;

        foreach ($payments as $payment) {
            try {
                // Reload payment with relationships
                $payment->load(['business.websites', 'website']);

                // Dispatch webhook job
                SendWebhookNotification::dispatch($payment);
                $queued++;

                Log::info('Queued webhook resend for fadded.net payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'website_id' => $faddedNetWebsite->id,
                ]);
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to queue webhook resend for fadded.net payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Queued {$queued} webhook(s) for resending");
        if ($errors > 0) {
            $this->warn("⚠️  {$errors} error(s) occurred");
        }

        $this->info("\nNote: Webhooks will be processed by the queue worker.");
        $this->info("Make sure queue worker is running: php artisan queue:work");

        return 0;
    }
}
