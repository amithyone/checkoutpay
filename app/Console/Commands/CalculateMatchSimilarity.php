<?php

namespace App\Console\Commands;

use App\Models\MatchAttempt;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;
use ReflectionClass;

class CalculateMatchSimilarity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'match:calculate-similarity {--force : Recalculate even if score already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate match similarity scores for all match attempts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Calculating Match Similarity Scores ===');
        $this->newLine();

        $matchingService = new PaymentMatchingService();
        $force = $this->option('force');

        // Get all match attempts
        $query = MatchAttempt::query();
        
        if (!$force) {
            // Only calculate for attempts without scores
            $query->whereNull('name_similarity_percent');
        }
        
        $attempts = $query->get();
        $total = $attempts->count();

        if ($total === 0) {
            $this->info('No match attempts found to process.');
            return 0;
        }

        $this->info("Total match attempts: {$total}");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        // Use reflection to access protected method
        $reflection = new ReflectionClass($matchingService);
        $method = $reflection->getMethod('matchNames');
        $method->setAccessible(true);

        foreach ($attempts as $attempt) {
            try {
                // Skip if both names are missing
                if (empty($attempt->payment_name) || empty($attempt->extracted_name)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Calculate similarity using the matchNames method
                $result = $method->invoke($matchingService, $attempt->payment_name, $attempt->extracted_name);
                
                $similarity = $result['similarity'] ?? 0;
                
                // Update the match attempt
                $attempt->update([
                    'name_similarity_percent' => $similarity
                ]);
                
                $updated++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("\nError processing attempt ID {$attempt->id}: {$e->getMessage()}");
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Results ===');
        $this->line("Total attempts: {$total}");
        $this->line("Updated: {$updated}");
        $this->line("Skipped (no names): {$skipped}");
        $this->line("Errors: {$errors}");
        $this->newLine();

        // Calculate final stats
        $totalScore = MatchAttempt::whereNotNull('name_similarity_percent')->sum('name_similarity_percent');
        $totalAttempts = MatchAttempt::whereNotNull('name_similarity_percent')->count();
        $averageScore = $totalAttempts > 0 ? round($totalScore / $totalAttempts, 2) : 0;

        $this->info('=== Final Statistics ===');
        $this->line("Total Match Similarity Score: {$totalScore}");
        $this->line("Total Attempts with Scores: {$totalAttempts}");
        $this->line("Average Similarity: {$averageScore}%");
        $this->newLine();

        $this->info('✅ Calculation complete!');

        return 0;
    }
}
