<?php

namespace App\Console\Commands;

use App\Services\Quarantine\QuarantineService;
use Illuminate\Console\Command;

class QuarantineBaselineCommand extends Command
{
    protected $signature = 'quarantine:baseline';

    protected $description = 'Write quarantine baseline JSON from current DB counts and suggest floor env values';

    public function handle(QuarantineService $quarantine): int
    {
        try {
            $baseline = $quarantine->writeBaseline();
        } catch (\Throwable $e) {
            $this->error('Failed to write baseline: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Wrote '.$quarantine->baselinePath());
        $this->table(
            ['Metric', 'Value'],
            [
                ['db_host', $baseline['db_host']],
                ['db_database', $baseline['db_database']],
                ['payments', (string) $baseline['counts']['payments']],
                ['businesses', (string) $baseline['counts']['businesses']],
                ['admins', (string) $baseline['counts']['admins']],
            ]
        );
        $this->warn('Suggested .error values:');
        foreach ($baseline['suggested_floors'] as $key => $value) {
            $this->line("{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
