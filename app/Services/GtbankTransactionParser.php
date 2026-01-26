<?php

namespace App\Services;

use App\Models\GtbankTransaction;
use App\Models\ProcessedEmail;
use App\Models\BankEmailTemplate;
use Illuminate\Support\Facades\Log;

class GtbankTransactionParser
{
    /**
     * Check if email is a GTBank transaction notification
     */
    public function isGtbankTransaction(array $emailData): bool
    {
        $from = strtolower($emailData['from'] ?? '');
        $subject = strtolower($emailData['subject'] ?? '');
        $html = $emailData['html'] ?? '';
        $text = $emailData['text'] ?? '';
        
        // Check sender domain
        $isGtbankDomain = str_contains($from, '@gtbank.com');
        
        // Check for transaction notification
        $hasTransactionNotification = 
            str_contains($subject, 'transaction notification') ||
            str_contains($html, 'transaction notification') ||
            str_contains($text, 'transaction notification');
        
        return $isGtbankDomain && $hasTransactionNotification;
    }

    /**
     * Parse GTBank transaction from email HTML
     */
    public function parseTransaction(array $emailData, ?ProcessedEmail $processedEmail = null, ?BankEmailTemplate $template = null): ?GtbankTransaction
    {
        if (!$this->isGtbankTransaction($emailData)) {
            return null;
        }

        $html = $emailData['html'] ?? '';
        $text = $emailData['text'] ?? '';

        // Extract data from HTML table
        $accountNumber = $this->extractAccountNumber($html, $text);
        $amount = $this->extractAmount($html, $text);
        $senderName = $this->extractSenderName($html, $text);
        $transactionType = $this->extractTransactionType($html, $text);
        $valueDate = $this->extractValueDate($html, $text);
        $narration = $this->extractNarration($html, $text);

        // Validate required fields
        if (!$accountNumber || !$amount || !$valueDate) {
            Log::warning('GTBank transaction missing required fields', [
                'account_number' => $accountNumber,
                'amount' => $amount,
                'value_date' => $valueDate,
            ]);
            return null;
        }

        // Generate duplicate hash
        $duplicateHash = GtbankTransaction::generateDuplicateHash(
            $accountNumber,
            $amount,
            $valueDate,
            $narration
        );

        // Check for duplicates
        if (GtbankTransaction::isDuplicate($duplicateHash)) {
            Log::info('GTBank transaction duplicate detected', [
                'duplicate_hash' => $duplicateHash,
                'account_number' => $accountNumber,
                'amount' => $amount,
            ]);
            return null;
        }

        // Create transaction
        $transaction = GtbankTransaction::create([
            'account_number' => $accountNumber,
            'amount' => $amount,
            'sender_name' => $senderName,
            'transaction_type' => $transactionType,
            'value_date' => $valueDate,
            'narration' => $narration,
            'bank_name' => 'Guaranty Trust Bank',
            'duplicate_hash' => $duplicateHash,
            'processed_email_id' => $processedEmail?->id,
            'bank_template_id' => $template?->id,
        ]);

        Log::info('GTBank transaction created', [
            'transaction_id' => $transaction->id,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'transaction_type' => $transactionType,
        ]);

        return $transaction;
    }

    /**
     * Extract account number from HTML table
     */
    protected function extractAccountNumber(string $html, string $text): ?string
    {
        // Pattern 1: HTML table with "Account Number" label
        if (preg_match('/<td[^>]*>[\s]*(?:account\s*number|account)[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d+)[\s]*<\/td>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        
        // Pattern 2: Same cell format
        if (preg_match('/<td[^>]*>[\s]*(?:account\s*number|account)[\s:]+(\d+)[\s]*<\/td>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        
        // Pattern 3: Text format
        if (preg_match('/(?:account\s*number|account)[\s:]+(\d+)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract amount from HTML table
     * Amount is after "NGN" in GTBank emails
     * Format: "Amount : NGN 1000" (with space after colon and NGN)
     */
    protected function extractAmount(string $html, string $text): ?float
    {
        // Pattern 1: HTML table with "Amount" label, amount after NGN
        // Format: <td>Amount:</td><td>NGN 1,000.00</td>
        if (preg_match('/<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        
        // Pattern 2: Same cell format, amount after NGN
        // Format: <td>Amount: NGN 1,000.00</td>
        if (preg_match('/<td[^>]*>[\s]*amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        
        // Decode quoted-printable encoding (common in email text_body)
        // =20 is space, =3D is equals sign
        $text = preg_replace('/=20/', ' ', $text);
        $text = preg_replace('/=3D/', '=', $text);
        $text = preg_replace('/=([0-9A-F]{2})/i', function($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Pattern 3: Text format - "Amount : NGN 1000" (with space after colon)
        // Format: Amount : NGN 1,000.00 (also handles =20 for space)
        if (preg_match('/amount[\s]*:[\s=]+(?:ngn|naira|₦|NGN)[\s=]+([\d,]+\.?\d*)/i', $text, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        
        // Pattern 4: Text format - "Amount: NGN 1000" (without space after colon)
        // Format: Amount: NGN 1,000.00
        if (preg_match('/amount[\s:]+(?:ngn|naira|₦|NGN)[\s=]+([\d,]+\.?\d*)/i', $text, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        return null;
    }

    /**
     * Extract sender name from Description field using "FROM <NAME> TO" pattern
     */
    protected function extractSenderName(string $html, string $text): ?string
    {
        // Pattern 1: HTML table with "Description" label containing "FROM <NAME> TO"
        if (preg_match('/<td[^>]*>[\s]*description[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            return trim($matches[1]);
        }
        
        // Pattern 2: Same cell format
        if (preg_match('/<td[^>]*>[\s]*description[\s:]+from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            return trim($matches[1]);
        }
        
        // Pattern 3: Text format
        if (preg_match('/description[\s:]+from\s+([A-Z][A-Z\s]+?)\s+to/i', $text, $matches)) {
            return trim($matches[1]);
        }
        
        // Pattern 4: Direct "FROM <NAME> TO" pattern
        if (preg_match('/from\s+([A-Z][A-Z\s]+?)\s+to/i', $html . ' ' . $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract transaction type (CREDIT or DEBIT)
     */
    protected function extractTransactionType(string $html, string $text): string
    {
        $content = strtolower($html . ' ' . $text);
        
        // Check for credit indicators
        if (str_contains($content, 'credit') || 
            str_contains($content, 'credited') ||
            str_contains($content, 'deposit') ||
            str_contains($content, 'received')) {
            return 'CREDIT';
        }
        
        // Check for debit indicators
        if (str_contains($content, 'debit') || 
            str_contains($content, 'debited') ||
            str_contains($content, 'withdrawal') ||
            str_contains($content, 'sent')) {
            return 'DEBIT';
        }
        
        // Default to CREDIT for payment notifications
        return 'CREDIT';
    }

    /**
     * Extract value date from HTML table
     */
    protected function extractValueDate(string $html, string $text): ?string
    {
        // Pattern 1: HTML table with "Value Date" label
        if (preg_match('/<td[^>]*>[\s]*value\s*date[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})[\s]*<\/td>/i', $html, $matches)) {
            return $this->normalizeDate($matches[1]);
        }
        
        // Pattern 2: Same cell format
        if (preg_match('/<td[^>]*>[\s]*value\s*date[\s:]+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})[\s]*<\/td>/i', $html, $matches)) {
            return $this->normalizeDate($matches[1]);
        }
        
        // Pattern 3: Text format
        if (preg_match('/value\s*date[\s:]+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $text, $matches)) {
            return $this->normalizeDate($matches[1]);
        }

        return null;
    }

    /**
     * Extract full narration from Description field
     */
    protected function extractNarration(string $html, string $text): ?string
    {
        // Pattern 1: HTML table with "Description" label
        if (preg_match('/<td[^>]*>[\s]*description[\s:]*<\/td>\s*<td[^>]*>[\s]*(.+?)[\s]*<\/td>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        // Pattern 2: Same cell format
        if (preg_match('/<td[^>]*>[\s]*description[\s:]+(.+?)[\s]*<\/td>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        // Pattern 3: Text format
        if (preg_match('/description[\s:]+(.+?)(?:\n|$)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Normalize date format to Y-m-d
     */
    protected function normalizeDate(string $date): string
    {
        try {
            // Try common date formats
            $formats = ['d/m/Y', 'd-m-Y', 'm/d/Y', 'Y-m-d', 'd/m/y', 'd-m-y'];
            
            foreach ($formats as $format) {
                $parsed = \Carbon\Carbon::createFromFormat($format, $date);
                if ($parsed) {
                    return $parsed->format('Y-m-d');
                }
            }
            
            // Fallback to Carbon parse
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Failed to normalize date', ['date' => $date, 'error' => $e->getMessage()]);
            return $date; // Return as-is if parsing fails
        }
    }
}
