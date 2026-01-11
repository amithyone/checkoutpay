<?php

/**
 * Test script to analyze live database extraction
 * This will help us see why extraction is failing
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;

echo "=== Testing Live Database Extraction ===\n\n";

// Get a recent unmatched email that should have account number in description
$email = ProcessedEmail::where('is_matched', false)
    ->where('html_body', 'LIKE', '%9008771210%')
    ->orWhere('text_body', 'LIKE', '%9008771210%')
    ->orWhere('html_body', 'LIKE', '%Description%')
    ->orWhere('html_body', 'LIKE', '%FROM%')
    ->latest()
    ->first();

if (!$email) {
    echo "❌ No unmatched email found with description field\n";
    echo "Trying to find ANY recent email...\n\n";
    $email = ProcessedEmail::latest()->first();
}

if (!$email) {
    echo "❌ No emails found in database\n";
    exit(1);
}

echo "Found Email ID: {$email->id}\n";
echo "Subject: {$email->subject}\n";
echo "From: {$email->from_email}\n";
echo "Date: {$email->email_date}\n";
echo "Matched: " . ($email->is_matched ? 'Yes' : 'No') . "\n";
echo "Extraction Method: " . ($email->extraction_method ?? 'NULL') . "\n";
echo "Account Number (extracted): " . ($email->account_number ?? 'NULL') . "\n";
echo "Amount (extracted): " . ($email->amount ?? 'NULL') . "\n";
echo "Sender Name (extracted): " . ($email->sender_name ?? 'NULL') . "\n";
echo "\n";

// Show HTML body length
$htmlLength = strlen($email->html_body ?? '');
$textLength = strlen($email->text_body ?? '');
echo "HTML Body Length: {$htmlLength} chars\n";
echo "Text Body Length: {$textLength} chars\n";
echo "\n";

// Extract a snippet of HTML body around description field
if ($htmlLength > 0) {
    echo "=== HTML Body Analysis ===\n";
    
    // Look for description field in HTML
    if (preg_match('/(?s)(<td[^>]*>[\s]*(?:description|Description)[\s:]*<\/td>.*?<\/td>)/i', $email->html_body, $descMatches)) {
        echo "✅ Found Description field in HTML:\n";
        echo substr($descMatches[1], 0, 500) . "...\n\n";
    } else {
        echo "❌ Description field NOT found in HTML\n";
        
        // Try to find account number pattern in HTML
        if (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/i', $email->html_body, $patternMatches)) {
            echo "✅ Found digit pattern in HTML:\n";
            echo "  First 10 digits (account): " . $patternMatches[1] . "\n";
            echo "  Next 10 digits (payer): " . $patternMatches[2] . "\n";
            echo "  Amount (÷100): " . ($patternMatches[3] / 100) . "\n";
            echo "  Date: " . $patternMatches[4] . "\n\n";
            
            // Show context around this pattern
            $pos = strpos($email->html_body, $patternMatches[0]);
            $snippet = substr($email->html_body, max(0, $pos - 100), 300);
            echo "Context around pattern:\n";
            echo $snippet . "\n\n";
        } else {
            echo "❌ Digit pattern NOT found in HTML\n";
        }
        
        // Show HTML preview
        echo "HTML Preview (first 1000 chars):\n";
        echo substr($email->html_body, 0, 1000) . "...\n\n";
    }
}

// Extract a snippet of text body
if ($textLength > 0) {
    echo "=== Text Body Analysis ===\n";
    
    // Look for description field in text
    if (preg_match('/(Description[\s:]+.*?(?:\n|$))/i', $email->text_body, $descMatches)) {
        echo "✅ Found Description field in Text:\n";
        echo substr($descMatches[1], 0, 500) . "...\n\n";
    } else {
        echo "❌ Description field NOT found in Text\n";
        
        // Try to find account number pattern in text
        if (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/i', $email->text_body, $patternMatches)) {
            echo "✅ Found digit pattern in Text:\n";
            echo "  First 10 digits (account): " . $patternMatches[1] . "\n";
            echo "  Next 10 digits (payer): " . $patternMatches[2] . "\n";
            echo "  Amount (÷100): " . ($patternMatches[3] / 100) . "\n";
            echo "  Date: " . $patternMatches[4] . "\n\n";
            
            // Show context around this pattern
            $pos = strpos($email->text_body, $patternMatches[0]);
            $snippet = substr($email->text_body, max(0, $pos - 100), 300);
            echo "Context around pattern:\n";
            echo $snippet . "\n\n";
        } else {
            echo "❌ Digit pattern NOT found in Text\n";
        }
        
        // Show text preview
        echo "Text Preview (first 1000 chars):\n";
        echo substr($email->text_body, 0, 1000) . "...\n\n";
    }
}

// DETAILED PATTERN TESTING
echo "\n=== Testing Patterns Directly ===\n";

// Test 1: Direct pattern match on text_body
if ($email->text_body) {
    $textBody = $email->text_body;
    echo "\n--- Pattern Test 1: text_body ---\n";
    echo "Text body snippet: " . substr($textBody, 0, 200) . "...\n";
    
    // Test our 43-digit pattern
    if (preg_match('/description[\s]*:[\s]*(\d{43})(?:\s|FROM|$)/i', $textBody, $matches)) {
        echo "✅ Pattern MATCHED! Found: " . $matches[1] . "\n";
        echo "   Length: " . strlen($matches[1]) . " digits\n";
        
        // Parse the 43 digits
        if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $matches[1], $digits)) {
            echo "   ✅ Parsed successfully:\n";
            echo "      Recipient Account: " . $digits[1] . "\n";
            echo "      Payer Account: " . $digits[2] . "\n";
            echo "      Amount: " . ($digits[3] / 100) . "\n";
            echo "      Date: " . $digits[4] . "\n";
        } else {
            echo "   ❌ Failed to parse 43 digits\n";
        }
    } else {
        echo "❌ Pattern did NOT match on text_body\n";
        
        // Try alternative patterns
        if (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $textBody, $altMatches)) {
            echo "   Found description field: " . substr($altMatches[1], 0, 100) . "...\n";
        }
        
        // Try finding just the 43 digits
        if (preg_match('/(\d{43})/', $textBody, $digitMatches)) {
            echo "   Found 43 digits somewhere: " . $digitMatches[1] . "\n";
        }
    }
}

// Test 2: Pattern match on HTML converted to plain text
if ($email->html_body) {
    $htmlBody = $email->html_body;
    $plainText = strip_tags($htmlBody);
    $plainText = preg_replace('/\s+/', ' ', $plainText);
    
    echo "\n--- Pattern Test 2: html_body (converted to plain text) ---\n";
    echo "Plain text snippet: " . substr($plainText, 0, 200) . "...\n";
    
    // Test our 43-digit pattern
    if (preg_match('/description[\s]*:[\s]*(\d{43})(?:\s|FROM|$)/i', $plainText, $matches)) {
        echo "✅ Pattern MATCHED! Found: " . $matches[1] . "\n";
        echo "   Length: " . strlen($matches[1]) . " digits\n";
        
        // Parse the 43 digits
        if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $matches[1], $digits)) {
            echo "   ✅ Parsed successfully:\n";
            echo "      Recipient Account: " . $digits[1] . "\n";
            echo "      Payer Account: " . $digits[2] . "\n";
            echo "      Amount: " . ($digits[3] / 100) . "\n";
            echo "      Date: " . $digits[4] . "\n";
        } else {
            echo "   ❌ Failed to parse 43 digits\n";
        }
    } else {
        echo "❌ Pattern did NOT match on plain text\n";
        
        // Try alternative patterns
        if (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $plainText, $altMatches)) {
            echo "   Found description field: " . substr($altMatches[1], 0, 100) . "...\n";
        }
        
        // Try finding just the 43 digits
        if (preg_match('/(\d{43})/', $plainText, $digitMatches)) {
            echo "   Found 43 digits somewhere: " . $digitMatches[1] . "\n";
        }
    }
}

// Now try extraction with PaymentMatchingService
echo "\n=== Testing Extraction with PaymentMatchingService ===\n";
$matchingService = app(PaymentMatchingService::class);

$emailData = [
    'subject' => $email->subject,
    'from' => $email->from_email,
    'text' => $email->text_body ?? '',
    'html' => $email->html_body ?? '',
    'date' => $email->email_date ? $email->email_date->toDateTimeString() : null,
    'email_account_id' => $email->email_account_id,
    'processed_email_id' => $email->id,
];

try {
    $result = $matchingService->extractPaymentInfo($emailData);
    
    if ($result && isset($result['data'])) {
        echo "✅ Extraction successful!\n";
        echo "Method: " . ($result['method'] ?? 'unknown') . "\n";
        echo "Extracted Data:\n";
        echo "  Amount: " . ($result['data']['amount'] ?? 'NULL') . "\n";
        echo "  Account Number: " . ($result['data']['account_number'] ?? 'NULL') . "\n";
        echo "  Payer Account Number: " . ($result['data']['payer_account_number'] ?? 'NULL') . "\n";
        echo "  Sender Name: " . ($result['data']['sender_name'] ?? 'NULL') . "\n";
        echo "  Extracted Date: " . ($result['data']['extracted_date'] ?? 'NULL') . "\n";
        echo "  Transaction Time: " . ($result['data']['transaction_time'] ?? 'NULL') . "\n";
        
        if (isset($result['data']['description_field'])) {
            echo "  Description Field: " . $result['data']['description_field'] . "\n";
        }
        
        if (isset($result['diagnostics'])) {
            echo "\nDiagnostics:\n";
            echo "  Steps: " . implode(', ', $result['diagnostics']['steps'] ?? []) . "\n";
            if (!empty($result['diagnostics']['errors'])) {
                echo "  Errors: " . implode(', ', $result['diagnostics']['errors']) . "\n";
            }
        }
    } else {
        echo "❌ Extraction failed - no data returned\n";
    }
} catch (\Exception $e) {
    echo "❌ Error during extraction: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Complete ===\n";
