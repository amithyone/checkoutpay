<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\TransactionLogService;
use Illuminate\Support\Collection;

class PaymentMatchingService
{
    /**
     * Match email data against pending payments
     */
    public function matchEmail(array $emailData): ?Payment
    {
        $extractedInfo = $this->extractPaymentInfo($emailData);

        if (!$extractedInfo) {
            \Illuminate\Support\Facades\Log::info('Could not extract payment info from email');
            return null;
        }

        \Illuminate\Support\Facades\Log::info('Extracted payment info from email', $extractedInfo);

        // Check for duplicate payments first
        $duplicate = $this->checkDuplicate($extractedInfo);
        if ($duplicate) {
            \Illuminate\Support\Facades\Log::warning('Duplicate payment detected', [
                'existing_transaction_id' => $duplicate->transaction_id,
                'extracted_info' => $extractedInfo,
            ]);
            return null; // Don't process duplicates
        }

        // Also check stored emails in database for matching
        $payment = $this->matchFromStoredEmails($extractedInfo, $emailData['email_account_id'] ?? null);
        if ($payment) {
            return $payment;
        }

        // Get pending payments
        // Logic:
        // - If business HAS email account assigned → only match if email came from that account
        // - If business has NO email account → match from ANY email account
        $query = Payment::pending();
        
        // If email_account_id is provided, match payments from:
        // 1. Businesses using this email account
        // 2. Businesses with NO email account (null email_account_id)
        if (!empty($emailData['email_account_id'])) {
            $query->where(function ($q) use ($emailData) {
                // Businesses assigned to this email account
                $q->whereHas('business', function ($businessQuery) use ($emailData) {
                    $businessQuery->where('email_account_id', $emailData['email_account_id']);
                })
                // OR businesses with NO email account (can receive from any email)
                ->orWhereHas('business', function ($businessQuery) {
                    $businessQuery->whereNull('email_account_id');
                })
                // OR payments without business (fallback)
                ->orWhereNull('business_id');
            });
        } else {
            // If no email_account_id provided, check all payments
            // This handles the fallback .env email account case
        }
        
        $pendingPayments = $query->get();

        foreach ($pendingPayments as $payment) {
            $match = $this->matchPayment($payment, $extractedInfo);

            if ($match['matched']) {
                \Illuminate\Support\Facades\Log::info('Payment matched', [
                    'transaction_id' => $payment->transaction_id,
                    'match_reason' => $match['reason'],
                ]);

                return $payment;
            } else {
                \Illuminate\Support\Facades\Log::debug('Payment mismatch', [
                    'transaction_id' => $payment->transaction_id,
                    'reason' => $match['reason'],
                ]);
            }
        }

        \Illuminate\Support\Facades\Log::info('No matching payment found for email');
        return null;
    }

    /**
     * Extract payment information from email
     */
    public function extractPaymentInfo(array $emailData): ?array
    {
        $subject = strtolower($emailData['subject'] ?? '');
        $text = $emailData['text'] ?? '';
        $html = $emailData['html'] ?? '';
        $from = strtolower($emailData['from'] ?? '');

        // If text body is empty but HTML exists, extract text from HTML intelligently
        if (empty(trim($text)) && !empty($html)) {
            // Convert HTML to plain text, preserving structure
            $text = $this->htmlToText($html);
        }
        
        $text = strtolower($text);
        $htmlLower = strtolower($html);
        
        // Use HTML directly for pattern matching (more accurate for structured emails)
        // Also use extracted text as fallback
        $fullText = $subject . ' ' . $text . ' ' . $htmlLower;

        // Extract amount - look for currency patterns
        // Updated to handle formats like "NGN 1000", "Amount: NGN 1000", etc.
        $amountPatterns = [
            '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*ngn\s*([\d,]+\.?\d*)/i',
            '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*naira\s*([\d,]+\.?\d*)/i',
            '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*[₦$]?\s*([\d,]+\.?\d*)/i',
            '/ngn\s*([\d,]+\.?\d*)/i',
            '/naira\s*([\d,]+\.?\d*)/i',
            '/[₦$]\s*([\d,]+\.?\d*)/i',
            '/([\d,]+\.?\d*)\s*(?:naira|ngn|usd|dollar)/i',
            '/([\d,]+\.?\d*)/i',
        ];

        $amount = null;
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                if ($amount > 0) {
                    break;
                }
            }
        }

        // Extract sender name
        // Updated to handle formats like "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        // Also check HTML for structured name fields
        $namePatterns = [
            // GTBank format: "FROM SOLOMON INNOCENT AMITHY TO SQUA"
            '/from\s+([A-Z][A-Z\s]+?)\s+to/i',
            // HTML table patterns
            '/<td[^>]*>[\s]*(?:from|sender|payer|depositor|account\s*name|name)[\s:]*<\/td>\s*<td[^>]*>[\s]*([A-Z][A-Z\s]+?)[\s]*<\/td>/i',
            // Standard patterns
            '/(?:from|sender|payer|depositor|account\s*name|name)[\s:]*([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i',
            '/(?:credited\s+by|from)\s+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i',
            // Fallback: any capitalized name pattern
            '/([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/',
        ];

        $senderName = null;
        // Try HTML first (more structured)
        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $htmlLower, $matches)) {
                $senderName = trim(strtolower($matches[1]));
                if (strlen($senderName) > 2) { // Valid name
                    break;
                }
            }
        }
        
        // If not found in HTML, try full text
        if (!$senderName) {
            foreach ($namePatterns as $pattern) {
                if (preg_match($pattern, $fullText, $matches)) {
                    $senderName = trim(strtolower($matches[1]));
                    if (strlen($senderName) > 2) {
                        break;
                    }
                }
            }
        }

        // If no name found in body, try extracting from email sender
        if (!$senderName && $from) {
            if (preg_match('/([^<]+)/', $from, $matches)) {
                $senderName = trim(strtolower($matches[1]));
            }
        }

        if (!$amount) {
            return null;
        }

        return [
            'amount' => $amount,
            'sender_name' => $senderName,
            'email_subject' => $emailData['subject'] ?? '',
            'email_from' => $emailData['from'] ?? '',
            'extracted_at' => now()->toISOString(),
        ];
    }

    /**
     * Match payment with extracted email info
     */
    public function matchPayment(Payment $payment, array $extractedInfo, ?\DateTime $emailDate = null): array
    {
        // Check time window: email must be received within 15 minutes of transaction creation
        if ($emailDate && $payment->created_at) {
            $timeDiff = abs($payment->created_at->diffInMinutes($emailDate));
            if ($timeDiff > 15) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Time window exceeded: email received %d minutes after transaction (max 15 minutes)',
                        $timeDiff
                    ),
                ];
            }
        }

        // Check amount match (allow small tolerance for rounding)
        $amountDiff = abs($payment->amount - $extractedInfo['amount']);
        $amountTolerance = 0.01; // 1 kobo tolerance

        if ($amountDiff > $amountTolerance) {
            return [
                'matched' => false,
                'reason' => sprintf(
                    'Amount mismatch: expected %s, got %s',
                    $payment->amount,
                    $extractedInfo['amount']
                ),
            ];
        }

        // If payer name is provided, check similarity (not exact match)
        if ($payment->payer_name) {
            if (empty($extractedInfo['sender_name'])) {
                return [
                    'matched' => false,
                    'reason' => 'Payer name required but not found in email',
                ];
            }

            // Normalize names for comparison
            $expectedName = trim(strtolower($payment->payer_name));
            $expectedName = preg_replace('/\s+/', ' ', $expectedName);
            $receivedName = trim(strtolower($extractedInfo['sender_name']));
            $receivedName = preg_replace('/\s+/', ' ', $receivedName);

            // Check if names match with similarity (handles order variations and partial matches)
            if (!$this->namesMatch($expectedName, $receivedName)) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Name mismatch: expected "%s", got "%s"',
                        $expectedName,
                        $receivedName
                    ),
                ];
            }
        }

        return [
            'matched' => true,
            'reason' => 'Amount and name match within time window',
        ];
    }

    /**
     * Check for duplicate payments
     */
    protected function checkDuplicate(array $extractedInfo): ?Payment
    {
        // Check for payments with same amount and payer name in last 1 hour
        $duplicateWindow = now()->subHour();

        $duplicate = Payment::where('amount', $extractedInfo['amount'])
            ->where('payer_name', $extractedInfo['sender_name'] ?? null)
            ->where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', $duplicateWindow)
            ->first();

        return $duplicate;
    }

    /**
     * Check if two names match, handling order variations and partial matches
     * 
     * Examples:
     * - "innocent solomon" matches "solomon innocent amithy" (all words present)
     * - "solomon innocent" matches "innocent solomon" (order variation)
     * - "john doe" matches "john doe smith" (partial match)
     * 
     * @param string $expectedName The name from payment request
     * @param string $receivedName The name extracted from email
     * @return bool
     */
    protected function namesMatch(string $expectedName, string $receivedName): bool
    {
        // Exact match
        if ($expectedName === $receivedName) {
            return true;
        }

        // Split names into words
        $expectedWords = array_filter(explode(' ', $expectedName));
        $receivedWords = array_filter(explode(' ', $receivedName));

        // If either is empty, no match
        if (empty($expectedWords) || empty($receivedWords)) {
            return false;
        }

        // Check if all words from expected name exist in received name
        // This handles cases like "innocent solomon" matching "solomon innocent amithy"
        $allWordsFound = true;
        foreach ($expectedWords as $word) {
            $wordFound = false;
            foreach ($receivedWords as $receivedWord) {
                // Exact word match
                if ($word === $receivedWord) {
                    $wordFound = true;
                    break;
                }
                // Partial match (word is contained in received word or vice versa)
                // Handles cases like "solomon" matching "solomon" in "solomon innocent"
                if (strpos($receivedWord, $word) !== false || strpos($word, $receivedWord) !== false) {
                    $wordFound = true;
                    break;
                }
            }
            if (!$wordFound) {
                $allWordsFound = false;
                break;
            }
        }

        // Also check reverse: all words from received name exist in expected name
        // This handles cases where received name has fewer words
        $allReceivedWordsFound = true;
        foreach ($receivedWords as $receivedWord) {
            $wordFound = false;
            foreach ($expectedWords as $word) {
                if ($receivedWord === $word) {
                    $wordFound = true;
                    break;
                }
                if (strpos($receivedWord, $word) !== false || strpos($word, $receivedWord) !== false) {
                    $wordFound = true;
                    break;
                }
            }
            if (!$wordFound) {
                $allReceivedWordsFound = false;
                break;
            }
        }

        // Match if all expected words are found in received name
        // OR if all received words are found in expected name (handles shorter names)
        return $allWordsFound || $allReceivedWordsFound;
    }

    /**
     * Match payment from stored emails in database
     */
    protected function matchFromStoredEmails(array $extractedInfo, ?int $emailAccountId): ?Payment
    {
        // Get pending payments
        $query = Payment::pending();
        
        // Filter by email account if provided
        if ($emailAccountId) {
            $query->where(function ($q) use ($emailAccountId) {
                $q->whereHas('business', function ($businessQuery) use ($emailAccountId) {
                    $businessQuery->where('email_account_id', $emailAccountId);
                })
                ->orWhereHas('business', function ($businessQuery) {
                    $businessQuery->whereNull('email_account_id');
                })
                ->orWhereNull('business_id');
            });
        }
        
        $pendingPayments = $query->get();
        
        if ($pendingPayments->isEmpty()) {
            return null;
        }
        
        // Check stored emails for matching amount and name
        $storedEmails = \App\Models\ProcessedEmail::unmatched()
            ->withAmount($extractedInfo['amount'])
            ->when($emailAccountId, function ($q) use ($emailAccountId) {
                $q->where('email_account_id', $emailAccountId);
            })
            ->get();
        
        foreach ($storedEmails as $storedEmail) {
            foreach ($pendingPayments as $payment) {
                $match = $this->matchPayment($payment, [
                    'amount' => $storedEmail->amount,
                    'sender_name' => $storedEmail->sender_name,
                ]);
                
                if ($match['matched']) {
                    // Mark stored email as matched
                    $storedEmail->markAsMatched($payment);
                    
                    \Illuminate\Support\Facades\Log::info('Payment matched from stored email', [
                        'transaction_id' => $payment->transaction_id,
                        'stored_email_id' => $storedEmail->id,
                        'match_reason' => $match['reason'],
                    ]);
                    
                    return $payment;
                }
            }
        }
        
        return null;
    }

    /**
     * Convert HTML to plain text while preserving important structure
     * Handles tables, divs, and other HTML elements banks use
     */
    protected function htmlToText(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Convert common HTML elements to text with spacing
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/td>/i', ' ', $html);
        $html = preg_replace('/<\/tr>/i', "\n", $html);
        $html = preg_replace('/<\/th>/i', ' ', $html);
        $html = preg_replace('/<\/li>/i', "\n", $html);
        
        // Remove all remaining HTML tags
        $text = strip_tags($html);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        $text = trim($text);
        
        return $text;
    }
}
