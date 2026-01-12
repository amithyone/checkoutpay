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
     */
    protected function matchNames(?string $paymentName, ?string $emailName): array
    {
        if (!$paymentName || !$emailName) {
            return ['matched' => false, 'similarity' => 0];
        }
        
        // Normalize names: lowercase, trim, remove extra spaces
        $paymentNameNorm = strtolower(trim(preg_replace('/\s+/', ' ', $paymentName)));
        $emailNameNorm = strtolower(trim(preg_replace('/\s+/', ' ', $emailName)));
        
        // Exact match
        if ($paymentNameNorm === $emailNameNorm) {
            return ['matched' => true, 'similarity' => 100];
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
     * Simple sender name extraction from text
     */
    protected function extractSenderNameFromText(string $textBody, string $htmlBody): ?string
    {
        $combined = strip_tags($htmlBody) . ' ' . $textBody;
        
        // Try common patterns
        $patterns = [
            '/from\s+([A-Z][A-Z\s]{2,30}?)(?:\s+to|\s+account|$)/i',
            '/transfer\s+from\s+([A-Z][A-Z\s]{2,30}?)(?:\s+to|\s+account|$)/i',
            '/sender[:\s]+([A-Z][A-Z\s]{2,30}?)(?:\s|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                $name = trim($matches[1]);
                if (strlen($name) >= 3 && strlen($name) <= 50 && !preg_match('/@/', $name)) {
                    return $name;
                }
            }
        }
        
        return null;
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
                $matchResult = $this->matchPayment($payment, $extractedInfo, $email->email_date);
                
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
