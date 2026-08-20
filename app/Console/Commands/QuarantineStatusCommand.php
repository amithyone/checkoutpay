<?php

namespace App\Console\Commands;

use App\Services\Quarantine\QuarantineService;
use Illuminate\Console\Command;

class QuarantineStatusCommand extends Command
{
    protected $signature = 'quarantine:status {--trip : Evaluate and write lock if fingerprint fails}';

    protected $description = 'Show quarantine lock / fingerprint status';

    public function handle(QuarantineService $quarantine): int
    {
        if ($this->option('trip') && $quarantine->isEnabled() && ! $quarantine->isLocked()) {
            $reasons = $quarantine->evaluateNow();
            if ($reasons !== []) {
                $quarantine->trip($reasons);
                $this->error('Quarantine tripped.');
            }
        }

        $status = $quarantine->status();
        $this->line('enabled: '.($status['enabled'] ? 'yes' : 'no'));
        $this->line('active: '.($status['active'] ? 'yes' : 'no'));
        $this->line('locked_file: '.($quarantine->isLocked() ? 'yes' : 'no'));
        if ($status['reasons'] !== []) {
            $this->line('reasons: '.implode(', ', $status['reasons']));
        }
        if (! empty($status['lock']['tripped_at'])) {
            $this->line('tripped_at: '.$status['lock']['tripped_at']);
        }

        return $status['active'] ? self::FAILURE : self::SUCCESS;
    }
}
