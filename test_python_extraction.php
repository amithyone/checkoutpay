<?php
/**
 * Simple test script for Python extraction service
 * Run this from command line: php test_python_extraction.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Python Extraction Service Test ===\n\n";

// Check configuration
echo "1. Checking configuration...\n";
$config = config('services.python_extractor');
echo "   Enabled: " . ($config['enabled'] ?? 'not set') . "\n";
echo "   Mode: " . ($config['mode'] ?? 'not set') . "\n";
echo "   Script Path: " . ($config['script_path'] ?? 'not set') . "\n";
echo "   Python Command: " . ($config['python_command'] ?? 'not set') . "\n\n";

// Create service
echo "2. Creating PythonExtractionService...\n";
try {
    $service = new \App\Services\PythonExtractionService();
    echo "   ✓ Service created successfully\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error creating service: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if available
echo "3. Checking if Python service is available...\n";
try {
    $available = $service->isAvailable();
    echo "   Result: " . ($available ? "✓ YES (Available)" : "✗ NO (Not Available)") . "\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error checking availability: " . $e->getMessage() . "\n\n";
    $available = false;
}

if (!$available) {
    echo "⚠️  Python service is not available. Check:\n";
    echo "   - Script path: " . ($config['script_path'] ?? 'not set') . "\n";
    echo "   - Python command: " . ($config['python_command'] ?? 'not set') . "\n";
    echo "   - Script exists: " . (file_exists($config['script_path'] ?? '') ? 'YES' : 'NO') . "\n\n";
    
    // Try to check Python directly
    echo "4. Testing Python directly...\n";
    $pythonCmd = $config['python_command'] ?? 'python3';
    $testCmd = escapeshellarg($pythonCmd) . ' --version 2>&1';
    $output = shell_exec($testCmd);
    echo "   Command: {$testCmd}\n";
    echo "   Output: " . ($output ?: 'No output') . "\n\n";
    
    exit(1);
}

// Test extraction
echo "4. Testing extraction...\n";
$emailData = [
    'processed_email_id' => 1,
    'subject' => 'Credit Alert - GTBank',
    'from' => 'noreply@gtbank.com',
    'text_body' => 'Your account has been credited with the sum of One Thousand Naira Only (NGN 1,000.00).',
    'html_body' => '<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr><tr><td>Description</td><td>FROM JOHN DOE TO SQUA</td></tr></table>',
    'date' => now()->toDateTimeString(),
];

try {
    $result = $service->extractPaymentInfo($emailData);
    
    if ($result) {
        echo "   ✓ Extraction successful!\n";
        echo "   Amount: ₦" . number_format($result['data']['amount'] ?? 0, 2) . "\n";
        echo "   Method: " . ($result['method'] ?? 'unknown') . "\n";
        echo "   Confidence: " . (($result['confidence'] ?? 0) * 100) . "%\n";
        echo "   Sender Name: " . ($result['data']['sender_name'] ?? 'Not found') . "\n";
        echo "   Currency: " . ($result['data']['currency'] ?? 'NGN') . "\n";
        
        if (isset($result['diagnostics'])) {
            echo "\n   Diagnostics:\n";
            if (isset($result['diagnostics']['steps'])) {
                foreach ($result['diagnostics']['steps'] as $step) {
                    echo "     - {$step}\n";
                }
            }
        }
        
        echo "\n   ✓✓✓ Python extraction is working correctly! ✓✓✓\n";
    } else {
        echo "   ✗ Extraction failed - no result returned\n";
        echo "   Check logs for details: storage/logs/laravel.log\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error during extraction: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
