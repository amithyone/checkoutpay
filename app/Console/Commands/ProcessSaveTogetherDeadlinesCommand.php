<?php

namespace App\Console\Commands;

use App\Services\Consumer\SaveTogetherService;
use Illuminate\Console\Command;

class ProcessSaveTogetherDeadlinesCommand extends Command
{
    protected $signature = 'save-together:process-deadlines';

    protected $description = 'Unlock Save Together pots whose deadline has passed';

    public function handle(SaveTogetherService $saveTogether): int
    {
        $count = $saveTogether->processDeadlines();
        $this->info("Unlocked {$count} Save Together pot(s).");

        return self::SUCCESS;
    }
}
