<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Services\PythonExtractionService;
use App\Services\DescriptionFieldExtractor;
use App\Services\MatchAttemptLogger;
use App\Services\TransactionLogService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentMatchingService
{
    protected PythonExtractionService $extractionService;
    protected DescriptionFieldExtractor $descriptionExtractor;
    protected MatchAttemptLogger $matchLogger;
    protected TransactionLogService $logService;

    public function __construct(?TransactionLogService $logService = null)
    {
        $this->extractionService = new PythonExtractionService();
        $this->descriptionExtractor = new DescriptionFieldExtractor();
        $this->matchLogger = new MatchAttemptLogger();
        $this->logService = $logService ?? new TransactionLogService();
    }

    /**
     * Extract payment information from email data
     * Returns: ['data' => [...], 'method' => '...'] or null
     */
    public function extractPaymentInfo(array $emailData): ?array
    {
        try {
            // Try Python extraction first
            $result = $this->extractionService->extractPaymentInfo($emailData);
            
            if ($result && isset($result['data'])) {
                // Also extract description field from text/html
                $textBody = $emailData['text'] ?? '';
                $htmlBody = $emailData['html'] ?? '';
                
                // Extract description field
                $descriptionField = $this->descriptionExtractor->extractFromHtml($htmlBody)
                    ?? $this->descriptionExtractor->extractFromText($textBody);
                
                if ($descriptionField) {
                    $parsed = $this->descriptionExtractor->parseDescriptionField($descriptionField);
                    
                    // Merge description field data into result
                    $result['data']['description_field'] = $descriptionField;
                    if ($parsed['account_number'] && !$result['data']['account_number']) {
                        $result['data']['account_number'] = $parsed['account_number'];
                    }
                    if ($parsed['payer_account_number'] && !isset($result['data']['payer_account_number'])) {
                        $result['data']['payer_account_number'] = $parsed['payer_account_number'];
                    }
                }
                
                return $result;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error extracting payment info', [
                'error' => $e->getMessage(),
                'email_id' => $emailData['processed_email_id'] ?? null,
            ]);
            return null;
        }
    }

    /**
     * Match an email to a payment
     * CRITICAL: Only matches emails received AFTER transaction creation
     * Matching order: 1. Amount, 2. Name, 3. Time (less strict if amount+name match)
     */
    public function matchEmail(array $emailData): ?Payment
    {
        try {
            // Extract payment info from email
            $extractionResult = $this->extractPaymentInfo($emailData);
            if (!$extractionResult || !isset($extractionResult['data'])) {
            return null;
        }

            $extractedInfo = $extractionResult['data'];
            $emailDate = isset($emailData['date']) ? Carbon::parse($emailData['date']) : null;
            
            if (!isset($extractedInfo['amount']) || $extractedInfo['amount'] <= 0) {
                return null;
            }
            
            // Get pending payments sorted by creation time (oldest first)
            // CRITICAL: Only check payments created BEFORE email was received
            $query = Payment::where('status', Payment::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            
            // CRITICAL: Email must be received AFTER transaction creation
            if ($emailDate) {
                $query->where('created_at', '<=', $emailDate);
            }
            
            // Filter by amount first (within 1 naira tolerance)
            $query->whereBetween('amount', [
                $extractedInfo['amount'] - 1,
                $extractedInfo['amount'] + 1
            ]);
            
            // Filter by email account if available
            if (isset($emailData['email_account_id'])) {
                $query->whereHas('business', function ($q) use ($emailData) {
                    $q->where('email_account_id', $emailData['email_account_id']);
                });
            }
            
            $potentialPayments = $query->orderBy('created_at', 'desc')->get();
            
            if ($potentialPayments->isEmpty()) {
                return null;
            }
            
            // Try to match each payment using proper matching order
            foreach ($potentialPayments as $payment) {
                $matchResult = $this->matchPayment($payment, $extractedInfo, $emailDate);
                
                // Log match attempt
                $this->matchLogger->logAttempt([
                    'payment_id' => $payment->id,
                    'processed_email_id' => $emailData['processed_email_id'] ?? null,
                    'matched' => $matchResult['matched'],
                    'reason' => $matchResult['reason'] ?? 'No reason',
                    'amount_match' => $matchResult['amount_match'] ?? false,
                    'name_match' => $matchResult['name_match'] ?? false,
                    'time_match' => $matchResult['time_match'] ?? false,
                    'account_match' => $matchResult['account_match'] ?? false,
                ]);
                
                if ($matchResult['matched']) {
                    // Store match result on payment for later use
                    $payment->setAttribute('_match_result', $matchResult);
                return $payment;
                }
            }

        return null;
            } catch (\Exception $e) {
            Log::error('Error matching email', [
                    'error' => $e->getMessage(),
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
            return null;
        }
    }

    /**
     * Match a payment to extracted email information
     * Matching order: 1. Amount (required), 2. Name, 3. Time (less strict if amount+name match)
     * 
     * @param Payment $payment
     * @param array $extractedInfo ['amount', 'sender_name', 'account_number', etc.]
     * @param Carbon|null $emailDate Email received date
     * @return array ['matched' => bool, 'reason' => string, 'amount_match' => bool, 'name_match' => bool, 'time_match' => bool]
     */
    public function matchPayment(Payment $payment, array $extractedInfo, ?Carbon $emailDate = null): array
    {
        $result = [
            'matched' => false,
            'reason' => '',
            'amount_match' => false,
            'name_match' => false,
            'time_match' => false,
            'account_match' => false,
            'name_mismatch' => false,
            'name_similarity_percent' => null,
            'is_mismatch' => false,
            'received_amount' => null,
            'mismatch_reason' => null,
        ];
        
        // STEP 1: Amount matching (REQUIRED - must match)
        $amountDiff = abs($payment->amount - ($extractedInfo['amount'] ?? 0));
        if ($amountDiff > 1) { // Allow 1 naira tolerance
            $result['reason'] = "Amount mismatch: payment={$payment->amount}, email=" . ($extractedInfo['amount'] ?? 0);
            return $result;
        }
        $result['amount_match'] = true;
        
        // STEP 2: Name matching (check if names match or are similar)
        $nameMatch = $this->matchNames($payment->payer_name, $extractedInfo['sender_name'] ?? null);
        $result['name_match'] = $nameMatch['matched'];
        $result['name_similarity_percent'] = $nameMatch['similarity'] ?? null;
        $result['name_mismatch'] = !$nameMatch['matched'];
        
        // STEP 3: Time matching (CRITICAL: Email must be received AFTER transaction creation)
        if ($emailDate) {
            // Email must be received after transaction creation
            if ($emailDate->lt($payment->created_at)) {
                $result['reason'] = "Email received before transaction creation: email={$emailDate->toDateTimeString()}, transaction={$payment->created_at->toDateTimeString()}";
                return $result;
            }
            
            // Calculate time difference
            $timeDiffMinutes = $payment->created_at->diffInMinutes($emailDate);
            
            // If amount and name match, be more lenient with time (up to 2 hours)
            // Otherwise, be stricter (up to 30 minutes)
            $maxTimeWindow = ($result['amount_match'] && $result['name_match']) ? 120 : 30;
            
            if ($timeDiffMinutes <= $maxTimeWindow) {
                $result['time_match'] = true;
            } else {
                // Time mismatch, but if amount+name match, we can still consider it
                if ($result['amount_match'] && $result['name_match']) {
                    $result['time_match'] = true; // Override: amount+name match is strong enough
            } else {
                    $result['reason'] = "Time window exceeded: {$timeDiffMinutes} minutes (max: {$maxTimeWindow})";
                    return $result;
                }
            }
        } else {
            // No email date - assume time match if amount matches
            $result['time_match'] = true;
        }
        
        // STEP 4: Account number matching (optional but helpful)
        if ($payment->account_number && isset($extractedInfo['account_number'])) {
            $result['account_match'] = ($payment->account_number === $extractedInfo['account_number']);
        }
        
        // Final decision: Match if amount matches AND (name matches OR account matches OR time is reasonable)
        // Priority: Amount (required) > Name > Time
        if ($result['amount_match']) {
            if ($result['name_match']) {
                // Amount + Name match = STRONG MATCH
                $result['matched'] = true;
                $result['reason'] = 'Matched: Amount and name match';
            } elseif ($result['account_match']) {
                // Amount + Account match = MATCH
                $result['matched'] = true;
                $result['reason'] = 'Matched: Amount and account number match';
            } elseif ($result['time_match']) {
                // Amount + Time match = WEAK MATCH (only if time is very close)
                if ($emailDate && $payment->created_at->diffInMinutes($emailDate) <= 15) {
                    $result['matched'] = true;
                    $result['reason'] = 'Matched: Amount and time match (name mismatch)';
                    $result['is_mismatch'] = true;
                    $result['mismatch_reason'] = 'Name mismatch but amount and time match';
                } else {
                    $result['reason'] = 'Amount matches but name and account do not match, and time window too large';
                }
            } else {
                $result['reason'] = 'Amount matches but name, account, and time do not match';
            }
        }
        
        // Store received amount for mismatch tracking
        if ($result['matched']) {
            $result['received_amount'] = $extractedInfo['amount'] ?? null;
        }
        
        return $result;
    }

    /**
     * Match names with fuzzy matching
     * Handles spacing variations, partial matches, etc.
     * Excludes bank names (GTBank, OPay, etc.) from matching
     */
    protected function matchNames(?string $paymentName, ?string $emailName): array
    {
        if (!$paymentName || !$emailName) {
            return ['matched' => false, 'similarity' => 0];
        }

        // Normalize names: lowercase, trim, remove extra spaces
        $paymentNameNorm = strtolower(trim(preg_replace('/\s+/', ' ', $paymentName)));
        $emailNameNorm = strtolower(trim(preg_replace('/\s+/', ' ', $emailName)));
        
        // Bank names list for exclusion
        $bankNames = [
            'gtbank', 'guaranty trust bank', 'guaranty trust', 'gt bank',
            'opay', 'opay digital', 'opay limited',
            'access bank', 'access',
            'zenith bank', 'zenith',
            'uba', 'united bank for africa',
            'first bank', 'firstbank', 'first bank of nigeria',
            'fidelity bank', 'fidelity',
            'union bank', 'union',
            'stanbic', 'stanbic ibtc',
            'ecobank', 'eco bank',
            'wema bank', 'wema',
            'sterling bank', 'sterling',
            'providus bank', 'providus',
            'kuda', 'kuda bank',
            'palmpay', 'palm pay',
            'carbon', 'carbon bank',
            'rubies', 'rubies bank',
            'vfd', 'vfd bank',
            'moniepoint', 'moniepoint bank',
            'xtrapay', 'xtra pay', 'xtrapay limited',
        ];
        
        // CRITICAL: Check if the name IS a bank name (not just contains bank name)
        // If the entire name is a bank name, exclude it (check BEFORE exact match)
        $paymentIsBankOnly = false;
        $emailIsBankOnly = false;
        
        foreach ($bankNames as $bankName) {
            // Check if the name equals the bank name (exact match)
            if ($paymentNameNorm === strtolower($bankName)) {
                $paymentIsBankOnly = true;
            }
            if ($emailNameNorm === strtolower($bankName)) {
                $emailIsBankOnly = true;
            }
        }
        
        // If the name IS a bank name (not just contains it), don't match
        if ($paymentIsBankOnly || $emailIsBankOnly) {
            return ['matched' => false, 'similarity' => 0, 'reason' => 'Name is a bank name'];
        }
        
        // Exact match (after bank name check)
        if ($paymentNameNorm === $emailNameNorm) {
            return ['matched' => true, 'similarity' => 100];
        }

        // CRITICAL: Check if expected name is contained in the other name
        // This handles cases like "john doe" vs "john doe from opay" or "john doe via gtbank"
        // Remove spaces for better matching
        $paymentNoSpace = str_replace(' ', '', $paymentNameNorm);
        $emailNoSpace = str_replace(' ', '', $emailNameNorm);
        
        // If payment name is contained in email name (or vice versa), it's a match
        // This allows "john doe" to match "john doe from opay" or "via gtbank john doe"
        // IMPORTANT: Only match if the contained name is at least 3 characters (to avoid false matches)
        if (strlen($paymentNoSpace) >= 3 && strlen($emailNoSpace) >= 3) {
            if (strpos($emailNoSpace, $paymentNoSpace) !== false) {
                // Payment name found in email name - MATCH (even if email has additional words like bank names)
                return ['matched' => true, 'similarity' => 90, 'reason' => 'Payment name contained in email name'];
            }
            if (strpos($paymentNoSpace, $emailNoSpace) !== false) {
                // Email name found in payment name - MATCH (even if payment has additional words)
                return ['matched' => true, 'similarity' => 90, 'reason' => 'Email name contained in payment name'];
            }
        }

        // Remove all spaces and compare
        $paymentNoSpace = str_replace(' ', '', $paymentNameNorm);
        $emailNoSpace = str_replace(' ', '', $emailNameNorm);
        
        if ($paymentNoSpace === $emailNoSpace) {
            return ['matched' => true, 'similarity' => 95];
        }
        
        // Check if one contains the other (partial match)
        if (strlen($paymentNoSpace) >= 3 && strlen($emailNoSpace) >= 3) {
            if (strpos($paymentNoSpace, $emailNoSpace) !== false || strpos($emailNoSpace, $paymentNoSpace) !== false) {
                return ['matched' => true, 'similarity' => 80];
            }
        }
        
        // Word-by-word matching (at least 2 words must match)
        $paymentWords = array_filter(explode(' ', $paymentNameNorm));
        $emailWords = array_filter(explode(' ', $emailNameNorm));
        
        if (count($paymentWords) >= 2 && count($emailWords) >= 2) {
            $matchingWords = array_intersect($paymentWords, $emailWords);
            if (count($matchingWords) >= 2) {
                return ['matched' => true, 'similarity' => 70];
            }
        }
        
        // Calculate similarity percentage using similar_text
        $similarity = 0;
        similar_text($paymentNameNorm, $emailNameNorm, $similarity);
        
        // Match if similarity is >= 70%
        if ($similarity >= 70) {
            return ['matched' => true, 'similarity' => round($similarity)];
        }
        
        return ['matched' => false, 'similarity' => round($similarity)];
    }

    /**
     * Extract missing data from text body
     */
    public function extractMissingFromTextBody(ProcessedEmail $email): bool
    {
        try {
            if (!$email->text_body) {
                return false;
            }
            
            $textBody = $email->text_body;
            $htmlBody = $email->html_body ?? '';
            
            $updated = false;
            
            // Extract description field if missing
            if (!$email->description_field) {
                $descriptionField = $this->descriptionExtractor->extractFromHtml($htmlBody)
                    ?? $this->descriptionExtractor->extractFromText($textBody);
                
                if ($descriptionField) {
                    $email->description_field = $descriptionField;
                    $updated = true;
                }
            }
            
            // Extract sender name if missing (using simple patterns)
            if (!$email->sender_name) {
                $senderName = $this->extractSenderNameFromText($textBody, $htmlBody);
                if ($senderName) {
                    $email->sender_name = $senderName;
                    $updated = true;
                }
            }
            
            if ($updated) {
                $email->save();
            }
            
            return $updated;
            } catch (\Exception $e) {
            Log::error('Error extracting from text body', [
                'email_id' => $email->id,
                    'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Advanced sender name extraction from text and HTML
     * Handles multiple formats including quoted-printable encoding
     */
    protected function extractSenderNameFromText(string $textBody, string $htmlBody): ?string
    {
        // Decode quoted-printable encoding first
        $textBody = $this->decodeQuotedPrintable($textBody);
        $htmlBody = $this->decodeQuotedPrintable($htmlBody);
        
        // Try HTML first (more structured)
        if (!empty($htmlBody)) {
            $htmlName = $this->extractFromHtml($htmlBody);
            if ($htmlName) {
                return $htmlName;
            }
        }
        
        // Then try text body
        if (!empty($textBody)) {
            $textName = $this->extractFromText($textBody);
            if ($textName) {
                return $textName;
            }
        }
        
        // Fallback: combine both
        $htmlText = strip_tags($htmlBody);
        $combined = $htmlText . ' ' . $textBody;
        
        // Normalize whitespace
        $combined = preg_replace('/\s+/', ' ', $combined);
        
        return $this->extractFromText($combined);
    }
    
    /**
     * Extract from HTML content
     */
    protected function extractFromHtml(string $html): ?string
    {
        // Try HTML table patterns first
        $htmlPatterns = [
            // Pattern: <td>Description:</td><td>FROM NAME TO</td>
            '/<td[^>]*>[\s]*(?:description|remarks|from)[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s\-]{2,50}?)\s+to/i',
            // Pattern: <td>FROM NAME TO</td>
            '/<td[^>]*>[\s]*from\s+([A-Z][A-Z\s\-]{2,50}?)\s+to/i',
            // Pattern: Description: ... FROM NAME
            '/description[\s:]+.*?from\s+([A-Z][A-Z\s\-]{2,50}?)(?:\s+to|\s*<\/td>)/i',
        ];
        
        foreach ($htmlPatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $name = trim($matches[1]);
                $name = $this->cleanExtractedName($name);
                if ($this->isValidExtractedName($name)) {
                    return $name;
                }
            }
        }
        
        // Strip HTML and try text patterns
        $text = strip_tags($html);
        return $this->extractFromText($text);
    }
    
    /**
     * Extract from text content
     */
    protected function extractFromText(string $text): ?string
    {
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Try patterns in priority order (most specific first)
        $patterns = [
            // Pattern 1: "TRANSFER FROM: NAME TO ..." format
            '/transfer\s+from[\s:]+([A-Z][A-Z\s\-]{3,50}?)\s+to/i',
            
            // Pattern 2: "Description : CODE-TRANSFER FROM: NAME TO ..."
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TRANSFER\s+FROM[\s:]+([A-Z][A-Z\s\-]{3,50}?)\s+TO/i',
            
            // Pattern 3: "TRANSFER FROM NAME-OPAY-..." or "TRANSFER FROM NAME-BANK-..."
            '/transfer\s+from\s+([A-Z][A-Z\s\-]{3,50}?)(?:\s*-\s*(?:OPAY|GTBANK|ACCESS|ZENITH|UBA|FIRST\s+BANK|INNOCE))/i',
            
            // Pattern 4: "Description : CODE-TRANSFER FROM NAME-..."
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TRANSFER\s+FROM\s+([A-Z][A-Z\s\-]{3,50}?)(?:\s*-\s*(?:OPAY|GTBANK|ACCESS|ZENITH|UBA|FIRST\s+BANK|INNOCE))/i',
            
            // Pattern 5: "Description : CODE-TXN-CODE-CODE-NAME" format (handles quoted-printable)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TXN-[\d\-]+-[A-Z]+=\s*[A-Z\-]*-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount)/i',
            
            // Pattern 6: "Description : CODE-BBBB-KMB-NAME" format (handles quoted-printable)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-BBBB-KMB-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*\.)/i',
            
            // Pattern 7: "Description : CODE-CODE-CODE-NAME" format (generic pattern)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-[A-Z]+-[A-Z]+-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*\.)/i',
            
            // Pattern 8: "Description : CODE-NAME = PHONE-BANK" format (name before phone number)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-([A-Z][A-Z\s\-,]{3,50}?)\s*=\s*[\d]+-[A-Z]+/i',
            
            // Pattern 9: "Description : CODE-NAME-BANK-NAME" format (extract first name before bank)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-([A-Z][A-Z\s\-,]{3,50}?)-(?:OPAY|GTBANK|ACCESS|ZENITH|UBA|FIRST\s+BANK|PALMPAY|KUD|BBBB|KMB)/i',
            
            // Pattern 10: "Remarks : NAME" (extract name from remarks field)
            '/remark[s]?[\s:]+([A-Z][A-Z\s\-,]{3,50}?)(?:\s*\||\s*$|\s*Time)/i',
            
            // Pattern 3: "FROM OPAY/ NAME" or "FROM BANK/ NAME"
            '/from\s+(?:[A-Z]+\/|OPAY\/|GTBANK\/|ACCESS\/|ZENITH\/|UBA\/|FIRST\s+BANK\/)\s*([A-Z][A-Z\s\-]{3,50}?)(?:\s*\/|\s*Support|\s*\||\s*$)/i',
            
            // Pattern 4: "received from NAME"
            '/received\s+from\s+([A-Z][A-Z\s\-]{3,50}?)(?:\s*\||\s*$)/i',
            
            // Pattern 5: "FROM NAME TO" (standard format)
            '/from\s+([A-Z][A-Z\s\-]{3,50}?)\s+to/i',
            
            // Pattern 6: "TRANSFER FROM NAME" (general)
            '/transfer\s+from\s+([A-Z][A-Z\s\-]{3,50}?)(?:\s+to|\s+account|\s*$)/i',
            
            // Pattern 7: "Remarks : ... NAME" (extract name from remarks)
            '/remark[s]?[\s:]+(?:NT|FROM|TRANSFER\s+FROM)?\s*([A-Z][A-Z\s\-]{3,50}?)(?:\s*\||\s*$)/i',
            
            // Pattern 8: Description field with code-name format "CODE-NAME TRF"
            '/description[\s:]+(?:=\d+)?\s*(?:[\d\-\s]+-)?([A-Z][A-Z\s\-]{3,50}?)\s+(?:TRF|TRANSFER|FOR|TO)/i',
            
            // Pattern 9: Direct "CODE-NAME TRF" format
            '/(?:[\d\-]+=?\d*\s*-)\s*([A-Z][A-Z\s\-]{3,50}?)\s+(?:TRF|TRANSFER|FOR|TO)/i',
            
            // Pattern 10: "Sender: NAME" or "Sender NAME"
            '/sender[:\s]+([A-Z][A-Z\s\-]{3,50}?)(?:\s|$)/i',
            
            // Pattern 11: "Payer: NAME" or "Payer NAME"
            '/payer[:\s]+([A-Z][A-Z\s\-]{3,50}?)(?:\s|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                
                // Clean up the name
                $name = $this->cleanExtractedName($name);
                
                // Validate name
                if ($this->isValidExtractedName($name)) {
                    return $name;
                }
            }
        }
        
            return null;
    }
    
    /**
     * Decode quoted-printable encoding
     */
    protected function decodeQuotedPrintable(string $text): string
    {
        // Decode quoted-printable encoding (=XX format)
        $text = quoted_printable_decode($text);
        
        // Also handle =20 (space) and other common encodings
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Handle =E2=80=AF (non-breaking space) and similar
        $text = preg_replace('/=E2=80=AF/', ' ', $text);
        $text = preg_replace('/=C2=A0/', ' ', $text);
        
        return $text;
    }

    /**
     * Clean extracted name
     */
    protected function cleanExtractedName(string $name): string
    {
        // Remove leading/trailing dashes, numbers, and special chars
        $name = preg_replace('/^[\d\-\s\/\|]+/', '', $name);
        $name = preg_replace('/[\d\-\s\/\|]+$/', '', $name);
        
        // Remove quoted-printable artifacts
        $name = preg_replace('/=\d+/', '', $name);
        
        // Remove email addresses
        $name = preg_replace('/\S+@\S+/', '', $name);
        
        // Remove account numbers (long digit sequences)
        $name = preg_replace('/\d{10,}/', '', $name);
        
        // Clean up multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);
        
        return trim($name);
    }
    
    /**
     * Validate extracted name
     */
    protected function isValidExtractedName(?string $name): bool
    {
        if (!$name || strlen($name) < 3 || strlen($name) > 100) {
            return false;
        }
        
        // Must not be an email address
        if (preg_match('/@/', $name)) {
            return false;
        }
        
        // Must contain at least one letter
        if (!preg_match('/[A-Za-z]/', $name)) {
            return false;
        }
        
        // Must not be just numbers or special chars
        if (preg_match('/^[\d\s\-\/\|]+$/', $name)) {
            return false;
        }
        
        // Must not be common invalid patterns
        $invalidPatterns = [
            '/^(OPAY|GTBANK|ACCESS|ZENITH|UBA|FIRST\s+BANK|WEB|LOCATION|ACCOUNT|NUMBER)$/i',
            '/^(SUPPORT|TRANSFER|TRF|FROM|TO|DESCRIPTION|REMARK)$/i',
            '/thank\s+you\s+for\s+choosing/i',
            '/guaranty\s+trust\s+bank/i',
            '/limited$/i',
            '/^thank\s+you/i',
            '/regards/i',
        ];
        
        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return false;
            }
        }
        
        // Must not be too long (likely not a name)
        if (strlen($name) > 60) {
            return false;
        }
        
        return true;
    }

    /**
     * Match payment to stored emails
     */
    public function matchPaymentToStoredEmail(Payment $payment): ?ProcessedEmail
    {
        try {
            // Get unmatched emails with matching amount
            // CRITICAL: Only check emails received AFTER transaction creation
            $query = ProcessedEmail::where('is_matched', false)
                ->where('amount', $payment->amount)
                ->where('email_date', '>=', $payment->created_at); // Email must be AFTER transaction
            
            // Filter by email account if business has one
            if ($payment->business_id && $payment->business->email_account_id) {
                $query->where('email_account_id', $payment->business->email_account_id);
            }
            
            $potentialEmails = $query->orderBy('email_date', 'asc')->get();
            
            foreach ($potentialEmails as $email) {
                $emailData = [
                    'subject' => $email->subject,
                    'from' => $email->from_email,
                    'text' => $email->text_body ?? '',
                    'html' => $email->html_body ?? '',
                    'date' => $email->email_date ? $email->email_date->toDateTimeString() : null,
                    'email_account_id' => $email->email_account_id,
                    'processed_email_id' => $email->id,
                ];
                
                $extractionResult = $this->extractPaymentInfo($emailData);
                if (!$extractionResult || !isset($extractionResult['data'])) {
                    continue;
                }
                
                $extractedInfo = $extractionResult['data'];
                $emailDate = $email->email_date ? Carbon::parse($email->email_date) : null;
                $matchResult = $this->matchPayment($payment, $extractedInfo, $emailDate);
                
                if ($matchResult['matched']) {
                    return $email;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error matching payment to stored email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
