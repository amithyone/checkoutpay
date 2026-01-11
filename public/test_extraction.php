<?php

/**
 * Web-accessible version of test_live_db_extraction.php
 * Access via: https://check-outpay.com/test_extraction.php
 * 
 * WARNING: This script exposes database information. 
 * Delete this file after diagnosis or protect it with authentication.
 */

// Only allow access if password is provided or from localhost
$password = $_GET['password'] ?? '';
$allowedPassword = 'diagnose2026'; // Change this or use .htaccess protection

if ($password !== $allowedPassword && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    die('Access denied. Provide password: ?password=your_password');
}

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Extraction Diagnostics</title>
    <style>
        body { font-family: monospace; font-size: 12px; padding: 20px; background: #1a1a1a; color: #0f0; }
        h1, h2 { color: #0ff; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        pre { background: #2a2a2a; padding: 10px; border: 1px solid #444; overflow-x: auto; }
        .section { margin: 20px 0; padding: 10px; border: 1px solid #444; }
    </style>
</head>
<body>
<h1>=== Testing Live Database Extraction ===</h1>

<?php
// Get a recent unmatched email that should have account number in description
$email = ProcessedEmail::where('is_matched', false)
    ->where(function($query) {
        $query->where('html_body', 'LIKE', '%9008771210%')
            ->orWhere('text_body', 'LIKE', '%9008771210%')
            ->orWhere('html_body', 'LIKE', '%Description%')
            ->orWhere('html_body', 'LIKE', '%FROM%');
    })
    ->latest()
    ->first();

if (!$email) {
    echo "<p class='warning'>⚠️ No unmatched email found with description field</p>";
    echo "<p>Trying to find ANY recent email...</p>";
    $email = ProcessedEmail::latest()->first();
}

if (!$email) {
    echo "<p class='error'>❌ No emails found in database</p>";
    exit;
}

echo "<div class='section'>";
echo "<h2>Found Email</h2>";
echo "<p><strong>ID:</strong> {$email->id}</p>";
echo "<p><strong>Subject:</strong> " . htmlspecialchars($email->subject) . "</p>";
echo "<p><strong>From:</strong> " . htmlspecialchars($email->from_email) . "</p>";
echo "<p><strong>Date:</strong> " . $email->email_date . "</p>";
echo "<p><strong>Matched:</strong> " . ($email->is_matched ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Extraction Method:</strong> " . ($email->extraction_method ?? 'NULL') . "</p>";
echo "<p><strong>Account Number (extracted):</strong> " . ($email->account_number ?? 'NULL') . "</p>";
echo "<p><strong>Amount (extracted):</strong> " . ($email->amount ?? 'NULL') . "</p>";
echo "<p><strong>Sender Name (extracted):</strong> " . ($email->sender_name ?? 'NULL') . "</p>";
echo "</div>";

// Show HTML body length
$htmlLength = strlen($email->html_body ?? '');
$textLength = strlen($email->text_body ?? '');

echo "<div class='section'>";
echo "<h2>Email Content Lengths</h2>";
echo "<p>HTML Body Length: {$htmlLength} chars</p>";
echo "<p>Text Body Length: {$textLength} chars</p>";
echo "</div>";

// Extract a snippet of HTML body around description field
if ($htmlLength > 0) {
    echo "<div class='section'>";
    echo "<h2>HTML Body Analysis</h2>";
    
    // Look for description field in HTML
    if (preg_match('/(?s)(<td[^>]*>[\s]*(?:description|Description)[\s:]*<\/td>.*?<\/td>)/i', $email->html_body, $descMatches)) {
        echo "<p class='success'>✅ Found Description field in HTML:</p>";
        echo "<pre>" . htmlspecialchars(substr($descMatches[1], 0, 500)) . "...</pre>";
    } else {
        echo "<p class='error'>❌ Description field NOT found in HTML</p>";
        
        // Try to find account number pattern in HTML
        if (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/i', $email->html_body, $patternMatches)) {
            echo "<p class='success'>✅ Found digit pattern in HTML:</p>";
            echo "<ul>";
            echo "<li>First 10 digits (account): " . htmlspecialchars($patternMatches[1]) . "</li>";
            echo "<li>Next 10 digits (payer): " . htmlspecialchars($patternMatches[2]) . "</li>";
            echo "<li>Amount (÷100): " . ($patternMatches[3] / 100) . "</li>";
            echo "<li>Date: " . htmlspecialchars($patternMatches[4]) . "</li>";
            echo "</ul>";
            
            // Show context around this pattern
            $pos = strpos($email->html_body, $patternMatches[0]);
            $snippet = substr($email->html_body, max(0, $pos - 100), 300);
            echo "<p>Context around pattern:</p>";
            echo "<pre>" . htmlspecialchars($snippet) . "</pre>";
        } else {
            echo "<p class='error'>❌ Digit pattern NOT found in HTML</p>";
        }
        
        // Show HTML preview
        echo "<p>HTML Preview (first 1000 chars):</p>";
        echo "<pre>" . htmlspecialchars(substr($email->html_body, 0, 1000)) . "...</pre>";
    }
    echo "</div>";
}

// Extract a snippet of text body
if ($textLength > 0) {
    echo "<div class='section'>";
    echo "<h2>Text Body Analysis</h2>";
    
    // Look for description field in text
    if (preg_match('/(Description[\s:]+.*?(?:\n|$))/i', $email->text_body, $descMatches)) {
        echo "<p class='success'>✅ Found Description field in Text:</p>";
        echo "<pre>" . htmlspecialchars(substr($descMatches[1], 0, 500)) . "...</pre>";
    } else {
        echo "<p class='error'>❌ Description field NOT found in Text</p>";
        
        // Try to find account number pattern in text
        if (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/i', $email->text_body, $patternMatches)) {
            echo "<p class='success'>✅ Found digit pattern in Text:</p>";
            echo "<ul>";
            echo "<li>First 10 digits (account): " . htmlspecialchars($patternMatches[1]) . "</li>";
            echo "<li>Next 10 digits (payer): " . htmlspecialchars($patternMatches[2]) . "</li>";
            echo "<li>Amount (÷100): " . ($patternMatches[3] / 100) . "</li>";
            echo "<li>Date: " . htmlspecialchars($patternMatches[4]) . "</li>";
            echo "</ul>";
            
            // Show context around this pattern
            $pos = strpos($email->text_body, $patternMatches[0]);
            $snippet = substr($email->text_body, max(0, $pos - 100), 300);
            echo "<p>Context around pattern:</p>";
            echo "<pre>" . htmlspecialchars($snippet) . "</pre>";
        } else {
            echo "<p class='error'>❌ Digit pattern NOT found in Text</p>";
        }
        
        // Show text preview
        echo "<p>Text Preview (first 1000 chars):</p>";
        echo "<pre>" . htmlspecialchars(substr($email->text_body, 0, 1000)) . "...</pre>";
    }
    echo "</div>";
}

// Now try extraction with PaymentMatchingService
echo "<div class='section'>";
echo "<h2>Testing Extraction with PaymentMatchingService</h2>";

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
        echo "<p class='success'>✅ Extraction successful!</p>";
        echo "<p><strong>Method:</strong> " . htmlspecialchars($result['method'] ?? 'unknown') . "</p>";
        echo "<h3>Extracted Data:</h3>";
        echo "<ul>";
        echo "<li>Amount: " . ($result['data']['amount'] ?? 'NULL') . "</li>";
        echo "<li>Account Number: " . ($result['data']['account_number'] ?? 'NULL') . "</li>";
        echo "<li>Payer Account Number: " . ($result['data']['payer_account_number'] ?? 'NULL') . "</li>";
        echo "<li>Sender Name: " . htmlspecialchars($result['data']['sender_name'] ?? 'NULL') . "</li>";
        echo "<li>Extracted Date: " . ($result['data']['extracted_date'] ?? 'NULL') . "</li>";
        echo "<li>Transaction Time: " . ($result['data']['transaction_time'] ?? 'NULL') . "</li>";
        echo "</ul>";
        
        if (isset($result['diagnostics'])) {
            echo "<h3>Diagnostics:</h3>";
            echo "<p>Steps: " . htmlspecialchars(implode(', ', $result['diagnostics']['steps'] ?? [])) . "</p>";
            if (!empty($result['diagnostics']['errors'])) {
                echo "<p class='error'>Errors: " . htmlspecialchars(implode(', ', $result['diagnostics']['errors'])) . "</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ Extraction failed - no data returned</p>";
        
        // Check diagnostics
        $diagnostics = $matchingService->getLastExtractionDiagnostics();
        if ($diagnostics) {
            echo "<h3>Extraction Diagnostics:</h3>";
            echo "<h4>Steps:</h4>";
            echo "<ul>";
            foreach ($diagnostics['steps'] ?? [] as $step) {
                echo "<li>" . htmlspecialchars($step) . "</li>";
            }
            echo "</ul>";
            if (!empty($diagnostics['errors'])) {
                echo "<h4>Errors:</h4>";
                echo "<ul>";
                foreach ($diagnostics['errors'] as $error) {
                    echo "<li class='error'>" . htmlspecialchars($error) . "</li>";
                }
                echo "</ul>";
            }
            if (isset($diagnostics['html_preview'])) {
                echo "<h4>HTML Preview (first 500 chars):</h4>";
                echo "<pre>" . htmlspecialchars(substr($diagnostics['html_preview'], 0, 500)) . "...</pre>";
            }
            if (isset($diagnostics['text_preview'])) {
                echo "<h4>Text Preview (first 500 chars):</h4>";
                echo "<pre>" . htmlspecialchars(substr($diagnostics['text_preview'], 0, 500)) . "...</pre>";
            }
        }
    }
} catch (\Exception $e) {
    echo "<p class='error'>❌ Error during extraction: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
echo "</div>";

echo "<div class='section'>";
echo "<p><strong>Note:</strong> Delete this file after diagnosis for security.</p>";
echo "</div>";
?>

</body>
</html>
