<?php

/**
 * Test script to debug account number extraction from description field
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Sample HTML from user's issue
$html = <<<'HTML'
<td colspan="8">
  
900877121002100859959000020260111094651392 FROM SOLOMON INNOCENT AMITHY TO SQUAD
 
</td>
HTML;

// Full HTML context (more realistic)
$fullHtml = <<<'HTML'
<table>
<tr>
<td>Description</td>
<td>:</td>
<td colspan="8">
  
900877121002100859959000020260111094651392 FROM SOLOMON INNOCENT AMITHY TO SQUAD
 
</td>
</tr>
</table>
HTML;

echo "=== Testing Account Number Extraction from Description Field ===\n\n";

// Test the patterns from PaymentMatchingService
$patterns = [
    'Pattern 1 (with space)' => '/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})[\s]+(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i',
    'Pattern 2 (no space - expected)' => '/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i',
    'Pattern 3 (with dash)' => '/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[\d\-]*\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i',
];

echo "Testing with full HTML context:\n";
echo "HTML length: " . strlen($fullHtml) . " chars\n";
echo "Looking for: 900877121002100859959000020260111094651392 FROM SOLOMON INNOCENT AMITHY TO SQUAD\n\n";

foreach ($patterns as $name => $pattern) {
    echo "--- $name ---\n";
    if (preg_match($pattern, $fullHtml, $matches)) {
        echo "✅ MATCHED!\n";
        echo "Matches found: " . count($matches) . "\n";
        foreach ($matches as $i => $match) {
            echo "  Match[$i]: " . substr($match, 0, 50) . (strlen($match) > 50 ? '...' : '') . "\n";
        }
        if (isset($matches[1])) {
            echo "  → Account Number (first 10 digits): " . $matches[1] . "\n";
        }
        if (isset($matches[2])) {
            echo "  → Payer Account (next 10 digits): " . $matches[2] . "\n";
        }
    } else {
        echo "❌ NO MATCH\n";
        echo "Pattern: " . substr($pattern, 0, 100) . "...\n";
        
        // Show what the HTML actually contains
        if (preg_match('/(?s)<td[^>]*>[\s]*(?:description)[\s:]*<\/td>\s*<td[^>]*>(.*?)<\/td>/i', $fullHtml, $descMatches)) {
            echo "Description cell content found: " . trim($descMatches[1]) . "\n";
            echo "Length: " . strlen(trim($descMatches[1])) . " chars\n";
        }
    }
    echo "\n";
}

// Test with simplified HTML (just the description cell)
echo "\n=== Testing with simplified HTML (just description cell) ===\n";
$simplifiedHtml = '<td>Description</td><td>:</td><td colspan="8">900877121002100859959000020260111094651392 FROM SOLOMON INNOCENT AMITHY TO SQUAD</td>';

echo "HTML: $simplifiedHtml\n\n";

foreach ($patterns as $name => $pattern) {
    echo "--- $name ---\n";
    if (preg_match($pattern, $simplifiedHtml, $matches)) {
        echo "✅ MATCHED!\n";
        if (isset($matches[1])) {
            echo "  → Account Number: " . $matches[1] . "\n";
        }
        if (isset($matches[2])) {
            echo "  → Payer Account: " . $matches[2] . "\n";
        }
    } else {
        echo "❌ NO MATCH\n";
    }
    echo "\n";
}

// Test pattern that might work better
echo "\n=== Testing improved pattern ===\n";
$improvedPattern = '/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i';
echo "Pattern: Handles the ':' in separate cell\n";

if (preg_match($improvedPattern, $fullHtml, $matches)) {
    echo "✅ MATCHED with improved pattern!\n";
    echo "Account Number: " . ($matches[1] ?? 'NOT FOUND') . "\n";
    echo "Payer Account: " . ($matches[2] ?? 'NOT FOUND') . "\n";
    echo "Amount: " . (isset($matches[3]) ? ($matches[3] / 100) : 'NOT FOUND') . "\n";
    echo "Date: " . ($matches[4] ?? 'NOT FOUND') . "\n";
    echo "Sender Name: " . ($matches[6] ?? 'NOT FOUND') . "\n";
} else {
    echo "❌ Still no match\n";
    
    // Debug: Let's see what's actually in the HTML
    echo "\nDebugging HTML structure:\n";
    if (preg_match('/(?s)<td[^>]*>[\s]*description[\s:]*<\/td>(.*?)<\/td>/i', $fullHtml, $debugMatches)) {
        echo "Found description row content: " . substr($debugMatches[0], 0, 200) . "...\n";
    }
    
    // Try a very simple pattern
    if (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM/i', $fullHtml, $simpleMatches)) {
        echo "\n✅ Found digits pattern with simple regex:\n";
        echo "  Account Number: " . $simpleMatches[1] . "\n";
        echo "  Payer Account: " . $simpleMatches[2] . "\n";
    }
}

echo "\n=== Complete ===\n";
