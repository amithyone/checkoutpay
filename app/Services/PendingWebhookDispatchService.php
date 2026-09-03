<?php

namespace App\Services;

use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Support\Collection;

class PendingWebhookDispatchService
{
    /**
     * Approved payments whose merchant webhook is pending, null, or failed.
     *
     * @param  bool  $ignoreRetryCooldown  If true, include rows where webhook_sent_at is recent (for immediate retries).
     */
    public function collectPending(int $limit, bool $ignoreRetryCooldown = false): Collection
    {
        $query = Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNull('webhook_status')
                    ->orWhere('webhook_status', 'pending')
                    ->orWhere('webhook_status', 'failed');
            });

        if (! $ignoreRetryCooldown) {
            $query->where(function ($q) {
                $q->whereNull('webhook_sent_at')
                    ->orWhere('webhook_sent_at', '<', now()->subMinutes(5));
            });
        }

        $query->where(function ($q) {
            $q->whereNull('webhook_attempts')
                ->orWhere('webhook_attempts', '<', 5);
        });

        return $query->orderBy('matched_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{0: int, 1: array<int, string>}
     */
    public function dispatchSyncForPayments(Collection $payments): array
    {
        $processed = 0;
        $errors = [];

        foreach ($payments as $payment) {
            try {
                SendWebhookNotification::dispatchSync($payment);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "Payment {$payment->id}: {$e->getMessage()}";
            }
        }

        return [$processed, $errors];
    }

    /**
     * @return array{sent: int, errors: array<int, string>, batches: int, pending_after: int}
     */
    public function processUntilExhausted(int $batchSize, int $maxTotal, bool $ignoreRetryCooldown): array
    {
        $totalSent = 0;
        $allErrors = [];
        $batches = 0;
        $maxBatches = 500;

        while ($totalSent < $maxTotal && $batches < $maxBatches) {
            $remainingBudget = $maxTotal - $totalSent;
            $take = min($batchSize, $remainingBudget);
            $payments = $this->collectPending($take, $ignoreRetryCooldown);

            if ($payments->isEmpty()) {
                break;
            }

            [$processed, $errors] = $this->dispatchSyncForPayments($payments);
            $totalSent += $processed;
            $allErrors = array_merge($allErrors, $errors);
            $batches++;

            if ($payments->count() < $take) {
                break;
            }
        }

        $pendingAfter = $this->countStillPending($ignoreRetryCooldown);

        return [
            'sent' => $totalSent,
            'errors' => $allErrors,
            'batches' => $batches,
            'pending_after' => $pendingAfter,
        ];
    }

    public function countStillPending(bool $ignoreRetryCooldown): int
    {
        $query = Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNull('webhook_status')
                    ->orWhere('webhook_status', 'pending')
                    ->orWhere('webhook_status', 'failed');
            });

        if (! $ignoreRetryCooldown) {
            $query->where(function ($q) {
                $q->whereNull('webhook_sent_at')
                    ->orWhere('webhook_sent_at', '<', now()->subMinutes(5));
            });
        }

        $query->where(function ($q) {
            $q->whereNull('webhook_attempts')
                ->orWhere('webhook_attempts', '<', 5);
        });

        return $query->count();
    }

    /**
     * Approved payments whose merchant webhook failed in the last N hours.
     *
     * @return Collection<int, Payment>
     */
    public function collectFailedInHours(int $hours, int $limit = 1000): Collection
    {
        return $this->failedInHoursQuery($hours)
            ->orderByDesc('webhook_sent_at')
            ->orderByDesc('matched_at')
            ->limit($limit)
            ->get();
    }

    public function countFailedInHours(int $hours): int
    {
        return $this->failedInHoursQuery($hours)->count();
    }

    /**
     * @param  array<int, int>  $windows
     * @return array<int, int>
     */
    public function countFailedByHours(array $windows = [6, 12, 24]): array
    {
        $counts = [];
        foreach ($windows as $hours) {
            $counts[(int) $hours] = $this->countFailedInHours((int) $hours);
        }

        return $counts;
    }

    protected function failedInHoursQuery(int $hours)
    {
        $since = now()->subHours($hours);

        return Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->where('webhook_status', 'failed')
            ->where(function ($q) use ($since) {
                $q->where('webhook_sent_at', '>=', $since)
                    ->orWhere('matched_at', '>=', $since);
            });
    }
}
