<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessVerification;
use Illuminate\Console\Command;

class CleanBotBusinesses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-bot-businesses {--force : Force deletion without prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes dummy bot businesses (e.g. random LLCs) with zero balance and no KYC.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding bot businesses...');

        $bots = Business::where('name', 'like', '% LLC')
            ->where('balance', '<=', 0)
            ->get()
            ->filter(function($b) {
                return $b->kycListStatus() === 'none'; // Ensure no KYC was ever submitted
            });

        if ($bots->isEmpty()) {
            $this->info('No bot businesses found.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Website'],
            $bots->map(fn($b) => [$b->id, $b->name, $b->email, $b->website])->toArray()
        );

        $this->warn('Found ' . $bots->count() . ' dummy bot businesses.');

        if (!$this->option('force') && !$this->confirm('Do you want to delete these businesses?')) {
            $this->info('Aborted.');
            return;
        }

        $this->info('Deleting...');

        $count = 0;
        foreach ($bots as $b) {
            $b->websites()->delete();
            $b->delete();
            $count++;
        }

        $this->info("Successfully deleted {$count} bot businesses.");
    }
}
