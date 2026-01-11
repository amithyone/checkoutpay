<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProcessedEmail;

echo "=== Checking Description Field Formats in Database ===\n\n";

// Get failed emails (where description_field is NULL)
$failedEmails = ProcessedEmail::whereNull('description_field')
    ->whereNotNull('text_body')
    ->limit(10)
    ->get();

foreach ($failedEmails as $email) {
    echo "Email ID: {$email->id} | Subject: " . substr($email->subject ?? 'No Subject', 0, 50) . "\n";
    echo "text_body length: " . strlen($email->text_body ?? '') . "\n";
    
    // Find description line
    if (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $email->text_body ?? '', $matches)) {
        $descLine = trim($matches[1]);
        echo "Description line: " . substr($descLine, 0, 100) . "\n";
        
        // Find all digit sequences
        if (preg_match_all('/(\d{20,})/', $descLine, $digitMatches)) {
            foreach ($digitMatches[1] as $digits) {
                echo "  Found " . strlen($digits) . " consecutive digits: " . substr($digits, 0, 50) . "\n";
            }
        }
        
        // Try to find the full pattern
        if (preg_match('/description[\s]*:[\s]*(\d{10,50})/i', $email->text_body ?? '', $fullMatch)) {
            $fullDigits = trim($fullMatch[1]);
            echo "  Full digit sequence: " . $fullDigits . " (length: " . strlen($fullDigits) . ")\n";
        }
    }
    
    echo "\n";
}
