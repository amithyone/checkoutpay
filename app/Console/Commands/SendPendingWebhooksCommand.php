<?php

namespace App\Console\Commands;

use App\Services\PendingWebhookDispatchService;
use Illuminate\Console\Command;

class SendPendingWebhooksCommand extends Command
{
    protected $signature = 'webhooks:send-pending
                            {--all : Process in batches until the queue is empty or --max is reached}
                            {--batch=100 : Number of payments per batch}
                            {--max=10000 : Maximum payments to process when using --all}
                            {--force : Ignore the 5-minute retry cooldown on webhook_sent_at}';

    protected $description = 'Send payment.approved webhooks for approved payments that are pending, failed, or not yet delivered (sync).';

    public function handle(PendingWebhookDispatchService $dispatcher): int
    {
        $batch = max(1, min(500, (int) $this->option('batch')));
        $max = max(1, min(50000, (int) $this->option('max')));
        $force = (bool) $this->option('force');
        $all = (bool) $this->option('all');

        if ($all) {
            $result = $dispatcher->processUntilExhausted($batch, $max, $force);
            $this->info("Sent webhooks for {$result['sent']} payment(s) in {$result['batches']} batch(es).");
            if ($result['pending_after'] > 0) {
                $this->warn("Still pending (same cooldown/attempt rules): {$result['pending_after']}. Run again with --force if retries are time-blocked.");
            }
            foreach (array_slice($result['errors'], 0, 20) as $err) {
                $this->error($err);
            }
            if (count($result['errors']) > 20) {
                $this->warn('… additional errors omitted (see logs).');
            }

            return self::SUCCESS;
        }

        $payments = $dispatcher->collectPending($batch, $force);
        [$processed, $errors] = $dispatcher->dispatchSyncForPayments($payments);

        $this->info("Processed {$processed} webhook(s) ({$payments->count()} candidate row(s)).");

        foreach ($errors as $err) {
            $this->error($err);
        }

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }
}
