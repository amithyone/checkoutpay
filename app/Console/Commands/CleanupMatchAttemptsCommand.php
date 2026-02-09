<?php

namespace App\Console\Commands;

use App\Models\MatchAttempt;
use Illuminate\Console\Command;

class CleanupMatchAttemptsCommand extends Command
{
    protected $signature = 'match-attempts:cleanup
                            {--days= : Retention period in days (overrides config)}
                            {--dry-run : Show how many would be deleted without deleting}';

    protected $description = 'Delete match attempt logs older than the retention period to reduce database size';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('services.match_attempts.retention_days', 30));

        if ($days < 1) {
            $this->error('Retention days must be at least 1.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = MatchAttempt::where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No match attempts older than {$days} days. Nothing to delete.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[Dry run] Would delete {$count} match attempt(s) older than {$days} days (before {$cutoff->toDateTimeString()}).");
            return self::SUCCESS;
        }

        $this->info("Deleting {$count} match attempt(s) older than {$days} days...");

        $deleted = 0;
        $query->chunkById(1000, function ($attempts) use (&$deleted) {
            $ids = $attempts->pluck('id')->toArray();
            $deleted += MatchAttempt::whereIn('id', $ids)->delete();
        });

        $this->info("Deleted {$deleted} match attempt(s).");
        return self::SUCCESS;
    }
}
