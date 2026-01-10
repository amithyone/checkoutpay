<?php

/**
 * Test script for PythonExtractionService
 * Run from command line: php test_python_service.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing PythonExtractionService...\n\n";

try {
    $service = new \App\Services\PythonExtractionService();
    
    echo "✅ Service instantiated successfully\n\n";
    
    echo "Checking if service is available...\n";
    $isAvailable = $service->isAvailable();
    
    if ($isAvailable) {
        echo "✅ Python extraction service is AVAILABLE\n\n";
        
        // Test extraction with sample data
        echo "Testing extraction with sample email data...\n";
        $emailData = [
            'processed_email_id' => 1,
            'subject' => 'Account Credited',
            'from' => 'noreply@gtbank.com',
            'text' => 'Your account has been credited with NGN 5,000.00',
            'html' => '<table><tr><td>Amount</td><td>NGN 5,000.00</td></tr></table>',
            'date' => now()->toISOString(),
        ];
        
        $result = $service->extractPaymentInfo($emailData);
        
        if ($result) {
            echo "✅ Extraction successful!\n";
            echo "Method: " . ($result['method'] ?? 'unknown') . "\n";
            echo "Amount: " . ($result['data']['amount'] ?? 'N/A') . "\n";
            echo "Currency: " . ($result['data']['currency'] ?? 'N/A') . "\n";
            echo "Confidence: " . ($result['confidence'] ?? 'N/A') . "\n";
            echo "\nFull result:\n";
            print_r($result);
        } else {
            echo "⚠️  Extraction returned null (this might be normal if extraction failed validation)\n";
        }
    } else {
        echo "❌ Python extraction service is NOT available\n";
        echo "This might be normal if:\n";
        echo "  - Python script is not found\n";
        echo "  - Python command is not available\n";
        echo "  - FastAPI service is not running (if using HTTP mode)\n";
        echo "\nCheck your configuration in config/services.php\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✅ Test completed successfully!\n";
