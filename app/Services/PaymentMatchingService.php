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

        // Extract amount - prioritize HTML table structures, then text patterns
        // First, try to find amount in HTML table cells (most reliable)
        $amount = null;
        
        // Pattern 1: HTML table with "Amount" label
        if (preg_match('/<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]*<\/td>\s*<td[^>]*>[\s]*ngn\s*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
        }
        // Pattern 2: HTML table with amount value (no label)
        elseif (preg_match('/<td[^>]*>[\s]*ngn\s*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
        }
        // Pattern 3: HTML with "Amount : NGN 1000" format
        elseif (preg_match('/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*ngn\s*([\d,]+\.?\d*)/i', $html, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
        }
        // Pattern 4: Text patterns (more specific, avoid small numbers)
        else {
            $amountPatterns = [
                '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*ngn\s*([\d,]+\.?\d*)/i',
                '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*naira\s*([\d,]+\.?\d*)/i',
                '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*[₦$]\s*([\d,]+\.?\d*)/i',
                '/ngn\s*([\d,]+\.?\d*)/i',
                '/naira\s*([\d,]+\.?\d*)/i',
                '/[₦$]\s*([\d,]+\.?\d*)/i',
                '/([\d,]+\.?\d*)\s*(?:naira|ngn|usd|dollar)/i',
            ];

            foreach ($amountPatterns as $pattern) {
                if (preg_match($pattern, $fullText, $matches)) {
                    $potentialAmount = (float) str_replace(',', '', $matches[1]);
                    // Only accept amounts >= 10 to avoid matching dates/times (e.g., "4:50:06 PM" or "2024")
                    if ($potentialAmount >= 10) {
                        $amount = $potentialAmount;
                        break;
                    }
                }
            }
        }

        // Extract sender name - GTBank stores name in Description field
        // GTBank format: Description field contains "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        $senderName = null;
        
        // Pattern 1: GTBank HTML table - Description field contains sender name
        // Format: <td>Description</td><td>FROM SOLOMON INNOCENT AMITHY TO SQUA</td>
        if (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 2: GTBank HTML table - Description in same cell
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]+from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 3: GTBank text format "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        elseif (preg_match('/from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 4: HTML table with "From" or "Sender" label
        elseif (preg_match('/<td[^>]*>[\s]*(?:from|sender|payer|depositor|account\s*name|name)[\s:]*<\/td>\s*<td[^>]*>[\s]*([A-Z][A-Z\s]+?)[\s]*<\/td>/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 5: Standard patterns in HTML
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|<\/)/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 6: Try in text (fallback)
        elseif (preg_match('/from\s+([A-Z][A-Z\s]+?)\s+to/i', $fullText, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 7: Other standard patterns in text
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i', $fullText, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        
        // Clean up sender name (remove extra spaces, validate length)
        if ($senderName) {
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            if (strlen($senderName) < 3) {
                $senderName = null; // Too short, invalid
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
        // Check time window: email must be received AFTER transaction creation and within configured minutes
        // Get time window from settings (default: 120 minutes / 2 hours)
        $timeWindowMinutes = \App\Models\Setting::get('payment_time_window_minutes', 120);
        
        // Ensure both dates are in the same timezone (Africa/Lagos) for accurate comparison
        if ($emailDate && $payment->created_at) {
            // Convert both to Carbon instances and set to app timezone
            $paymentTime = \Carbon\Carbon::parse($payment->created_at)->setTimezone(config('app.timezone'));
            $emailTime = \Carbon\Carbon::parse($emailDate)->setTimezone(config('app.timezone'));
            
            // Reject emails that arrived BEFORE the transaction was created
            if ($emailTime->lt($paymentTime)) {
                $timeDiff = abs($paymentTime->diffInMinutes($emailTime));
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Email received BEFORE transaction was created (%d minutes before). Payment: %s, Email: %s',
                        $timeDiff,
                        $paymentTime->format('Y-m-d H:i:s T'),
                        $emailTime->format('Y-m-d H:i:s T')
                    ),
                ];
            }
            
            // Check if email arrived within the time window AFTER transaction creation
            $timeDiff = $paymentTime->diffInMinutes($emailTime);
            
            if ($timeDiff > $timeWindowMinutes) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Time window exceeded: email received %d minutes after transaction (max %d minutes). Payment: %s, Email: %s',
                        $timeDiff,
                        $timeWindowMinutes,
                        $paymentTime->format('Y-m-d H:i:s T'),
                        $emailTime->format('Y-m-d H:i:s T')
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
     * Check if two names match with 70% similarity
     * 
     * Examples:
     * - "amithy one media" matches "amithy one" (2 out of 3 words = 67%, but we check if major words match)
     * - "innocent solomon" matches "solomon innocent amithy" (all words present)
     * - "solomon innocent" matches "innocent solomon" (order variation)
     * 
     * @param string $expectedName The name from payment request (e.g., "amithy one media")
     * @param string $receivedName The name extracted from email (e.g., "amithy one" or longer description)
     * @return bool True if at least 70% of words match
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

        // Count how many words from expected name are found in received name
        $matchedWords = 0;
        foreach ($expectedWords as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            
            foreach ($receivedWords as $receivedWord) {
                $receivedWord = trim($receivedWord);
                if (empty($receivedWord)) continue;
                
                // Exact word match
                if (strtolower($word) === strtolower($receivedWord)) {
                    $matchedWords++;
                    break;
                }
                // Partial match (word is contained in received word or vice versa)
                // Handles cases like "amithy" matching "amithy" in "amithy one"
                if (stripos($receivedWord, $word) !== false || stripos($word, $receivedWord) !== false) {
                    $matchedWords++;
                    break;
                }
            }
        }

        // Calculate similarity percentage
        $totalExpectedWords = count($expectedWords);
        $similarityPercent = ($matchedWords / $totalExpectedWords) * 100;

        // Match if at least 70% of words match
        // Example: "amithy one media" (3 words) needs at least 2 words to match (67% rounds to 70%)
        return $similarityPercent >= 70;
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
                // Re-extract from html_body if available
                $emailData = [
                    'subject' => $storedEmail->subject,
                    'from' => $storedEmail->from_email,
                    'text' => $storedEmail->text_body ?? '',
                    'html' => $storedEmail->html_body ?? '', // Use html_body for matching
                    'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                ];
                
                // Re-extract payment info (will use html_body)
                $extractedInfo = $this->extractPaymentInfo($emailData);
                
                if (!$extractedInfo || !$extractedInfo['amount']) {
                    continue;
                }
                
                $match = $this->matchPayment($payment, $extractedInfo, $storedEmail->email_date);
                
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
     * Re-check a stored email against pending payments
     * Used for manual matching from admin panel
     */
    public function recheckStoredEmail(\App\Models\ProcessedEmail $storedEmail): array
    {
        // Re-extract payment info from html_body
        $emailData = [
            'subject' => $storedEmail->subject,
            'from' => $storedEmail->from_email,
            'text' => $storedEmail->text_body ?? '',
            'html' => $storedEmail->html_body ?? '', // Prioritize html_body
            'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
        ];
        
        $extractedInfo = $this->extractPaymentInfo($emailData);
        
        if (!$extractedInfo || !$extractedInfo['amount']) {
            return [
                'success' => false,
                'message' => 'Could not extract payment information from email',
                'matches' => [],
            ];
        }
        
        // Get pending payments
        $query = Payment::pending();
        
        // Filter by email account if email has one
        if ($storedEmail->email_account_id) {
            $query->where(function ($q) use ($storedEmail) {
                $q->whereHas('business', function ($businessQuery) use ($storedEmail) {
                    $businessQuery->where('email_account_id', $storedEmail->email_account_id);
                })
                ->orWhereHas('business', function ($businessQuery) {
                    $businessQuery->whereNull('email_account_id');
                })
                ->orWhereNull('business_id');
            });
        }
        
        $pendingPayments = $query->get();
        $matches = [];
        
        foreach ($pendingPayments as $payment) {
            $match = $this->matchPayment($payment, $extractedInfo, $storedEmail->email_date);
            
            $matches[] = [
                'payment' => $payment,
                'matched' => $match['matched'],
                'reason' => $match['reason'],
                'transaction_id' => $payment->transaction_id,
                'expected_amount' => $payment->amount,
                'extracted_amount' => $extractedInfo['amount'],
                'expected_name' => $payment->payer_name,
                'extracted_name' => $extractedInfo['sender_name'] ?? null,
                'time_diff_minutes' => $storedEmail->email_date ? abs(
                    \Carbon\Carbon::parse($payment->created_at)->setTimezone(config('app.timezone'))
                        ->diffInMinutes(\Carbon\Carbon::parse($storedEmail->email_date)->setTimezone(config('app.timezone')))
                ) : null,
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Re-check completed',
            'extracted_info' => $extractedInfo,
            'matches' => $matches,
        ];
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
