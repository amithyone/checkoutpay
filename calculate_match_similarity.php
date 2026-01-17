<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MatchAttempt;
use App\Services\PaymentMatchingService;

echo "=== Calculating Match Similarity Scores ===\n\n";

$matchingService = new PaymentMatchingService();

// Get all match attempts
$attempts = MatchAttempt::all();

$total = $attempts->count();
$updated = 0;
$skipped = 0;
$errors = 0;

echo "Total match attempts: {$total}\n\n";

foreach ($attempts as $attempt) {
    try {
        // Skip if both names are missing
        if (empty($attempt->payment_name) || empty($attempt->extracted_name)) {
            $skipped++;
            continue;
        }
        
        // Calculate similarity using the matchNames method
        // Use reflection to access protected method
        $reflection = new ReflectionClass($matchingService);
        $method = $reflection->getMethod('matchNames');
        $method->setAccessible(true);
        
        $result = $method->invoke($matchingService, $attempt->payment_name, $attempt->extracted_name);
        
        $similarity = $result['similarity'] ?? 0;
        
        // Update the match attempt
        $attempt->update([
            'name_similarity_percent' => $similarity
        ]);
        
        $updated++;
        
        if ($updated % 10 === 0) {
            echo "Processed: {$updated}/{$total}\n";
        }
    } catch (\Exception $e) {
        $errors++;
        echo "Error processing attempt ID {$attempt->id}: {$e->getMessage()}\n";
    }
}

echo "\n=== Results ===\n";
echo "Total attempts: {$total}\n";
echo "Updated: {$updated}\n";
echo "Skipped (no names): {$skipped}\n";
echo "Errors: {$errors}\n\n";

// Calculate final stats
$totalScore = MatchAttempt::whereNotNull('name_similarity_percent')->sum('name_similarity_percent');
$totalAttempts = MatchAttempt::whereNotNull('name_similarity_percent')->count();
$averageScore = $totalAttempts > 0 ? round($totalScore / $totalAttempts, 2) : 0;

echo "=== Final Statistics ===\n";
echo "Total Match Similarity Score: {$totalScore}\n";
echo "Total Attempts with Scores: {$totalAttempts}\n";
echo "Average Similarity: {$averageScore}%\n";

echo "\n✅ Calculation complete!\n";
