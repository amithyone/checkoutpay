<?php

namespace App\Console\Commands;

use App\Services\Quarantine\QuarantineService;
use Illuminate\Console\Command;

class QuarantineClearCommand extends Command
{
    protected $signature = 'quarantine:clear {--code= : Unlock code from QUARANTINE_UNLOCK_CODE} {--force-file : Delete lock file without code (SSH emergency only)}';

    protected $description = 'Clear quarantine lock after fixing .error';

    public function handle(QuarantineService $quarantine): int
    {
        if ($this->option('force-file')) {
            if (! $this->confirm('Delete quarantine.lock without unlock code?', false)) {
                return self::FAILURE;
            }
            $quarantine->clearLock();
            $this->warn('Lock file removed. Fix fingerprint before enabling traffic.');

            return self::SUCCESS;
        }

        $code = (string) ($this->option('code') ?: $this->secret('Unlock code'));
        if ($code === '' || ! $quarantine->clearWithCode($code)) {
            $this->error('Invalid unlock code.');

            return self::FAILURE;
        }

        if ($quarantine->isEnabled()) {
            $reasons = $quarantine->evaluateNow();
            if ($reasons !== []) {
                $quarantine->trip($reasons);
                $this->error('Code accepted but fingerprint still failing — re-armed. Reasons: '.implode(', ', $reasons));

                return self::FAILURE;
            }
        }

        $this->info('Quarantine cleared.');

        return self::SUCCESS;
    }
}
