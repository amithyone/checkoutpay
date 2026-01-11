<?php

namespace App\Services;

use App\Models\Payment;

class PaymentMatcher
{
    /**
     * Match payment against extracted email info
     * This is the full matching logic from PaymentMatchingService
     * 
     * @param Payment $payment The payment to match
     * @param array $extractedInfo Extracted info from email
     * @param \DateTime|null $emailDate Email date
     * @param int|null $pendingPaymentsWithSameAmount Number of pending payments with the same amount (for flexible matching)
     */
    public function matchPayment(Payment $payment, array $extractedInfo, ?\DateTime $emailDate = null, ?int $pendingPaymentsWithSameAmount = null): array
    {
        // Check time window: email must be received AFTER transaction creation and within configured minutes
        $timeWindowMinutes = \App\Models\Setting::get('payment_time_window_minutes', 120);
        
        // Ensure both dates are in the same timezone (Africa/Lagos) for accurate comparison
        if ($emailDate && $payment->created_at) {
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
                    'time_diff_minutes' => -$timeDiff,
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
                    'time_diff_minutes' => $timeDiff,
                ];
            }
        }
        
        // Calculate time diff for logging (even if match succeeds)
        $timeDiff = null;
        if ($emailDate && $payment->created_at) {
            $paymentTime = \Carbon\Carbon::parse($payment->created_at)->setTimezone(config('app.timezone'));
            $emailTime = \Carbon\Carbon::parse($emailDate)->setTimezone(config('app.timezone'));
            $timeDiff = $paymentTime->diffInMinutes($emailTime);
        }

        // Calculate amount difference
        $expectedAmount = $payment->amount;
        $receivedAmount = $extractedInfo['amount'];
        $amountDiff = $expectedAmount - $receivedAmount; // Positive if received is lower
        $amountTolerance = 0.01; // Small tolerance for rounding (1 kobo)
        
        // NEW STRATEGY: Check name first, then amount
        $nameSimilarityPercent = null;
        $nameMatches = false;
        
        // If payer name is provided, check similarity first
        if ($payment->payer_name) {
            if (empty($extractedInfo['sender_name'])) {
                // Name required but not found - REJECT (cannot approve without name match)
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Payer name required but not found in email. Expected "%s", but sender_name is empty. Cannot approve without name match.',
                        $payment->payer_name
                    ),
                    'amount_diff' => $amountDiff,
                    'time_diff_minutes' => $timeDiff,
                    'name_similarity_percent' => 0,
                ];
            } else {
                // Normalize names for comparison
                $expectedName = trim(strtolower($payment->payer_name));
                $expectedName = preg_replace('/\s+/', ' ', $expectedName);
                $receivedName = trim(strtolower($extractedInfo['sender_name']));
                $receivedName = preg_replace('/\s+/', ' ', $receivedName);

                // Check if names match with similarity
                $matchResult = $this->namesMatch($expectedName, $receivedName);
                $nameSimilarityPercent = $matchResult['similarity'];
                $nameMatches = $matchResult['matched'];
                
                if (!$nameMatches) {
                    // Name doesn't match - require exact amount match
                    if (abs($amountDiff) > $amountTolerance) {
                        return [
                            'matched' => false,
                            'reason' => sprintf(
                                'Name mismatch: expected "%s", got "%s" (similarity: %d%%) and amount mismatch',
                                $expectedName,
                                $receivedName,
                                $nameSimilarityPercent
                            ),
                            'amount_diff' => $amountDiff,
                            'time_diff_minutes' => $timeDiff,
                            'name_similarity_percent' => $nameSimilarityPercent,
                        ];
                    }
                }
            }
        }
        
        // If name matches (or no name required), handle amount matching with lenient rules
        $isMismatch = false;
        $mismatchReason = null;
        $finalReceivedAmount = null;
        
        // If name matches, we allow larger amount differences
        if ($nameMatches) {
            // Name matches - be lenient with amount (allow up to N5000 difference)
            $maxAmountDiff = 5000;
            
            if ($amountDiff >= $maxAmountDiff) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Amount mismatch too large: expected ₦%s, received ₦%s (difference: ₦%s). Name matches but amount difference exceeds limit.',
                        number_format($expectedAmount, 2),
                        number_format($receivedAmount, 2),
                        number_format($amountDiff, 2)
                    ),
                    'amount_diff' => $amountDiff,
                    'time_diff_minutes' => $timeDiff,
                    'name_similarity_percent' => $nameSimilarityPercent,
                ];
            } elseif (abs($amountDiff) > $amountTolerance) {
                // Amount differs but within acceptable range - approve with mismatch flag
                $isMismatch = true;
                $finalReceivedAmount = $receivedAmount;
                
                if ($amountDiff > 0) {
                    $mismatchReason = sprintf(
                        'Amount mismatch: expected ₦%s, received ₦%s (difference: ₦%s). Payment approved because name matches.',
                        number_format($expectedAmount, 2),
                        number_format($receivedAmount, 2),
                        number_format($amountDiff, 2)
                    );
                } else {
                    $mismatchReason = sprintf(
                        'Amount mismatch: expected ₦%s, received ₦%s (overpayment: ₦%s). Payment approved because name matches.',
                        number_format($expectedAmount, 2),
                        number_format($receivedAmount, 2),
                        number_format(abs($amountDiff), 2)
                    );
                }
            }
        } else {
            // Name doesn't match or not provided
            // FLEXIBLE MATCHING: Check if multiple payments exist with same amount
            $hasMultiplePaymentsWithSameAmount = ($pendingPaymentsWithSameAmount !== null && $pendingPaymentsWithSameAmount > 1);
            
            if ($hasMultiplePaymentsWithSameAmount) {
                // Multiple payments with same amount - MUST have name match
                if ($payment->payer_name) {
                    if ($nameSimilarityPercent === null || $nameSimilarityPercent < 65) {
                        return [
                            'matched' => false,
                            'reason' => sprintf(
                                'Multiple pending payments with same amount (%d found). Name similarity too low: %d%% (minimum 65%% required). Expected "%s", got "%s". Name match required to distinguish between payments.',
                                $pendingPaymentsWithSameAmount,
                                $nameSimilarityPercent ?? 0,
                                $payment->payer_name,
                                $extractedInfo['sender_name'] ?? 'not found'
                            ),
                            'amount_diff' => $amountDiff,
                            'time_diff_minutes' => $timeDiff,
                            'name_similarity_percent' => $nameSimilarityPercent ?? 0,
                        ];
                    }
                } else {
                    // No payer_name but multiple payments - cannot distinguish
                    return [
                        'matched' => false,
                        'reason' => sprintf(
                            'Multiple pending payments with same amount (%d found). Cannot match without payer_name to distinguish between payments.',
                            $pendingPaymentsWithSameAmount
                        ),
                        'amount_diff' => $amountDiff,
                        'time_diff_minutes' => $timeDiff,
                        'name_similarity_percent' => $nameSimilarityPercent ?? 0,
                    ];
                }
            } else {
                // Single payment with this amount - allow approval with name mismatch (but flag it)
                // Still require exact amount match
                if (abs($amountDiff) > $amountTolerance) {
                    return [
                        'matched' => false,
                        'reason' => sprintf(
                            'Amount mismatch: expected ₦%s, received ₦%s (difference: ₦%s). Exact amount required.',
                            number_format($expectedAmount, 2),
                            number_format($receivedAmount, 2),
                            number_format(abs($amountDiff), 2)
                        ),
                        'amount_diff' => $amountDiff,
                        'time_diff_minutes' => $timeDiff,
                        'name_similarity_percent' => $nameSimilarityPercent ?? 0,
                    ];
                }
                
                // Amount matches exactly - approve but flag name mismatch if applicable
                if ($payment->payer_name && ($nameSimilarityPercent === null || $nameSimilarityPercent < 65)) {
                    // Flag as name mismatch but allow approval (single payment scenario)
                    $isMismatch = true;
                    $mismatchReason = sprintf(
                        'Name mismatch: expected "%s", got "%s" (similarity: %d%%). Approved because only one pending payment with this amount. Amount matches exactly.',
                        $payment->payer_name,
                        $extractedInfo['sender_name'] ?? 'not found',
                        $nameSimilarityPercent ?? 0
                    );
                }
            }
        }

        // FINAL VALIDATION: Check if multiple payments exist with same amount
        $hasMultiplePaymentsWithSameAmount = ($pendingPaymentsWithSameAmount !== null && $pendingPaymentsWithSameAmount > 1);
        
        if ($hasMultiplePaymentsWithSameAmount && $payment->payer_name) {
            // Multiple payments with same amount - require name match
            if ($nameSimilarityPercent === null || $nameSimilarityPercent < 65) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Multiple pending payments with same amount (%d found). Name similarity too low: %d%% (minimum 65%% required). Cannot approve without name match.',
                        $pendingPaymentsWithSameAmount,
                        $nameSimilarityPercent ?? 0
                    ),
                    'amount_diff' => $amountDiff,
                    'time_diff_minutes' => $timeDiff,
                    'name_similarity_percent' => $nameSimilarityPercent ?? 0,
                ];
            }
        }

        return [
            'matched' => true,
            'reason' => $isMismatch ? $mismatchReason : 'Amount and name match within time window',
            'is_mismatch' => $isMismatch,
            'name_mismatch' => ($payment->payer_name && ($nameSimilarityPercent === null || $nameSimilarityPercent < 65)) ? true : false, // Flag name mismatch in payload
            'received_amount' => $finalReceivedAmount,
            'mismatch_reason' => $mismatchReason,
            'amount_diff' => $amountDiff,
            'time_diff_minutes' => $timeDiff,
            'name_similarity_percent' => $nameSimilarityPercent ?? 0,
        ];
    }
    
    /**
     * Check if two names match with 65% similarity
     */
    public function namesMatch(string $expectedName, string $receivedName): array
    {
        // Exact match
        if ($expectedName === $receivedName) {
            return ['matched' => true, 'similarity' => 100];
        }

        // Split names into words
        $expectedWords = array_filter(explode(' ', $expectedName));
        $receivedWords = array_filter(explode(' ', $receivedName));

        // If either is empty, no match
        if (empty($expectedWords) || empty($receivedWords)) {
            return ['matched' => false, 'similarity' => 0];
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
                if (stripos($receivedWord, $word) !== false || stripos($word, $receivedWord) !== false) {
                    $matchedWords++;
                    break;
                }
            }
        }

        // Calculate similarity percentage
        $totalExpectedWords = count($expectedWords);
        $similarityPercent = (int) round(($matchedWords / $totalExpectedWords) * 100);

        // Match if at least 65% of words match
        $matched = $similarityPercent >= 65;
        
        return ['matched' => $matched, 'similarity' => $similarityPercent];
    }
}
