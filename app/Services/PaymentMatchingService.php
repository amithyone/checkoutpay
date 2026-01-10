<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Models\MatchAttempt;
use App\Services\TransactionLogService;
use App\Services\MatchAttemptLogger;
use Illuminate\Support\Collection;

class PaymentMatchingService
{
    protected MatchAttemptLogger $matchLogger;

    public function __construct(?TransactionLogService $transactionLogService = null)
    {
        $this->matchLogger = new MatchAttemptLogger();
    }

    /**
     * Match email data against pending payments
     */
    public function matchEmail(array $emailData): ?Payment
    {
        $processedEmailId = $emailData['processed_email_id'] ?? null;
        $startTime = microtime(true);

        // Extract payment info with hybrid method (HTML primary, rendered fallback)
        $extractionResult = $this->extractPaymentInfo($emailData);
        $extractedInfo = $extractionResult['data'] ?? null;
        $extractionMethod = $extractionResult['method'] ?? 'unknown';

        if (!$extractedInfo) {
            // Get detailed extraction diagnostics
            $diagnostics = $this->getLastExtractionDiagnostics();
            
            // Build detailed error reason
            $detailedReason = 'Could not extract payment info from email.';
            if ($diagnostics) {
                $detailedReason .= "\n\nExtraction Steps:\n" . implode("\n", $diagnostics['steps']);
                $detailedReason .= "\n\nErrors Encountered:\n" . implode("\n", $diagnostics['errors']);
                $detailedReason .= "\n\nEmail Content Analysis:";
                $detailedReason .= "\n- Text Body Length: " . ($diagnostics['text_length'] ?? 0) . " chars";
                $detailedReason .= "\n- HTML Body Length: " . ($diagnostics['html_length'] ?? 0) . " chars";
                $detailedReason .= "\n- Subject: " . ($diagnostics['subject'] ?? 'N/A');
                $detailedReason .= "\n- From: " . ($diagnostics['from'] ?? 'N/A');
                
                if (!empty($diagnostics['text_preview'])) {
                    $detailedReason .= "\n\nText Body Preview (first 500 chars):\n" . $diagnostics['text_preview'];
                }
                
                if (!empty($diagnostics['html_preview'])) {
                    $detailedReason .= "\n\nHTML Body Preview (first 500 chars):\n" . $diagnostics['html_preview'];
                }
                
                // Check for common issues
                $issues = [];
                if (empty(trim($emailData['text'] ?? '')) && empty(trim($emailData['html'] ?? ''))) {
                    $issues[] = "Both text_body and html_body are empty - email may not have been parsed correctly";
                }
                if (!empty($emailData['html'] ?? '') && !preg_match('/amount|ngn|naira|payment|transfer|credit|deposit/i', $emailData['html'])) {
                    $issues[] = "HTML body doesn't contain common payment keywords (amount, ngn, naira, payment, transfer, credit, deposit)";
                }
                if (!empty($emailData['text'] ?? '') && !preg_match('/amount|ngn|naira|payment|transfer|credit|deposit/i', $emailData['text'])) {
                    $issues[] = "Text body doesn't contain common payment keywords (amount, ngn, naira, payment, transfer, credit, deposit)";
                }
                if (!empty($diagnostics['html_preview']) && !preg_match('/<td|<tr|<table/i', $diagnostics['html_preview'])) {
                    $issues[] = "HTML doesn't appear to contain table structures (td, tr, table tags) - may not be bank email format";
                }
                
                if (!empty($issues)) {
                    $detailedReason .= "\n\nPotential Issues Detected:\n" . implode("\n", array_map(fn($issue) => "- {$issue}", $issues));
                }
            }
            
            // Log failed extraction attempt with detailed diagnostics
            if ($processedEmailId) {
                $processedEmail = ProcessedEmail::find($processedEmailId);
                if ($processedEmail) {
                    $this->matchLogger->logAttempt([
                        'processed_email_id' => $processedEmailId,
                        'match_result' => MatchAttempt::RESULT_UNMATCHED,
                        'reason' => $detailedReason,
                        'extraction_method' => $extractionMethod,
                        'email_subject' => $emailData['subject'] ?? null,
                        'email_from' => $emailData['from'] ?? null,
                        'email_date' => $emailData['date'] ?? null,
                        'html_snippet' => $this->matchLogger->extractHtmlSnippet($emailData['html'] ?? ''),
                        'text_snippet' => $this->matchLogger->extractTextSnippet($emailData['text'] ?? ''),
                        'details' => array_merge([
                            'extraction_failed' => true,
                            'has_html' => !empty($emailData['html']),
                            'has_text' => !empty($emailData['text']),
                            'text_length' => strlen(trim($emailData['text'] ?? '')),
                            'html_length' => strlen(trim($emailData['html'] ?? '')),
                        ], $diagnostics ? [
                            'extraction_steps' => $diagnostics['steps'],
                            'extraction_errors' => $diagnostics['errors'],
                            'text_preview' => $diagnostics['text_preview'] ?? null,
                            'html_preview' => $diagnostics['html_preview'] ?? null,
                        ] : []),
                    ]);
                }
            }

            \Illuminate\Support\Facades\Log::info('Could not extract payment info from email', [
                'extraction_method' => $extractionMethod,
                'processed_email_id' => $processedEmailId,
                'diagnostics' => $diagnostics,
            ]);
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
            $match = $this->matchPayment($payment, $extractedInfo, !empty($emailData['date']) ? new \DateTime($emailData['date']) : null);

            // Log match attempt to database
            try {
                $this->matchLogger->logAttempt([
                    'payment_id' => $payment->id,
                    'processed_email_id' => $processedEmailId,
                    'transaction_id' => $payment->transaction_id,
                    'match_result' => $match['matched'] ? MatchAttempt::RESULT_MATCHED : MatchAttempt::RESULT_UNMATCHED,
                    'reason' => $match['reason'] ?? 'Unknown reason',
                    'payment_amount' => $payment->amount,
                    'payment_name' => $payment->payer_name,
                    'payment_account_number' => $payment->account_number,
                    'payment_created_at' => $payment->created_at,
                    'extracted_amount' => $extractedInfo['amount'] ?? null,
                    'extracted_name' => $extractedInfo['sender_name'] ?? null,
                    'extracted_account_number' => $extractedInfo['account_number'] ?? null,
                    'email_subject' => $emailData['subject'] ?? null,
                    'email_from' => $emailData['from'] ?? null,
                    'email_date' => !empty($emailData['date']) ? new \DateTime($emailData['date']) : null,
                    'amount_diff' => $match['amount_diff'] ?? null,
                    'name_similarity_percent' => $match['name_similarity_percent'] ?? null,
                    'time_diff_minutes' => $match['time_diff_minutes'] ?? null,
                    'extraction_method' => $extractionMethod,
                    'details' => [
                        'match_details' => $match,
                        'extracted_info' => $extractedInfo,
                        'payment_data' => [
                            'transaction_id' => $payment->transaction_id,
                            'amount' => $payment->amount,
                            'payer_name' => $payment->payer_name,
                            'account_number' => $payment->account_number,
                            'created_at' => $payment->created_at->toISOString(),
                        ],
                    ],
                    'html_snippet' => $this->matchLogger->extractHtmlSnippet($emailData['html'] ?? '', $extractedInfo['amount'] ?? null),
                    'text_snippet' => $this->matchLogger->extractTextSnippet($emailData['text'] ?? '', $extractedInfo['amount'] ?? null),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log match attempt', [
                    'error' => $e->getMessage(),
                    'transaction_id' => $payment->transaction_id,
                ]);
            }

            if ($match['matched']) {
                \Illuminate\Support\Facades\Log::info('Payment matched', [
                    'transaction_id' => $payment->transaction_id,
                    'match_reason' => $match['reason'],
                    'match_attempt_logged' => true,
                ]);

                return $payment;
            } else {
                \Illuminate\Support\Facades\Log::info('Payment mismatch - logged to database', [
                    'transaction_id' => $payment->transaction_id,
                    'reason' => $match['reason'],
                    'match_attempt_logged' => true,
                ]);
            }
        }

        // Log that no payment matched
        \Illuminate\Support\Facades\Log::info('No matching payment found for email', [
            'processed_email_id' => $processedEmailId,
            'extracted_amount' => $extractedInfo['amount'] ?? null,
            'extracted_name' => $extractedInfo['sender_name'] ?? null,
            'extraction_method' => $extractionMethod,
        ]);

        return null;
    }

    /**
     * Extract payment information from email
     * Strategy: Try text_body first, then html_body if text_body fails
     */
    public function extractPaymentInfo(array $emailData): ?array
    {
        $subject = strtolower($emailData['subject'] ?? '');
        $text = $emailData['text'] ?? '';
        $html = $emailData['html'] ?? '';
        $from = strtolower($emailData['from'] ?? '');
        
        $extractionSteps = [];
        $extractionErrors = [];
        
        // Try to find matching bank email template (highest priority)
        $template = $this->findMatchingTemplate($from);
        $extractionSteps[] = 'Template lookup: ' . ($template ? 'Found (' . $template->bank_name . ')' : 'Not found');
        
        // If template found, use template-specific extraction
        if ($template) {
            $result = $this->extractUsingTemplate($emailData, $template);
            if ($result && isset($result['amount']) && $result['amount'] > 0) {
                return [
                    'data' => $result,
                    'method' => 'template',
                ];
            } else {
                $extractionErrors[] = 'Template extraction failed: Amount not found or invalid';
            }
        }
        
        // Strategy: Try text_body first, then html_body
        // STEP 1: Try extraction from text_body (plain text) first
        $textTrimmed = trim($text);
        $textLength = strlen($textTrimmed);
        $extractionSteps[] = "Text body check: " . ($textLength > 0 ? "Present ({$textLength} chars)" : "Empty or missing");
        
        if ($textLength > 0) {
            $extractionResult = $this->extractFromTextBody($text, $subject, $from);
            if ($extractionResult && isset($extractionResult['amount']) && $extractionResult['amount'] > 0) {
                return [
                    'data' => $extractionResult,
                    'method' => 'text_body',
                ];
            } else {
                $amountFound = $extractionResult['amount'] ?? null;
                $extractionErrors[] = "Text body extraction failed: " . 
                    ($amountFound === null ? "No amount found in text body" : 
                     ($amountFound <= 0 ? "Amount found but invalid ({$amountFound})" : "Unknown error"));
            }
        } else {
            $extractionErrors[] = "Text body is empty or whitespace only";
        }
        
        // STEP 2: If text_body extraction failed, try html_body
        $htmlTrimmed = trim($html);
        $htmlLength = strlen($htmlTrimmed);
        $extractionSteps[] = "HTML body check: " . ($htmlLength > 0 ? "Present ({$htmlLength} chars)" : "Empty or missing");
        
        if ($htmlLength > 0) {
            // First try HTML table extraction (most accurate for Nigerian banks)
            $extractionResult = $this->extractFromHtmlBody($html, $subject, $from);
            if ($extractionResult && isset($extractionResult['amount']) && $extractionResult['amount'] > 0) {
                return [
                    'data' => $extractionResult,
                    'method' => $extractionResult['method'] ?? 'html_body',
                ];
            } else {
                $amountFound = $extractionResult['amount'] ?? null;
                $extractionErrors[] = "HTML body extraction failed: " . 
                    ($amountFound === null ? "No amount found in HTML (tried table and text patterns)" : 
                     ($amountFound <= 0 ? "Amount found but invalid ({$amountFound})" : "Unknown error"));
            }
            
            // If HTML table extraction failed, convert HTML to text and try text extraction
            $renderedText = $this->htmlToText($html);
            $renderedLength = strlen(trim($renderedText));
            $extractionSteps[] = "HTML-to-text conversion: {$renderedLength} chars";
            
            if ($renderedLength > 0) {
                $extractionResult = $this->extractFromTextBody($renderedText, $subject, $from);
                if ($extractionResult && isset($extractionResult['amount']) && $extractionResult['amount'] > 0) {
                    return [
                        'data' => $extractionResult,
                        'method' => 'html_rendered_text',
                    ];
                } else {
                    $amountFound = $extractionResult['amount'] ?? null;
                    $extractionErrors[] = "HTML rendered text extraction failed: " . 
                        ($amountFound === null ? "No amount found after converting HTML to text" : 
                         ($amountFound <= 0 ? "Amount found but invalid ({$amountFound})" : "Unknown error"));
                }
            } else {
                $extractionErrors[] = "HTML-to-text conversion produced empty result";
            }
        } else {
            $extractionErrors[] = "HTML body is empty or whitespace only";
        }
        
        // Store extraction diagnostic info for logging
        $this->lastExtractionDiagnostics = [
            'steps' => $extractionSteps,
            'errors' => $extractionErrors,
            'text_length' => $textLength,
            'html_length' => $htmlLength,
            'subject' => $emailData['subject'] ?? '',
            'from' => $emailData['from'] ?? '',
            'text_preview' => mb_substr($textTrimmed, 0, 500),
            'html_preview' => mb_substr($htmlTrimmed, 0, 500),
        ];
        
        // If both text_body and html_body extraction failed, return null
            return null;
        }

    /**
     * Store last extraction diagnostics for error reporting
     */
    protected $lastExtractionDiagnostics = null;
    
    /**
     * Get last extraction diagnostics
     */
    public function getLastExtractionDiagnostics(): ?array
    {
        return $this->lastExtractionDiagnostics;
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
                    'time_diff_minutes' => -$timeDiff, // Negative indicates before
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

        // Check amount match with new rules:
        // - If received amount is N500 or more LOWER than expected → FAIL (reject)
        // - If received amount is less than N500 lower → Approve but mark as mismatch
        $expectedAmount = $payment->amount;
        $receivedAmount = $extractedInfo['amount'];
        $amountDiff = $expectedAmount - $receivedAmount; // Positive if received is lower
        
        // Small tolerance for rounding (1 kobo)
        $amountTolerance = 0.01;
        
        // If received amount is significantly lower (N500 or more)
        if ($amountDiff >= 500) {
            return [
                'matched' => false,
                'reason' => sprintf(
                    'Amount mismatch: expected ₦%s, received ₦%s (difference: ₦%s). Manual resettlement required.',
                    number_format($expectedAmount, 2),
                    number_format($receivedAmount, 2),
                    number_format($amountDiff, 2)
                ),
                'should_reject' => true, // Flag to reject payment
                'amount_diff' => $amountDiff,
                'time_diff_minutes' => $timeDiff,
            ];
        }
        
        // If received amount is higher or difference is less than N500
        // We'll approve but mark as mismatch if difference > tolerance
        $isMismatch = false;
        $mismatchReason = null;
        $finalReceivedAmount = null;
        
        if ($amountDiff > $amountTolerance && $amountDiff < 500) {
            // Amount is lower but within tolerance (less than N500 difference)
            $isMismatch = true;
            $finalReceivedAmount = $receivedAmount;
            $mismatchReason = sprintf(
                'Amount mismatch: expected ₦%s, received ₦%s (difference: ₦%s). Payment approved with mismatch flag.',
                number_format($expectedAmount, 2),
                number_format($receivedAmount, 2),
                number_format($amountDiff, 2)
            );
        } elseif ($receivedAmount > $expectedAmount + $amountTolerance) {
            // Received more than expected (overpayment)
            $isMismatch = true;
            $finalReceivedAmount = $receivedAmount;
            $mismatchReason = sprintf(
                'Amount mismatch: expected ₦%s, received ₦%s (overpayment: ₦%s). Payment approved with mismatch flag.',
                number_format($expectedAmount, 2),
                number_format($receivedAmount, 2),
                number_format($receivedAmount - $expectedAmount, 2)
            );
        }

        // If payer name is provided, check similarity (not exact match)
        $nameSimilarityPercent = null;
        if ($payment->payer_name) {
            if (empty($extractedInfo['sender_name'])) {
                return [
                    'matched' => false,
                    'reason' => 'Payer name required but not found in email',
                    'amount_diff' => $amountDiff,
                    'time_diff_minutes' => $timeDiff,
                    'name_similarity_percent' => 0,
                ];
            }

            // Normalize names for comparison
            $expectedName = trim(strtolower($payment->payer_name));
            $expectedName = preg_replace('/\s+/', ' ', $expectedName);
            $receivedName = trim(strtolower($extractedInfo['sender_name']));
            $receivedName = preg_replace('/\s+/', ' ', $receivedName);

            // Check if names match with similarity (handles order variations and partial matches)
            $matchResult = $this->namesMatch($expectedName, $receivedName);
            $nameSimilarityPercent = $matchResult['similarity'];
            
            if (!$matchResult['matched']) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Name mismatch: expected "%s", got "%s" (similarity: %d%%)',
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

        return [
            'matched' => true,
            'reason' => $isMismatch ? $mismatchReason : 'Amount and name match within time window',
            'is_mismatch' => $isMismatch,
            'received_amount' => $finalReceivedAmount,
            'mismatch_reason' => $mismatchReason,
            'amount_diff' => $amountDiff,
            'time_diff_minutes' => $timeDiff,
            'name_similarity_percent' => $nameSimilarityPercent,
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
     * Check if two names match with 65% similarity
     * 
     * Examples:
     * - "amithy one media" matches "amithy one" (2 out of 3 words = 67%, but we check if major words match)
     * - "innocent solomon" matches "solomon innocent amithy" (all words present)
     * - "solomon innocent" matches "innocent solomon" (order variation)
     * 
     * @param string $expectedName The name from payment request (e.g., "amithy one media")
     * @param string $receivedName The name extracted from email (e.g., "amithy one" or longer description)
     * @return array ['matched' => bool, 'similarity' => int] Returns match result and similarity percentage
     */
    protected function namesMatch(string $expectedName, string $receivedName): array
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
                // Handles cases like "amithy" matching "amithy" in "amithy one"
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
        // Example: "amithy one media" (3 words) needs at least 2 words to match (67% >= 65%)
        $matched = $similarityPercent >= 65;
        
        return ['matched' => $matched, 'similarity' => $similarityPercent];
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
     * Find matching bank email template for sender email
     */
    protected function findMatchingTemplate(string $fromEmail): ?\App\Models\BankEmailTemplate
    {
        // Get active templates ordered by priority
        $templates = \App\Models\BankEmailTemplate::active()->get();
        
        foreach ($templates as $template) {
            if ($template->matchesEmail($fromEmail)) {
                return $template;
            }
        }
        
        return null;
    }

    /**
     * Extract payment info using bank email template
     */
    protected function extractUsingTemplate(array $emailData, \App\Models\BankEmailTemplate $template): ?array
    {
        $subject = strtolower($emailData['subject'] ?? '');
        $text = $emailData['text'] ?? '';
        $html = $emailData['html'] ?? '';
        
        // If text body is empty but HTML exists, extract text from HTML
        if (empty(trim($text)) && !empty($html)) {
            $text = $this->htmlToText($html);
        }
        
        $text = strtolower($text);
        $htmlLower = strtolower($html);
        $fullText = $subject . ' ' . $text . ' ' . $htmlLower;
        
        $amount = null;
        $senderName = null;
        $accountNumber = null;
        
        // Extract amount using template
        if ($template->amount_pattern) {
            // Use custom regex pattern
            if (preg_match($template->amount_pattern, $html ?: $fullText, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1] ?? $matches[0]);
            }
        } elseif ($template->amount_field_label) {
            // Use field label to find in HTML table
            $label = preg_quote($template->amount_field_label, '/');
            if (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦)?\s*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
            }
        }
        
        // Extract sender name using template
        if ($template->sender_name_pattern) {
            // Use custom regex pattern
            if (preg_match($template->sender_name_pattern, $html ?: $fullText, $matches)) {
                $senderName = trim(strtolower($matches[1] ?? $matches[0]));
            }
        } elseif ($template->sender_name_field_label) {
            // Use field label to find in HTML table
            $label = preg_quote($template->sender_name_field_label, '/');
            // Try Description field with "FROM NAME TO" format
            if (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
                $senderName = trim(strtolower($matches[1]));
            }
            // Try standard format
            elseif (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s]*([A-Z][A-Z\s]+?)[\s]*<\/td>/i', $html, $matches)) {
                $senderName = trim(strtolower($matches[1]));
            }
        }
        
        // Extract account number using template
        if ($template->account_number_pattern) {
            if (preg_match($template->account_number_pattern, $html ?: $fullText, $matches)) {
                $accountNumber = trim($matches[1] ?? $matches[0]);
            }
        } elseif ($template->account_number_field_label) {
            $label = preg_quote($template->account_number_field_label, '/');
            if (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d+)[\s]*<\/td>/i', $html, $matches)) {
                $accountNumber = trim($matches[1]);
            }
        }
        
        // If no amount found, return null
        if (!$amount || $amount < 10) {
            return null;
        }
        
        return [
            'amount' => $amount,
            'sender_name' => $senderName,
            'account_number' => $accountNumber,
            'email_subject' => $emailData['subject'] ?? '',
            'email_from' => $emailData['from'] ?? '',
            'extracted_at' => now()->toISOString(),
            'template_used' => $template->bank_name,
        ];
    }

    /**
     * Extract payment info from text_body (plain text)
     */
    protected function extractFromTextBody(string $text, string $subject, string $from): ?array
    {
        $textLower = strtolower($text);
        $fullText = $subject . ' ' . $textLower;
        
        $amount = null;
        $senderName = null;
        
        // Extract amount from text
        $amountPatterns = [
            '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)/i',
            '/(?:ngn|naira|₦)\s*([\d,]+\.?\d*)/i',
            '/([\d,]+\.?\d*)\s*(?:naira|ngn|usd|dollar)/i',
        ];
        
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    break;
                }
            }
        }
        
        // Extract sender name from text
        // Pattern 1: "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        if (preg_match('/from\s+([A-Z][A-Z\s]+?)\s+to/i', $text, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 2: GTBank description with "NAME TRF FOR" format
        // Format: "090405260110014006799532206126-AMITHY ONE M TRF FOR CUSTOMER..."
        elseif (preg_match('/description[\s:]+.*?[\s\-]*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: Other standard patterns in text
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i', $fullText, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        
        // Clean up sender name
        if ($senderName) {
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            if (strlen($senderName) < 3) {
                $senderName = null;
            }
        }
        
        // Try extracting from email sender if no name found
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
        ];
    }
    
    /**
     * Extract payment info from html_body
     */
    protected function extractFromHtmlBody(string $html, string $subject, string $from): ?array
    {
        $htmlLower = strtolower($html);
        $fullText = $subject . ' ' . $htmlLower;
        
        $amount = null;
        $senderName = null;
        $method = null;
        
        // PRIMARY: HTML table extraction (most accurate for Nigerian banks)
        // Pattern 1: GTBank HTML table - Amount in separate cell after label (handles &nbsp;)
        if (preg_match('/<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦)[\s&nbsp;]*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
            $method = 'html_table';
        }
        // Pattern 2: GTBank HTML table - Amount in same cell with label (handles &nbsp;)
        elseif (preg_match('/<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]+(?:ngn|naira|₦)[\s&nbsp;]*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
            $method = 'html_table';
        }
        // Pattern 3: Description field contains amount
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>.*?(?:ngn|naira|₦)\s*([\d,]+\.?\d*).*?<\/td>/i', $html, $matches)) {
            $potentialAmount = (float) str_replace(',', '', $matches[1]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
                $method = 'html_table';
            }
        }
        // Pattern 4: Any HTML table cell containing NGN/Naira
        elseif (preg_match('/<td[^>]*>[\s]*(?:ngn|naira|₦)\s*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
            $potentialAmount = (float) str_replace(',', '', $matches[1]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
                $method = 'html_table';
            }
        }
        // Pattern 5: HTML with amount format (not in table)
        elseif (preg_match('/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)/i', $html, $matches)) {
            $potentialAmount = (float) str_replace(',', '', $matches[1]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
                $method = 'html_text';
            }
        }
        // Pattern 6: Standalone NGN in HTML
        elseif (preg_match('/(?:ngn|naira|₦)([\d,]+\.?\d*)/i', $html, $matches)) {
            $potentialAmount = (float) str_replace(',', '', $matches[1]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
                $method = 'html_text';
            }
        }
        
        // Extract sender name from HTML
        // Pattern 1: GTBank HTML table - Description field contains "FROM NAME TO"
        if (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 2: GTBank HTML table - Description field contains "AMITHY ONE M TRF FOR" (new format)
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>.*?[\s\-]*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: GTBank HTML table - Description in same cell with "FROM"
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]+from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 4: GTBank text format "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        elseif (preg_match('/from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 5: HTML table with "From" or "Sender" label
        elseif (preg_match('/<td[^>]*>[\s]*(?:from|sender|payer|depositor|account\s*name|name)[\s:]*<\/td>\s*<td[^>]*>[\s]*([A-Z][A-Z\s]+?)[\s]*<\/td>/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 6: Standard patterns in HTML
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|<\/)/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        
        // Clean up sender name
        if ($senderName) {
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            if (strlen($senderName) < 3) {
                $senderName = null;
            }
        }
        
        // Try extracting from email sender if no name found
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
            'method' => $method ?? 'html_body',
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
