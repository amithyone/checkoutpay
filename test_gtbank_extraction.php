<?php

/**
 * Test script for GTBank email extraction
 * Run from command line: php test_gtbank_extraction.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing GTBank Email Extraction...\n\n";

// Sample GTBank email text body (from user's email)
$sampleEmail = <<<EMAIL
=20 	
1:40:07 AM
Dear  FASTIFYFOOD LTD
Guaranty Trust Bank electronic Notification Service (GeNS)
We wish to inform you that a CREDIT transaction occurred on = your account with us.
 
The details of this transaction are shown below:
Transaction Notification
=20
Account Number	:	3002156642
Transaction Location	:	205
Description	:	=20 090405260110014006799532206126-AMITHY ONE M TRF FOR = CUSTOMERAT126TRF2MPT4E0RT200 =20
Amount	:	NGN 1000
Value Date	:	2026-01-10
Remarks	:	9787297119 301632AT126TRF2MPT4E0RT20097872971193-M
Time of Transaction	:	1:40:07 AM
Document Number	:	=20
The balances on this account as at  1:40:07 = AM  are as follows;
Current Balance	
:
NGN  39950 
Available Balance	
:
NGN  39828.86 
EMAIL;

$htmlEmail = <<<HTML
<table>
<tr><td>Account Number</td><td>3002156642</td></tr>
<tr><td>Transaction Location</td><td>205</td></tr>
<tr><td>Description</td><td>=20 090405260110014006799532206126-AMITHY ONE M TRF FOR = CUSTOMERAT126TRF2MPT4E0RT200 =20</td></tr>
<tr><td>Amount</td><td>NGN 1000</td></tr>
<tr><td>Value Date</td><td>2026-01-10</td></tr>
<tr><td>Remarks</td><td>9787297119 301632AT126TRF2MPT4E0RT20097872971193-M</td></tr>
<tr><td>Time of Transaction</td><td>1:40:07 AM</td></tr>
</table>
HTML;

try {
    $matchingService = new \App\Services\PaymentMatchingService();
    
    $emailData = [
        'processed_email_id' => 999,
        'subject' => 'Transaction Notification',
        'from' => 'noreply@gtbank.com',
        'text' => $sampleEmail,
        'html' => $htmlEmail,
        'date' => '2026-01-10 01:40:07',
    ];
    
    echo "Email Data:\n";
    echo "- Subject: {$emailData['subject']}\n";
    echo "- From: {$emailData['from']}\n";
    echo "- Text length: " . strlen($emailData['text']) . " chars\n";
    echo "- HTML length: " . strlen($emailData['html']) . " chars\n\n";
    
    echo "Extracting payment information...\n\n";
    
    $result = $matchingService->extractPaymentInfo($emailData);
    
    if ($result) {
        echo "✅ Extraction successful!\n\n";
        echo "Extracted Data:\n";
        echo "- Method: " . ($result['method'] ?? 'unknown') . "\n";
        
        $data = $result['data'] ?? [];
        echo "- Amount: " . ($data['amount'] ?? 'N/A') . "\n";
        echo "- Currency: " . ($data['currency'] ?? 'N/A') . "\n";
        echo "- Sender Name: " . ($data['sender_name'] ?? 'N/A') . "\n";
        echo "- Account Number: " . ($data['account_number'] ?? 'N/A') . "\n";
        echo "- Direction: " . ($data['direction'] ?? 'N/A') . "\n";
        
        echo "\nFull Result:\n";
        print_r($result);
        
        // Expected values
        echo "\n\nExpected Values:\n";
        echo "- Amount: 1000 ✅\n";
        echo "- Sender Name: amithy one m ✅\n";
        echo "- Account Number: 3002156642 ✅\n";
        
        // Verify
        $amountMatches = isset($data['amount']) && abs($data['amount'] - 1000) < 0.01;
        $nameMatches = isset($data['sender_name']) && strtolower($data['sender_name']) === 'amithy one m';
        $accountMatches = isset($data['account_number']) && $data['account_number'] === '3002156642';
        
        echo "\nVerification:\n";
        echo "- Amount match: " . ($amountMatches ? "✅ PASS" : "❌ FAIL (got: {$data['amount']})") . "\n";
        echo "- Name match: " . ($nameMatches ? "✅ PASS" : "❌ FAIL (got: '{$data['sender_name']}')") . "\n";
        echo "- Account match: " . ($accountMatches ? "✅ PASS" : "❌ FAIL (got: '{$data['account_number']}')") . "\n";
        
        if ($amountMatches && $nameMatches && $accountMatches) {
            echo "\n🎉 All tests passed!\n";
        } else {
            echo "\n⚠️  Some tests failed. Review extraction patterns.\n";
        }
        
    } else {
        echo "❌ Extraction returned null\n";
        echo "Check extraction patterns and quoted-printable decoding.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✅ Test completed!\n";
