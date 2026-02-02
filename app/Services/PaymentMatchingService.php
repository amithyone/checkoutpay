<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Services\DescriptionFieldExtractor;
use App\Services\MatchAttemptLogger;
use App\Services\TransactionLogService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentMatchingService
{
    protected DescriptionFieldExtractor $descriptionExtractor;
    protected MatchAttemptLogger $matchLogger;
    protected TransactionLogService $logService;

    public function __construct(?TransactionLogService $logService = null)
    {
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
            $textBody = $emailData['text'] ?? '';
            $htmlBody = $emailData['html'] ?? '';
            $result = null;
            
            // PRIORITY 1: Try PHP extraction first
            if (!empty($textBody)) {
                // Decode quoted-printable encoding before passing to extractFromTextBody
                $decodedTextBody = preg_replace('/=20/', ' ', $textBody);
                $decodedTextBody = preg_replace('/=3D/', '=', $decodedTextBody);
                $decodedTextBody = html_entity_decode($decodedTextBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $decodedTextBody = strip_tags($decodedTextBody);
                $decodedTextBody = preg_replace('/\s+/', ' ', $decodedTextBody);
                
                $emailExtractor = new \App\Services\EmailExtractionService();
                $phpResult = $emailExtractor->extractFromTextBody(
                    $decodedTextBody,
                    $emailData['subject'] ?? '',
                    $emailData['from'] ?? '',
                    $emailData['date'] ?? null
                );
                
                if ($phpResult) {
                    $result = [
                        'data' => $phpResult,
                        'method' => 'php'
                    ];
                }
            }
            
            // PRIORITY 2: If PHP didn't extract name or name is invalid, try direct extraction
            $extractedSenderName = $result['data']['sender_name'] ?? null;
            $needsDirectExtraction = !$result || empty($extractedSenderName) || !$this->isValidExtractedName($extractedSenderName);
            
            if ($needsDirectExtraction) {
                $nameExtractor = new \App\Services\SenderNameExtractor();
                $directName = null;
                
                // Try HTML first
                if (!empty($htmlBody)) {
                    $directName = $nameExtractor->extractFromHtml($htmlBody);
                }
                
                // If still invalid or empty, try text
                if ((!$directName || !$this->isValidExtractedName($directName)) && !empty($textBody)) {
                    $directName = $nameExtractor->extractFromText($textBody, $emailData['subject'] ?? '');
                }
                
                // Use direct extraction if valid
                if ($directName && $this->isValidExtractedName($directName)) {
                    if (!$result) {
                        $result = ['data' => [], 'method' => 'direct'];
                    }
                    $result['data']['sender_name'] = $directName;
                } elseif ($result && $extractedSenderName && !$this->isValidExtractedName($extractedSenderName)) {
                    // Clear invalid name
                    $result['data']['sender_name'] = null;
                }
            }
            
            // PRIORITY 3: Extract description field from text/html
            $descriptionField = $this->descriptionExtractor->extractFromHtml($htmlBody)
                ?? $this->descriptionExtractor->extractFromText($textBody);
            
            if ($descriptionField) {
                if (!$result) {
                    $result = ['data' => [], 'method' => 'direct'];
                }
                $parsed = $this->descriptionExtractor->parseDescriptionField($descriptionField);
                
                // Merge description field data into result
                $result['data']['description_field'] = $descriptionField;
                if ($parsed['account_number'] && empty($result['data']['account_number'])) {
                    $result['data']['account_number'] = $parsed['account_number'];
                }
                if ($parsed['payer_account_number'] && !isset($result['data']['payer_account_number'])) {
                    $result['data']['payer_account_number'] = $parsed['payer_account_number'];
                }
            }
            
            // PRIORITY 3.5: Extract transaction ID from email text/html
            $transactionId = null;
            $combinedText = ($textBody ?? '') . ' ' . ($htmlBody ?? '');
            if (preg_match('/TXN[\-]?[\d]+[\-]?[A-Z0-9]+/i', $combinedText, $txnMatches)) {
                $transactionId = trim($txnMatches[0]);
            }
            
            if ($transactionId) {
                if (!$result) {
                    $result = ['data' => [], 'method' => 'direct'];
                }
                $result['data']['transaction_id'] = $transactionId;
            }
            
            // Python extraction removed - using PHP extraction only for better performance
            // PHP extraction is more reliable and faster than Python service
            
            return $result;
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
        
        // STEP 0: Transaction ID matching (HIGHEST PRIORITY - if transaction ID matches exactly, match even without name)
        $extractedTransactionId = $extractedInfo['transaction_id'] ?? null;
        if ($extractedTransactionId && !empty($payment->transaction_id)) {
            // Normalize transaction IDs (uppercase, remove dashes for comparison)
            $extractedTxnUpper = strtoupper(trim($extractedTransactionId));
            $paymentTxnUpper = strtoupper(trim($payment->transaction_id));
            $extractedTxnNoDash = str_replace('-', '', $extractedTxnUpper);
            $paymentTxnNoDash = str_replace('-', '', $paymentTxnUpper);
            
            // Exact match (with or without dashes)
            if ($extractedTxnNoDash === $paymentTxnNoDash || $extractedTxnUpper === $paymentTxnUpper) {
                // Transaction ID matches exactly - this is a STRONG MATCH even without name
                $receivedAmount = $extractedInfo['amount'] ?? 0;
                $requestedAmount = $payment->amount;
                $amountDiff = abs($requestedAmount - $receivedAmount);
                
                // Still check amount (should match)
                if ($amountDiff <= 1) {
                    $result['matched'] = true;
                    $result['amount_match'] = true;
                    $result['name_match'] = false; // Name not required for transaction ID match
                    $result['reason'] = 'Matched: Exact transaction ID match (name not required)';
                    
                    // Still try to match name if available (for logging, but transaction ID match is sufficient)
                    $extractedSenderName = $extractedInfo['sender_name'] ?? null;
                    if ($extractedSenderName && $this->isValidExtractedName($extractedSenderName)) {
                        $nameMatch = $this->matchNames($payment->payer_name, $extractedSenderName);
                        $result['name_match'] = $nameMatch['matched'] && ($nameMatch['similarity'] ?? 0) >= 50;
                        $result['name_similarity_percent'] = $nameMatch['similarity'] ?? null;
                        if ($result['name_match']) {
                            $result['reason'] = 'Matched: Exact transaction ID and name match (similarity: ' . ($nameMatch['similarity'] ?? 0) . '%)';
                        } else {
                            $result['reason'] = 'Matched: Exact transaction ID match (name similarity: ' . ($nameMatch['similarity'] ?? 0) . '% < 50%)';
                        }
                    }
                    
                    // Time matching (less strict for transaction ID match)
                    if ($emailDate) {
                        if ($emailDate->lt($payment->created_at)) {
                            $result['reason'] = "Transaction ID matches but email received before transaction creation";
                            return $result;
                        }
                        $timeDiffMinutes = $payment->created_at->diffInMinutes($emailDate);
                        $result['time_match'] = ($timeDiffMinutes <= 120); // 2 hours window for transaction ID match
                    } else {
                        $result['time_match'] = true;
                    }
                    
                    return $result;
                } else {
                    $result['reason'] = "Transaction ID matches but amount mismatch: payment={$requestedAmount}, email={$receivedAmount}";
                    return $result;
                }
            }
        }
        
        // STEP 1: Amount matching and charges mismatch detection
        $receivedAmount = $extractedInfo['amount'] ?? 0;
        $requestedAmount = $payment->amount;
        $amountDiff = abs($requestedAmount - $receivedAmount);
        
        // CRITICAL: Validate extracted sender name before matching
        $extractedSenderName = $extractedInfo['sender_name'] ?? null;
        if ($extractedSenderName && !$this->isValidExtractedName($extractedSenderName)) {
            $result['reason'] = "Invalid extracted sender name: '{$extractedSenderName}'";
            return $result;
        }
        
        // STEP 2: Name matching (check if names match or are similar)
        // CRITICAL: Require at least 50% similarity to prevent matching wrong persons
        $nameMatch = $this->matchNames($payment->payer_name, $extractedSenderName);
        $minSimilarityRequired = 50;
        $similarity = $nameMatch['similarity'] ?? 0;
        
        // Only consider it a match if similarity >= 50%
        $result['name_match'] = $nameMatch['matched'] && $similarity >= $minSimilarityRequired;
        $result['name_similarity_percent'] = $similarity;
        $result['name_mismatch'] = !$result['name_match'];
        
        // Check for charges mismatch scenario: name matches (with >= 50% similarity) but amount differs by charges amount
        $chargesMismatchDetected = false;
        $minSimilarityForChargesMismatch = 50;
        if ($result['name_match'] && ($result['name_similarity_percent'] ?? 0) >= $minSimilarityForChargesMismatch && $amountDiff > 1 && $payment->business_id) {
            $business = $payment->business;
            $website = $payment->website; // Get website from payment if available
            $chargeService = app(\App\Services\ChargeService::class);
            
            // Calculate what charges would be for the received amount
            $chargesForReceived = $chargeService->calculateCharges($receivedAmount, $website, $business);
            $expectedCharges = $chargesForReceived['total_charges'];
            
            // Check if the difference equals the charges (within 1 naira tolerance)
            if (abs($amountDiff - $expectedCharges) <= 1) {
                // This is a charges mismatch - customer paid base amount without charges
                $chargesMismatchDetected = true;
                $result['matched'] = true;
                $result['amount_match'] = false; // Amount doesn't match exactly
                $result['is_mismatch'] = true;
                $result['received_amount'] = $receivedAmount;
                $result['mismatch_reason'] = 'Customer paid base amount without charges. Expected: ₦' . number_format($requestedAmount, 2) . ', Received: ₦' . number_format($receivedAmount, 2) . ' (charges: ₦' . number_format($expectedCharges, 2) . ')';
                $result['reason'] = 'Matched: Name matches, amount mismatch equals charges (charges not included)';
                
                // Store charges info for later use
                $result['_charges_info'] = [
                    'expected_charges' => $expectedCharges,
                    'charges_percentage' => $chargesForReceived['charge_percentage'],
                    'charges_fixed' => $chargesForReceived['charge_fixed'],
                ];
            }
        }
        
        // STEP 1 (continued): Strict amount matching (if not charges mismatch)
        if (!$chargesMismatchDetected) {
            if ($amountDiff > 1) { // Allow 1 naira tolerance
                $result['reason'] = "Amount mismatch: payment={$requestedAmount}, email={$receivedAmount}";
                return $result;
            }
            $result['amount_match'] = true;
        }
        
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
        // Note: Charges mismatch case is already handled above and marked as matched
        if ($result['matched'] && $chargesMismatchDetected) {
            // Charges mismatch already detected - ensure time match passes
            if (!$result['time_match'] && $emailDate) {
                // If time doesn't match, still allow if within reasonable window (charges mismatch is acceptable)
                $timeDiffMinutes = $payment->created_at->diffInMinutes($emailDate);
                if ($timeDiffMinutes <= 120) { // 2 hours window for charges mismatch
                    $result['time_match'] = true;
                } else {
                    // Time window exceeded - reject the match
                    $result['matched'] = false;
                    $result['reason'] = "Charges mismatch detected but time window exceeded: {$timeDiffMinutes} minutes (max: 120)";
                    return $result;
                }
            }
            // Charges mismatch is already matched and time validated, return
            return $result;
        }
        
        if ($result['amount_match']) {
            // CRITICAL: Require at least 50% name similarity before matching
            $minSimilarityRequired = 50;
            $hasMinimumSimilarity = $result['name_similarity_percent'] !== null && $result['name_similarity_percent'] >= $minSimilarityRequired;
            
            if ($result['name_match'] && $hasMinimumSimilarity) {
                // Amount + Name match = STRONG MATCH (only if similarity >= 50%)
                $result['matched'] = true;
                $result['reason'] = 'Matched: Amount and name match (similarity: ' . $result['name_similarity_percent'] . '%)';
            } elseif ($result['name_match'] && !$hasMinimumSimilarity) {
                // Name matched but similarity too low - reject
                $result['matched'] = false;
                $result['name_match'] = false;
                $result['reason'] = 'Name similarity too low (' . $result['name_similarity_percent'] . '% < ' . $minSimilarityRequired . '%) - potential wrong person';
            } elseif ($result['account_match']) {
                // Amount + Account match = MATCH (but still prefer name match)
                $result['matched'] = true;
                $result['reason'] = 'Matched: Amount and account number match (name similarity: ' . ($result['name_similarity_percent'] ?? 'N/A') . '%)';
            } elseif ($result['time_match']) {
                // Amount + Time match = WEAK MATCH (only if time is very close AND name similarity >= 50%)
                if ($emailDate && $payment->created_at->diffInMinutes($emailDate) <= 15 && $hasMinimumSimilarity) {
                    $result['matched'] = true;
                    $result['reason'] = 'Matched: Amount and time match (name similarity: ' . $result['name_similarity_percent'] . '%)';
                    $result['is_mismatch'] = true;
                    $result['mismatch_reason'] = 'Name similarity acceptable (' . $result['name_similarity_percent'] . '%) but not exact match';
                } else {
                    if (!$hasMinimumSimilarity && $result['name_similarity_percent'] !== null) {
                        $result['reason'] = 'Amount and time match but name similarity too low (' . $result['name_similarity_percent'] . '% < ' . $minSimilarityRequired . '%) - potential wrong person';
                    } else {
                        $result['reason'] = 'Amount matches but name and account do not match, and time window too large';
                    }
                }
            } else {
                $result['reason'] = 'Amount matches but name similarity too low (' . ($result['name_similarity_percent'] ?? 'N/A') . '% < ' . $minSimilarityRequired . '%), account mismatch, and time mismatch';
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
        
        // Calculate similarity first to ensure minimum threshold
        $similarity = 0;
        similar_text($paymentNameNorm, $emailNameNorm, $similarity);
        
        // If payment name is contained in email name (or vice versa), check similarity before matching
        // This allows "john doe" to match "john doe from opay" or "via gtbank john doe"
        // IMPORTANT: Only match if the contained name is at least 3 characters AND similarity >= 50%
        if (strlen($paymentNoSpace) >= 3 && strlen($emailNoSpace) >= 3) {
            if (strpos($emailNoSpace, $paymentNoSpace) !== false) {
                // Payment name found in email name - MATCH only if similarity >= 50%
                if ($similarity >= 50) {
                    return ['matched' => true, 'similarity' => round($similarity), 'reason' => 'Payment name contained in email name (similarity: ' . round($similarity) . '%)'];
                } else {
                    return ['matched' => false, 'similarity' => round($similarity), 'reason' => 'Payment name contained but similarity too low (' . round($similarity) . '% < 50%)'];
                }
            }
            if (strpos($paymentNoSpace, $emailNoSpace) !== false) {
                // Email name found in payment name - MATCH only if similarity >= 50%
                if ($similarity >= 50) {
                    return ['matched' => true, 'similarity' => round($similarity), 'reason' => 'Email name contained in payment name (similarity: ' . round($similarity) . '%)'];
                } else {
                    return ['matched' => false, 'similarity' => round($similarity), 'reason' => 'Email name contained but similarity too low (' . round($similarity) . '% < 50%)'];
                }
            }
        }

        // Remove all spaces and compare
        $paymentNoSpace = str_replace(' ', '', $paymentNameNorm);
        $emailNoSpace = str_replace(' ', '', $emailNameNorm);
        
        if ($paymentNoSpace === $emailNoSpace) {
            // Calculate actual similarity to ensure it meets threshold
            $similarity = 0;
            similar_text($paymentNameNorm, $emailNameNorm, $similarity);
            // No-space match is typically high similarity, but verify >= 50%
            if ($similarity >= 50) {
                return ['matched' => true, 'similarity' => round($similarity)];
            } else {
                return ['matched' => false, 'similarity' => round($similarity), 'reason' => 'No-space match but similarity too low (' . round($similarity) . '% < 50%)'];
            }
        }
        
        // Check if one contains the other (partial match) - but require similarity check
        if (strlen($paymentNoSpace) >= 3 && strlen($emailNoSpace) >= 3) {
            if (strpos($paymentNoSpace, $emailNoSpace) !== false || strpos($emailNoSpace, $paymentNoSpace) !== false) {
                // Calculate similarity to ensure it meets minimum threshold
                $similarity = 0;
                similar_text($paymentNameNorm, $emailNameNorm, $similarity);
                if ($similarity >= 50) {
                    return ['matched' => true, 'similarity' => round($similarity), 'reason' => 'Partial name match (similarity: ' . round($similarity) . '%)'];
                } else {
                    return ['matched' => false, 'similarity' => round($similarity), 'reason' => 'Partial match but similarity too low (' . round($similarity) . '% < 50%)'];
                }
            }
        }
        
        // Word-by-word matching - handles reordered names (e.g., "adeniyi timilehin okikiola" vs "okikiola timilehin adeniyi")
        $paymentWords = array_filter(explode(' ', $paymentNameNorm), function($word) {
            return strlen(trim($word)) >= 2; // Only consider words with 2+ characters
        });
        $emailWords = array_filter(explode(' ', $emailNameNorm), function($word) {
            return strlen(trim($word)) >= 2; // Only consider words with 2+ characters
        });
        
        // Remove duplicates and normalize
        $paymentWords = array_unique(array_map('trim', $paymentWords));
        $emailWords = array_unique(array_map('trim', $emailWords));
        
        if (count($paymentWords) >= 2 && count($emailWords) >= 2) {
            $matchingWords = array_intersect($paymentWords, $emailWords);
            $matchingCount = count($matchingWords);
            $totalWords = max(count($paymentWords), count($emailWords));
            
            // If all words match (regardless of order), it's a strong match
            // Example: "adeniyi timilehin okikiola" vs "okikiola timilehin adeniyi" = all 3 words match
            if ($matchingCount === count($paymentWords) && $matchingCount === count($emailWords)) {
                // All words match - this is a STRONG MATCH (same person, different order)
                $similarity = 0;
                similar_text($paymentNameNorm, $emailNameNorm, $similarity);
                // Even if similarity is slightly below 50%, if all words match, consider it a match
                // But still require minimum 40% similarity to avoid false positives
                if ($similarity >= 40) {
                    return ['matched' => true, 'similarity' => max(round($similarity), 85), 'reason' => 'All words match (reordered names, similarity: ' . round($similarity) . '%)'];
                }
            }
            
            // If at least 2 words match and they represent a significant portion
            if ($matchingCount >= 2) {
                $wordMatchRatio = $matchingCount / $totalWords;
                // If at least 70% of words match, consider it a match
                if ($wordMatchRatio >= 0.7) {
                    $similarity = 0;
                    similar_text($paymentNameNorm, $emailNameNorm, $similarity);
                    if ($similarity >= 50) {
                        return ['matched' => true, 'similarity' => round($similarity), 'reason' => 'Most words match (' . $matchingCount . '/' . $totalWords . ' words, similarity: ' . round($similarity) . '%)'];
                    }
                }
                
                // Standard check: at least 2 words match and similarity >= 50%
                $similarity = 0;
                similar_text($paymentNameNorm, $emailNameNorm, $similarity);
                if ($similarity >= 50) {
                    return ['matched' => true, 'similarity' => round($similarity), 'reason' => 'Word match (' . $matchingCount . ' words, similarity: ' . round($similarity) . '%)'];
                } else {
                    return ['matched' => false, 'similarity' => round($similarity), 'reason' => 'Word match but similarity too low (' . round($similarity) . '% < 50%)'];
                }
            }
        }
        
        // Calculate similarity percentage using similar_text
        $similarity = 0;
        similar_text($paymentNameNorm, $emailNameNorm, $similarity);
        
        // CRITICAL: Require at least 50% similarity before matching
        // This prevents matching wrong persons
        if ($similarity >= 50) {
            return ['matched' => true, 'similarity' => round($similarity), 'reason' => 'Similarity match (' . round($similarity) . '%)'];
        }
        
        return ['matched' => false, 'similarity' => round($similarity), 'reason' => 'Similarity too low (' . round($similarity) . '% < 50%)'];
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
            
            // Get current extracted_data or initialize empty array
            $extractedData = $email->extracted_data ?? [];
            
            // Extract description field if missing
            if (!$email->description_field) {
                $descriptionField = $this->descriptionExtractor->extractFromHtml($htmlBody)
                    ?? $this->descriptionExtractor->extractFromText($textBody);
                
                if ($descriptionField) {
                    $email->description_field = $descriptionField;
                    // Update extracted_data
                    $extractedData['description_field'] = $descriptionField;
                    // Also update if nested in 'data' key
                    if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                        $extractedData['data']['description_field'] = $descriptionField;
                    }
                    $updated = true;
                }
            }
            
            // Extract sender name if missing (using simple patterns)
            if (!$email->sender_name) {
                $senderName = $this->extractSenderNameFromText($textBody, $htmlBody);
                if ($senderName) {
                    $email->sender_name = $senderName;
                    // Update extracted_data
                    $extractedData['sender_name'] = strtolower($senderName);
                    // Also update if nested in 'data' key
                    if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                        $extractedData['data']['sender_name'] = strtolower($senderName);
                    }
                    // Add extraction method info
                    $extractedData['extraction_method'] = 'text_body_re_extraction';
                    $extractedData['extraction_timestamp'] = now()->toDateTimeString();
                    $updated = true;
                }
            }
            
            if ($updated) {
                // Update extracted_data field
                $email->extracted_data = $extractedData;
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
        
        $nameExtractor = new \App\Services\SenderNameExtractor();
        
        // Check if text_body contains HTML tags (old system saved HTML in text_body)
        $textBodyHasHtml = !empty($textBody) && preg_match('/<[^>]+>/', $textBody);
        
        // PRIORITY: If text_body contains HTML, treat it as HTML
        if ($textBodyHasHtml) {
            $htmlName = $nameExtractor->extractFromHtml($textBody);
            if ($htmlName) {
                return $htmlName;
            }
            // Also try extracting from text (strip tags first)
            $textOnly = strip_tags($textBody);
            $textName = $nameExtractor->extractFromText($textOnly);
            if ($textName) {
                return $textName;
            }
        } else {
            // PRIORITY: Try text body first (cleaner, no HTML tags)
            if (!empty($textBody)) {
                $textName = $nameExtractor->extractFromText($textBody);
                if ($textName) {
                    return $textName;
                }
            }
        }
        
        // Then try HTML body (more structured but may have HTML tags)
        if (!empty($htmlBody)) {
            $htmlName = $nameExtractor->extractFromHtml($htmlBody);
            if ($htmlName) {
                return $htmlName;
            }
        }
        
        // Fallback: combine both (strip HTML tags from text_body if it has HTML)
        $textForCombined = $textBodyHasHtml ? strip_tags($textBody) : $textBody;
        $htmlText = strip_tags($htmlBody);
        $combined = $htmlText . ' ' . $textForCombined;
        
        // Normalize whitespace
        $combined = preg_replace('/\s+/', ' ', $combined);
        
        return $nameExtractor->extractFromText($combined);
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
            
            // Pattern 5: "Description : CODE-TXN-CODE-CODE-PALMPAY-NAME" format (PALMPAY/OPAY specific - high priority)
            // Handles: ...-TXN-CODE-CODE-PALMPAY-OKWUDIRI RICHARD Amount
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TXN-[\d\-]+-[A-Z]+[\s=]*[A-Z\-]*[\s=]*-[\s=]*(?:PALMPAY|OPAY|KUD|KUDABANK)[\s=]*-[\s=]*([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*Value)/i',
            
            // Pattern 5b: "Description : ...-PALMPAY-NAME" format (simpler pattern for PALMPAY)
            // Handles: ...-PALMPAY-OKWUDIRI RICHARD Amount
            '/description[\s:]+.*?-(?:PALMPAY|OPAY|KUD|KUDABANK)-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*Value)/i',
            
            // Pattern 5c: "Description : ...-INTERNET-KMB-NAME" or "...-KMB-NAME" or "...-BIG-KMB-NAME" format (KMB specific)
            // Handles: ...-INTERNET-KMB-AKINDEINDE, OLAYINKA H Amount or ...-BIG-KMB-OSULA, GODSTIME
            '/description[\s:]+.*?-(?:INTERNET-|BIG-)?KMB-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*Value|\s*H\s*=)/i',
            
            // Pattern 6: "Description : CODE-TXN-CODE-CODE-NAME" format (handles quoted-printable)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TXN-[\d\-]+-[A-Z]+=\s*[A-Z\-]*-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount)/i',
            
            // Pattern 7: "Description : CODE-BBBB-KMB-NAME" format (handles quoted-printable)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-BBBB-KMB-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*\.)/i',
            
            // Pattern 8: "Description : CODE-CODE-CODE-NAME" format (generic pattern)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-[A-Z]+-[A-Z]+-([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*\.)/i',
            
            // Pattern 9: "Description : CODE-NAME = PHONE-BANK" format (name before phone number)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-([A-Z][A-Z\s\-,]{3,50}?)\s*=\s*[\d]+-[A-Z]+/i',
            
            // Pattern 10: "Description : CODE-NAME-BANK-NAME" format (extract first name before bank)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-([A-Z][A-Z\s\-,]{3,50}?)-(?:OPAY|GTBANK|ACCESS|ZENITH|UBA|FIRST\s+BANK|PALMPAY|KUD|BBBB|KMB)/i',
            
            // Pattern 11: "Description : ...-PALMPAY-NAME" or "...-OPAY-NAME" format (payment provider followed by name)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TXN-[\d\-]+-[A-Z]+[\s=]*[A-Z\-]*[\s=]*-[\s=]*(?:PALMPAY|OPAY|KUD|KUDABANK)[\s=]*-[\s=]*([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*Value)/i',
            
            // Pattern 12: "Description : ...-PALMPAY NAME" format (space instead of hyphen)
            '/description[\s:]+(?:=\d+)?\s*[\d\-]+-TXN-[\d\-]+-[A-Z]+[\s=]*[A-Z\-]*[\s=]*-[\s=]*(?:PALMPAY|OPAY|KUD|KUDABANK)[\s=]+([A-Z][A-Z\s\-,]{3,50}?)(?:\s*=|\s*Amount|\s*Value)/i',
            
            // Pattern 13: "Remarks : NAME" (extract name from remarks field)
            '/remark[s]?[\s:]+([A-Z][A-Z\s\-,]{3,50}?)(?:\s*\||\s*$|\s*Time)/i',
            
            // Pattern 14: "FROM OPAY/ NAME" or "FROM BANK/ NAME"
            '/from\s+(?:[A-Z]+\/|OPAY\/|GTBANK\/|ACCESS\/|ZENITH\/|UBA\/|FIRST\s+BANK\/)\s*([A-Z][A-Z\s\-]{3,50}?)(?:\s*\/|\s*Support|\s*\||\s*$)/i',
            
            // Pattern 15: "received from NAME"
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
        
        // Sanitize UTF-8 after decoding
        $text = $this->sanitizeUtf8($text);
        
        return $text;
    }
    
    /**
     * Sanitize UTF-8 string to remove malformed characters
     * 
     * @param string $string
     * @return string
     */
    protected function sanitizeUtf8(string $string): string
    {
        if (empty($string)) {
            return $string;
        }
        
        // First, try to fix encoding using mb_convert_encoding
        if (!mb_check_encoding($string, 'UTF-8')) {
            // Try to convert from various encodings
            $encodings = ['ISO-8859-1', 'Windows-1252', 'UTF-8'];
            foreach ($encodings as $encoding) {
                $converted = @mb_convert_encoding($string, 'UTF-8', $encoding);
                if (mb_check_encoding($converted, 'UTF-8')) {
                    $string = $converted;
                    break;
                }
            }
        }
        
        // Use iconv to remove invalid UTF-8 sequences
        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        
        // If iconv failed, use mb_convert_encoding with IGNORE flag
        if ($sanitized === false || !mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // Remove control characters except newlines, carriage returns, and tabs
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $sanitized);
        
        // Final check: ensure valid UTF-8
        if (!mb_check_encoding($sanitized, 'UTF-8')) {
            // Last resort: remove any remaining invalid bytes
            $sanitized = mb_convert_encoding($sanitized, 'UTF-8', 'UTF-8');
        }
        
        return $sanitized ?: '';
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
        
        $name = trim($name);
        
        // Reject specific invalid names
        $invalidNames = ['-', 'mobile', 'vam transfer transaction', 'vam'];
        if (in_array(strtolower($name), $invalidNames)) {
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

    /**
     * Reverse search: Check if payer_name from pending payment appears in unmatched emails
     * This helps catch cases where emails came in but weren't matched initially
     * 
     * @param Payment $payment The pending payment to search for
     * @return ProcessedEmail|null The matched email if found
     */
    public function reverseSearchPaymentInEmails(Payment $payment): ?ProcessedEmail
    {
        try {
            // Skip if payment doesn't have a valid payer_name
            if (empty($payment->payer_name) || !$this->isValidExtractedName($payment->payer_name)) {
                return null;
            }

            // Get unmatched emails with matching amount
            // CRITICAL: Only check emails received AFTER transaction creation
            $query = ProcessedEmail::where('is_matched', false)
                ->whereBetween('amount', [
                    $payment->amount - 1,
                    $payment->amount + 1
                ])
                ->where('email_date', '>=', $payment->created_at); // Email must be AFTER transaction
            
            // Filter by email account if business has one
            if ($payment->business_id && $payment->business->email_account_id) {
                $query->where('email_account_id', $payment->business->email_account_id);
            }
            
            $potentialEmails = $query->orderBy('email_date', 'asc')->get();
            
            // Normalize payer name for searching (uppercase, remove extra spaces)
            $payerNameNormalized = strtoupper(trim($payment->payer_name));
            $payerNameNormalized = preg_replace('/\s+/', ' ', $payerNameNormalized);
            $payerNameWords = explode(' ', $payerNameNormalized);
            
            // Need at least 2 words to search (to avoid false matches)
            if (count($payerNameWords) < 2) {
                return null;
            }
            
            foreach ($potentialEmails as $email) {
                // Combine text and HTML for searching
                $emailContent = ($email->text_body ?? '') . ' ' . ($email->html_body ?? '');
                $emailContent = strip_tags($emailContent); // Remove HTML tags
                $emailContent = $this->decodeQuotedPrintable($emailContent);
                $emailContentUpper = strtoupper($emailContent);
                
                // Check if payer name appears in email content
                // Strategy: Check if all significant words from payer_name appear in email
                $significantWords = array_filter($payerNameWords, function($word) {
                    return strlen($word) >= 3; // Only words with 3+ characters
                });
                
                if (empty($significantWords)) {
                    continue;
                }
                
                // Check if all significant words appear in email (in any order)
                $allWordsFound = true;
                foreach ($significantWords as $word) {
                    // Use word boundary to avoid partial matches
                    if (!preg_match('/\b' . preg_quote($word, '/') . '\b/i', $emailContentUpper)) {
                        $allWordsFound = false;
                        break;
                    }
                }
                
                if (!$allWordsFound) {
                    continue;
                }
                
                // Name found in email! Now verify amount matches and extract info for proper matching
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
                
                // Use matchPayment to verify the match (checks amount, time, etc.)
                $matchResult = $this->matchPayment($payment, $extractedInfo, $emailDate);
                
                if ($matchResult['matched']) {
                    Log::info('Reverse search found match', [
                        'payment_id' => $payment->id,
                        'email_id' => $email->id,
                        'payer_name' => $payment->payer_name,
                        'amount' => $payment->amount,
                    ]);
                    return $email;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error in reverse search for payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
