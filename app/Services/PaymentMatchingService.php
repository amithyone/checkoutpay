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
    protected ?PythonExtractionService $pythonExtractor = null;

    public function __construct(?TransactionLogService $transactionLogService = null)
    {
        $this->matchLogger = new MatchAttemptLogger();
        
        // Initialize Python extraction service if enabled
        if (config('services.python_extractor.enabled', true)) {
            try {
                $this->pythonExtractor = new PythonExtractionService();
                // Check if service is available (cache result for 1 minute)
                if (!$this->pythonExtractor->isAvailable()) {
                    \Illuminate\Support\Facades\Log::warning('Python extraction service is not available, falling back to PHP extraction');
                    $this->pythonExtractor = null;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to initialize Python extraction service', [
                    'error' => $e->getMessage(),
                ]);
                $this->pythonExtractor = null;
            }
        }
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

        \Illuminate\Support\Facades\Log::info('Extracted payment info from email', array_merge($extractedInfo, [
            'extraction_method' => $extractionMethod,
            'has_account_number' => !empty($extractedInfo['account_number'] ?? null),
            'account_number' => $extractedInfo['account_number'] ?? 'NULL',
            'has_payer_account_number' => !empty($extractedInfo['payer_account_number'] ?? null),
            'payer_account_number' => $extractedInfo['payer_account_number'] ?? 'NULL',
        ]));

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
        $payment = $this->matchFromStoredEmails($extractedInfo, $emailData['email_account_id'] ?? null, $processedEmailId, $emailData, $extractionMethod);
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
                
                // Update payer_account_number if extracted
                if (isset($extractedInfo['payer_account_number']) && $extractedInfo['payer_account_number']) {
                    $payment->update(['payer_account_number' => $extractedInfo['payer_account_number']]);
                    $payment->refresh(); // Refresh to get updated data
                }

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
     * Strategy: Try Python extraction service first (if available), then fallback to PHP extraction
     */
    public function extractPaymentInfo(array $emailData): ?array
    {
        // PRIORITY 1: Try Python extraction service (if enabled and available)
        if ($this->pythonExtractor !== null) {
            try {
                $result = $this->pythonExtractor->extractPaymentInfo($emailData);
                if ($result) {
                    \Illuminate\Support\Facades\Log::info('Payment info extracted using Python service', [
                        'method' => $result['method'] ?? 'python_extractor',
                        'confidence' => $result['confidence'] ?? null,
                        'email_id' => $emailData['processed_email_id'] ?? null,
                    ]);
                    return $result;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Python extraction failed, falling back to PHP', [
                    'error' => $e->getMessage(),
                    'email_id' => $emailData['processed_email_id'] ?? null,
                ]);
                // Fall through to PHP extraction
            }
        }
        
        // PRIORITY 2: Fallback to PHP extraction (existing logic)
        return $this->extractPaymentInfoPhp($emailData);
    }

    /**
     * Extract payment information using PHP (fallback when Python is unavailable).
     * Strategy: Try text_body first, then html_body if text_body fails
     */
    protected function extractPaymentInfoPhp(array $emailData): ?array
    {
        $subject = strtolower($emailData['subject'] ?? '');
        $text = $this->decodeQuotedPrintable($emailData['text'] ?? '');
        $html = $this->decodeQuotedPrintable($emailData['html'] ?? '');
        // CRITICAL: Decode HTML entities (like &nbsp; to space) for stored emails
        // Stored emails may have NGN&nbsp;1000 which should be NGN 1000
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
        
        // Store extraction diagnostic info for logging (note: text/html are already decoded here)
        // Sanitize previews to ensure valid UTF-8 for JSON encoding
        $textPreview = mb_substr($textTrimmed, 0, 1000);
        $htmlPreview = mb_substr($htmlTrimmed, 0, 1000);
        
        // Clean UTF-8 for previews (they'll be stored in JSON)
        $textPreview = $this->cleanUtf8ForJson($textPreview);
        $htmlPreview = $this->cleanUtf8ForJson($htmlPreview);
        
        $this->lastExtractionDiagnostics = [
            'steps' => $extractionSteps,
            'errors' => $extractionErrors,
            'text_length' => $textLength,
            'html_length' => $htmlLength,
            'subject' => $emailData['subject'] ?? '',
            'from' => $emailData['from'] ?? '',
            'text_preview' => $textPreview, // Sanitized for JSON
            'html_preview' => $htmlPreview, // Sanitized for JSON
            // Check if HTML contains table tags (for diagnostics)
            'html_has_table_tags' => strpos($htmlTrimmed, '<td') !== false || strpos($htmlTrimmed, '<table') !== false,
            // Check if HTML contains amount keywords
            'html_has_amount_keywords' => preg_match('/(?:amount|ngn|naira)/i', $htmlTrimmed),
            // Check if text contains amount keywords
            'text_has_amount_keywords' => preg_match('/(?:amount|ngn|naira)/i', $textTrimmed),
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

        // CRITICAL: Validate account number match - payment account_number must match recipient account from description field
        // The recipient account is the account number where the payment was sent TO (positions 0-9 in description field)
        // IMPORTANT: The first 10 digits of description field is ALWAYS the recipient account, even if Account Number field is missing
        if ($payment->account_number) {
            $expectedAccount = trim($payment->account_number);
            
            // Check if account_number was extracted (from Account Number field or description field)
            if (isset($extractedInfo['account_number']) && $extractedInfo['account_number']) {
                $extractedRecipientAccount = trim($extractedInfo['account_number']);
                
                if ($expectedAccount !== $extractedRecipientAccount) {
                    return [
                        'matched' => false,
                        'reason' => sprintf(
                            'Account number mismatch: payment expects account "%s", but email shows recipient account "%s". Payment cannot be approved.',
                            $expectedAccount,
                            $extractedRecipientAccount
                        ),
                        'account_number_mismatch' => true,
                        'expected_account' => $expectedAccount,
                        'extracted_account' => $extractedRecipientAccount,
                    ];
                }
            } else {
                // Account number not found in email - this should not happen if description field exists
                // But we should still try to extract from description field if it wasn't extracted yet
                // This is a fallback safety check
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Account number required but not found in email. Payment expects account "%s". Description field should contain recipient account as first 10 digits.',
                        $expectedAccount
                    ),
                    'account_number_missing' => true,
                    'expected_account' => $expectedAccount,
                ];
            }
        }

        // Calculate amount difference
        $expectedAmount = $payment->amount;
        $receivedAmount = $extractedInfo['amount'];
        $amountDiff = $expectedAmount - $receivedAmount; // Positive if received is lower
        $amountTolerance = 0.01; // Small tolerance for rounding (1 kobo)
        
        // NEW STRATEGY: Check name first, then amount
        // If name matches, we're more lenient with amount mismatches
        $nameSimilarityPercent = null;
        $nameMatches = false;
        
        // If payer name is provided, check similarity first
        if ($payment->payer_name) {
            if (empty($extractedInfo['sender_name'])) {
                // Name required but not found - check amount strictly
                if (abs($amountDiff) > $amountTolerance) {
                    return [
                        'matched' => false,
                        'reason' => 'Payer name required but not found in email, and amount mismatch',
                        'amount_diff' => $amountDiff,
                        'time_diff_minutes' => $timeDiff,
                        'name_similarity_percent' => 0,
                    ];
                }
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
        // If name doesn't match (or not provided), we require exact amount match
        if ($nameMatches) {
            // Name matches - be lenient with amount (allow up to N5000 difference)
            $maxAmountDiff = 5000; // Allow up to N5000 difference when name matches
            
            if ($amountDiff >= $maxAmountDiff) {
                // Amount is too low even with name match
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
                    // Received less than expected
                    $mismatchReason = sprintf(
                        'Amount mismatch: expected ₦%s, received ₦%s (difference: ₦%s). Payment approved because name matches.',
                        number_format($expectedAmount, 2),
                        number_format($receivedAmount, 2),
                        number_format($amountDiff, 2)
                    );
                } else {
                    // Received more than expected (overpayment)
                    $mismatchReason = sprintf(
                        'Amount mismatch: expected ₦%s, received ₦%s (overpayment: ₦%s). Payment approved because name matches.',
                        number_format($expectedAmount, 2),
                        number_format($receivedAmount, 2),
                        number_format(abs($amountDiff), 2)
                    );
                }
            }
        } else {
            // Name doesn't match or not provided - require exact amount match
            if (abs($amountDiff) > $amountTolerance) {
                return [
                    'matched' => false,
                    'reason' => sprintf(
                        'Amount mismatch: expected ₦%s, received ₦%s (difference: ₦%s). Name does not match, so exact amount required.',
                        number_format($expectedAmount, 2),
                        number_format($receivedAmount, 2),
                        number_format(abs($amountDiff), 2)
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
    protected function matchFromStoredEmails(array $extractedInfo, ?int $emailAccountId = null, ?int $processedEmailId = null, ?array $emailData = null, ?string $extractionMethod = null): ?Payment
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
                $extractionResult = $this->extractPaymentInfo($emailData);
                
                // extractPaymentInfo can return null if extraction fails
                if (!$extractionResult || !is_array($extractionResult)) {
                    continue;
                }
                
                $extractedInfo = $extractionResult['data'] ?? null;
                
                if (!$extractedInfo || !isset($extractedInfo['amount']) || !$extractedInfo['amount']) {
                    continue;
                }
                
                $match = $this->matchPayment($payment, $extractedInfo, $storedEmail->email_date);
                
                // Log match attempt to database
                try {
                    $this->matchLogger->logAttempt([
                        'payment_id' => $payment->id,
                        'processed_email_id' => $storedEmail->id,
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
                        'email_subject' => $storedEmail->subject,
                        'email_from' => $storedEmail->from_email,
                        'email_date' => $storedEmail->email_date,
                        'amount_diff' => $match['amount_diff'] ?? null,
                        'name_similarity_percent' => $match['name_similarity_percent'] ?? null,
                        'time_diff_minutes' => $match['time_diff_minutes'] ?? null,
                        'extraction_method' => $extractionResult['method'] ?? 'unknown',
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
                        'html_snippet' => $this->matchLogger->extractHtmlSnippet($storedEmail->html_body ?? '', $extractedInfo['amount'] ?? null),
                        'text_snippet' => $this->matchLogger->extractTextSnippet($storedEmail->text_body ?? '', $extractedInfo['amount'] ?? null),
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to log match attempt in matchFromStoredEmails', [
                        'error' => $e->getMessage(),
                        'transaction_id' => $payment->transaction_id,
                        'stored_email_id' => $storedEmail->id,
                    ]);
                }
                
                if ($match['matched']) {
                    // Mark stored email as matched
                    $storedEmail->markAsMatched($payment);
                    
                    // Update payer_account_number if extracted
                    if (isset($extractedInfo['payer_account_number']) && $extractedInfo['payer_account_number']) {
                        $payment->update(['payer_account_number' => $extractedInfo['payer_account_number']]);
                        $payment->refresh(); // Refresh to get updated data
                    }
                    
                    \Illuminate\Support\Facades\Log::info('Payment matched from stored email', [
                        'transaction_id' => $payment->transaction_id,
                        'stored_email_id' => $storedEmail->id,
                        'match_reason' => $match['reason'],
                        'match_attempt_logged' => true,
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
        
        $extractionResult = $this->extractPaymentInfo($emailData);
        
        // Handle extraction result format: ['data' => [...], 'method' => '...']
        // extractPaymentInfo can return null if extraction fails
        if (!$extractionResult || !is_array($extractionResult)) {
            return [
                'success' => false,
                'message' => 'Could not extract payment information from email',
                'matches' => [],
            ];
        }
        
        $extractedInfo = $extractionResult['data'] ?? null;
        $extractionMethod = $extractionResult['method'] ?? 'unknown';
        
        if (!$extractedInfo || !isset($extractedInfo['amount']) || !$extractedInfo['amount']) {
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
            
            // Log match attempt to database
            try {
                $this->matchLogger->logAttempt([
                    'payment_id' => $payment->id,
                    'processed_email_id' => $storedEmail->id,
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
                    'email_subject' => $storedEmail->subject,
                    'email_from' => $storedEmail->from_email,
                    'email_date' => $storedEmail->email_date,
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
                    'html_snippet' => $this->matchLogger->extractHtmlSnippet($storedEmail->html_body ?? '', $extractedInfo['amount'] ?? null),
                    'text_snippet' => $this->matchLogger->extractTextSnippet($storedEmail->text_body ?? '', $extractedInfo['amount'] ?? null),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log match attempt in recheckStoredEmail', [
                    'error' => $e->getMessage(),
                    'transaction_id' => $payment->transaction_id,
                    'email_id' => $storedEmail->id,
                ]);
            }
            
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
        $subject = $emailData['subject'] ?? '';
        $text = $emailData['text'] ?? '';
        $html = $emailData['html'] ?? '';
        
        // Decode quoted-printable and HTML entities FIRST
        $text = $this->decodeQuotedPrintable($text);
        $html = $this->decodeQuotedPrintable($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // If text body is empty but HTML exists, extract text from HTML
        if (empty(trim($text)) && !empty($html)) {
            $text = $this->htmlToText($html);
        }
        
        $amount = null;
        $senderName = null;
        $accountNumber = null;
        $payerAccountNumber = null;
        $transactionTime = null;
        $extractedDate = null;
        
        // STRATEGY 1: Try TEXT-based extraction first (for forwarded emails in plain text)
        // GTBank format from processed_emails text_body column:
        // "Account Number : 3002156642"
        // "Amount : NGN 1000"
        // "Time of Transaction : 12:17:27 AM"
        // "Description : 9008771210 021008599511000020260111080847554 FROM SOLOMON INNOCENT AMITHY TO SQUA"
        // Description field format: recipient_account(10) sender_account(10) amount_without_decimal(6) date_YYYYMMDD(8) unknown(9) FROM NAME TO NAME
        if (!empty(trim($text))) {
            // Normalize whitespace (handle newlines and multiple spaces)
            $normalizedText = preg_replace('/\s+/', ' ', $text);
            
            // Extract Amount from text: "Amount : NGN 1000" format
            // Pattern handles: "Amount : NGN 1000", "Amount: NGN 1000", "Amount :NGN 1000"
            if (preg_match('/amount[\s]*:[\s]*(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)/i', $normalizedText, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
            }
            // Fallback: Just look for "NGN" followed by number after "Amount"
            elseif (preg_match('/amount[\s:]+.*?(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)/i', $normalizedText, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
            }
            
            // Extract Time of Transaction: "Time of Transaction : 12:17:27 AM" format
            if (preg_match('/time\s+of\s+transaction[\s]*:[\s]*([\d:APM\s]+)/i', $normalizedText, $matches)) {
                $transactionTime = trim($matches[1]);
            }
            
            // PRIORITY: Extract from Description field FIRST - This is the PRIMARY source for recipient account number
            // The first 10 digits of description field is ALWAYS the recipient account number (where payment was sent TO)
            // Format: "Description : 9008771210 021008599511000020260111080847554 FROM SOLOMON INNOCENT AMITHY TO SQUA"
            // Structure: recipient_account(10) [space] sender_account(10)amount(6)date(8)unknown(9) FROM NAME TO NAME
            // Or without space: recipient_account(10)sender_account(10)amount(6)date(8)unknown(9) FROM NAME TO NAME
            // IMPORTANT: Description field is MORE RELIABLE than "Account Number" field, so we prioritize it
            if (preg_match('/description[\s]*:[\s]*(\d{10})[\s]*(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i', $normalizedText, $matches)) {
                // Match 1: recipient account (10 digits) - PRIMARY source for account_number
                $accountNumber = trim($matches[1]); // Always use description field as PRIMARY source
                // Match 2: sender/payer account (10 digits)
                $payerAccountNumber = trim($matches[2]);
                
                // Match 3: amount without decimal (6 digits) - backup extraction
                $amountFromDesc = (float) ($matches[3] / 100); // Divide by 100 to get actual amount
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                
                // Match 4: date YYYYMMDD (8 digits) - backup extraction
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3]; // Format: YYYY-MM-DD
                }
                
                // Match 5: sender name (FROM NAME TO)
                $senderName = trim(strtolower($matches[6]));
            }
            // Alternative format: with dash/separator before FROM
            // Format: "Description : 090405260110001723439231932126-AMITHY ONE M TRF FOR CUSTOMERAT126TRF2MPT4E0RT200"
            // IMPORTANT: First 10 digits is the recipient account number - PRIMARY source
            elseif (preg_match('/description[\s]*:[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[\d\-]*\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $normalizedText, $matches)) {
                // Match 1: recipient account (10 digits) - PRIMARY source for account_number
                $accountNumber = trim($matches[1]); // Always use description field as PRIMARY source
                // Match 2: sender/payer account (10 digits)
                $payerAccountNumber = trim($matches[2]);
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                $potentialName = trim($matches[6]);
                $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern with flexible code length before name (fallback - old format)
            elseif (preg_match('/description[\s]*:[\s]*.*?(\d{10})(\d{10})[\d\-]*\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $normalizedText, $matches)) {
                $payerAccountNumber = trim($matches[2]);
                $potentialName = trim($matches[3]);
                $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern: Direct format in text (without "Description :" prefix) - try to extract account numbers
            // IMPORTANT: First 10 digits is the recipient account number - PRIMARY source
            elseif (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i', $normalizedText, $matches)) {
                // Match 1: recipient account (10 digits) - PRIMARY source for account_number
                $accountNumber = trim($matches[1]); // Always use description field as PRIMARY source
                // Match 2: sender/payer account (10 digits)
                $payerAccountNumber = trim($matches[2]);
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                $senderName = trim(strtolower($matches[6]));
            }
            // Pattern: "FROM NAME TO" format (fallback)
            elseif (preg_match('/from\s+([A-Z][A-Z\s]+?)\s+to/i', $normalizedText, $matches)) {
                $senderName = trim(strtolower($matches[1]));
            }
            
            // FALLBACK: Extract Account Number from "Account Number" field ONLY if description extraction failed
            // Description field is PRIMARY source and should always be used when available
            if (!$accountNumber && preg_match('/account\s*number[\s]*:[\s]*(\d+)/i', $normalizedText, $matches)) {
                $accountNumber = trim($matches[1]);
            }
        }
        
        // STRATEGY 2: Try HTML-based extraction (for original HTML emails)
        if ((!$amount || !$accountNumber) && !empty($html)) {
            // PRIORITY: Extract account number from Description field FIRST in HTML (before Account Number field)
            // Description field format: <td>Description</td><td>:</td><td colspan="8">900877121002100859959000020260111094651392 FROM SOLOMON INNOCENT AMITHY TO SQUAD</td>
            // Format: recipient_account(10) sender_account(10) amount(6) date(8) unknown(9) FROM NAME TO NAME
            // IMPORTANT: First 10 digits is ALWAYS the recipient account number (where payment was sent TO)
            // Note: HTML structure may have a colon cell (<td>:</td>) between label and value
            
            // Pattern 1: Description field WITHOUT space between accounts, with optional colon cell (MOST COMMON, FLEXIBLE)
            // Format: <td>Description</td><td>:</td><td colspan="8">900877121002100859959000020260111094651392 FROM...</td>
            // Uses .*? for flexible matching to handle any HTML attributes/whitespace
            if (!$accountNumber && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>[\s\n\r]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[\s\n\r]+FROM[\s\n\r]+([A-Z\s]+?)[\s\n\r]+TO/i', $html, $matches)) {
                $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
                $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                if (!$senderName) {
                    $senderName = trim(strtolower($matches[6]));
                }
            }
            // Pattern 2: Description field WITHOUT colon cell (direct next cell) - FLEXIBLE
            // Format: <td>Description</td><td>900877121002100859959000020260111094651392 FROM...</td>
            elseif (!$accountNumber && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s\n\r]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[\s\n\r]+FROM[\s\n\r]+([A-Z\s]+?)[\s\n\r]+TO/i', $html, $matches)) {
                $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
                $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                if (!$senderName) {
                    $senderName = trim(strtolower($matches[6]));
                }
            }
            // Pattern 3: Description field with space between accounts
            elseif (!$accountNumber && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})[\s]+(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i', $html, $matches)) {
                $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
                $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                if (!$senderName) {
                    $senderName = trim(strtolower($matches[6]));
                }
            }
            // Pattern 4: Description field with dash separator (old format)
            elseif (!$accountNumber && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[\d\-]*\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
                $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
                $payerAccountNumber = trim($matches[2]);
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                if (!$senderName) {
                    $potentialName = trim($matches[6]);
                    $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
                    if (strlen($potentialName) >= 3) {
                        $senderName = trim(strtolower($potentialName));
                    }
                }
            }
            
            // Extract amount from HTML table
            if (!$amount && $template->amount_pattern) {
                if (preg_match($template->amount_pattern, $html, $matches)) {
                    $amount = (float) str_replace(',', '', $matches[1] ?? $matches[0]);
                }
            }
            
            if (!$amount && $template->amount_field_label) {
                $label = preg_quote($template->amount_field_label, '/');
                // HTML table format: <td>Amount</td><td>:</td><td>NGN 1000</td>
                if (preg_match('/(?s)<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                    $amount = (float) str_replace(',', '', $matches[1]);
                }
                // Same cell format: <td>Amount: NGN 1000</td>
                elseif (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                    $amount = (float) str_replace(',', '', $matches[1]);
                }
            }
            
            // FALLBACK: Extract account number from "Account Number" field ONLY if description extraction failed
            if (!$accountNumber && $template->account_number_pattern) {
                if (preg_match($template->account_number_pattern, $html, $matches)) {
                    $accountNumber = trim($matches[1] ?? $matches[0]);
                }
            }
            
            if (!$accountNumber && $template->account_number_field_label) {
                $label = preg_quote($template->account_number_field_label, '/');
                // HTML table format: <td>Account Number</td><td>:</td><td>3002156642</td>
                if (preg_match('/(?s)<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d+)[\s]*<\/td>/i', $html, $matches)) {
                    $accountNumber = trim($matches[1]);
                }
                // Same cell format: <td>Account Number: 3002156642</td>
                elseif (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]+(\d+)[\s]*<\/td>/i', $html, $matches)) {
                    $accountNumber = trim($matches[1]);
                }
            }
            
            // Extract sender name from HTML Description field
            if (!$senderName && $template->sender_name_pattern) {
                if (preg_match($template->sender_name_pattern, $html, $matches)) {
                    $senderName = trim(strtolower($matches[1] ?? $matches[0]));
                }
            }
            
            if (!$senderName && $template->sender_name_field_label) {
                $label = preg_quote($template->sender_name_field_label, '/');
                // Pattern: Description field contains "CODE-NAME TRF FOR"
                if (preg_match('/(?s)<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>.*?([\d\-]+\s*-\s*)?([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
                    $potentialName = trim($matches[2] ?? $matches[1] ?? '');
                    $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
                    if (strlen($potentialName) >= 3) {
                        $senderName = trim(strtolower($potentialName));
                    }
                }
                // Pattern: "FROM NAME TO" format
                elseif (preg_match('/<td[^>]*>[\s]*' . $label . '[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
                    $senderName = trim(strtolower($matches[1]));
                }
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
            'payer_account_number' => $payerAccountNumber,
            'transaction_time' => $transactionTime,
            'extracted_date' => $extractedDate,
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
        $accountNumber = null;
        $senderName = null;
        $payerAccountNumber = null;
        $transactionTime = null;
        $extractedDate = null;
        
        // PRIORITY: Extract account number from Description field FIRST (before any other extraction)
        // Description field format: "Description : 9008771210 021008599511000020260111080847554 FROM SOLOMON INNOCENT AMITHY TO SQUA"
        // Format: recipient_account(10) sender_account(10) amount_without_decimal(6) date_YYYYMMDD(8) unknown(9) FROM NAME TO NAME
        // IMPORTANT: First 10 digits is ALWAYS the recipient account number (where payment was sent TO)
        // CRITICAL: Use flexible pattern that handles space or no space between account numbers
        // Pattern 1: Flexible - handles space OR no space between first and second 10-digit numbers
        // Format: "Description : 9008771210 021008599511000020260111080847554 FROM..." (with space)
        // OR: "Description : 900877121002100859959000020260111094651392 FROM..." (without space)
        // CRITICAL: Allow flexible spacing/characters between digits and FROM to handle formatting variations
        if (preg_match('/description[\s]*:[\s]*(\d{10})[\s]*(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM\s+([A-Z\s]+?)\s+TO/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
            $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
            $amountFromDesc = (float) ($matches[3] / 100); // Divide by 100 to get actual amount
            if ($amountFromDesc >= 10) {
                $amount = $amountFromDesc;
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $senderName = trim(strtolower($matches[6]));
        }
        // Pattern 2: Without space between accounts (all 43 digits together) - also flexible
        // Format: "Description : 900877121002100859959000020260111094651392 FROM..."
        // CRITICAL: Make TO optional - text might be truncated or not have TO
        elseif (preg_match('/description[\s]*:[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s+TO|$)/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if ($amountFromDesc >= 10) {
                $amount = $amountFromDesc;
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $senderName = trim(strtolower($matches[6]));
        }
        // Pattern 3: Direct format without "Description :" prefix (fallback - very flexible)
        // Format: "9008771210 021008599511000020260111080847554 FROM SOLOMON..." (with space)
        // OR: "900877121002100859959000020260111094651392 FROM SOLOMON..." (without space)
        // CRITICAL: This pattern allows ANY characters (including spaces, dashes, etc.) between digits and FROM
        // CRITICAL: Make TO optional - text might be truncated or not have TO
        elseif (preg_match('/(\d{10})[\s]*(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s+TO|$)/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if ($amountFromDesc >= 10) {
                $amount = $amountFromDesc;
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $senderName = trim(strtolower($matches[6]));
        }
        
        // Extract amount from text (case insensitive, flexible patterns) - only if not already extracted
        $amountPatterns = [
            '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦|NGN)[\s]*([\d,]+\.?\d*)/i',
            '/(?:ngn|naira|₦|NGN)[\s]*([\d,]+\.?\d*)/i',
            '/([\d,]+\.?\d*)[\s]*(?:naira|ngn|usd|dollar|NGN)/i',
            // Pattern for format: "Amount\t:\tNGN 1000" (tab separated)
            '/amount[\s\t:]+(?:ngn|naira|₦|NGN)[\s\t]*([\d,]+\.?\d*)/i',
            // Pattern for format: "Amount: NGN  1000" (multiple spaces)
            '/amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)/i',
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
        // Pattern 2: GTBank description with "CODE-NAME TRF FOR" format
        // Format: "Description: =20 090405260110014006799532206126-AMITHY ONE M TRF FOR..."
        // Note: Text is already decoded from quoted-printable by decodeQuotedPrintable()
        elseif (preg_match('/description[\s:]+.*?([\d\-]+\s*-\s*)([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[2] ?? '');
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 2b: Direct format in text "CODE-NAME TRF FOR" (after decode)
        elseif (preg_match('/[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
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
            'account_number' => $accountNumber, // CRITICAL: Recipient account number (where payment was sent TO)
            'payer_account_number' => $payerAccountNumber,
            'transaction_time' => $transactionTime,
            'extracted_date' => $extractedDate,
        ];
    }
    
    /**
     * Extract payment info from html_body
     */
    protected function extractFromHtmlBody(string $html, string $subject, string $from): ?array
    {
        // CRITICAL: Decode quoted-printable FIRST (e.g., =3D becomes =, =20 becomes space)
        // This is essential because database may have stored HTML with quoted-printable encoding
        $html = $this->decodeQuotedPrintable($html);
        
        // Decode HTML entities SECOND (like &nbsp; becomes space, &amp; becomes &)
        // This is critical because stored HTML may have entities like NGN&nbsp;1000
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $htmlLower = strtolower($html);
        $fullText = $subject . ' ' . $htmlLower;
        
        $amount = null;
        $accountNumber = null;
        $senderName = null;
        $payerAccountNumber = null;
        $transactionTime = null;
        $extractedDate = null;
        $method = null;
        
        // PRIORITY: Extract account number from Description field FIRST (before any other extraction)
        // Description field format: <td>Description</td><td>:</td><td colspan="8">900877121002100859959000020260111094651392 FROM SOLOMON INNOCENT AMITHY TO SQUAD</td>
        // Format: recipient_account(10) sender_account(10) amount(6) date(8) unknown(9) FROM NAME TO NAME
        // IMPORTANT: First 10 digits is ALWAYS the recipient account number (where payment was sent TO)
        // Note: HTML structure may have a colon cell (<td>:</td>) between label and value
        
        // Pattern 1: Description field WITHOUT space between accounts, with optional colon cell (ULTRA-FLEXIBLE)
        // Format: <td>Description</td><td>:</td><td colspan="8">900877121002100859959000020260111094651392 FROM...</td>
        // This is the most common format in GTBank HTML emails
        // CRITICAL: Use .*? for flexible matching - allows ANY characters between digits and FROM
        // Changed from [\s\n\r]+FROM to .*?FROM to handle any formatting variations
        if (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?).*?TO/i', $html, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
            $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
            $amountFromDesc = (float) ($matches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = 'html_table_description';
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $senderName = trim(strtolower($matches[6]));
        }
        // Pattern 2: Description field WITHOUT colon cell (direct next cell) - ULTRA-FLEXIBLE
        // Format: <td>Description</td><td>900877121002100859959000020260111094651392 FROM...</td>
        // CRITICAL: Use .*? for flexible matching - allows ANY characters between digits and FROM
        // CRITICAL: Make TO optional - HTML might be truncated or not have TO (like: FROM SOLOMON with no TO)
        elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>.*?(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $html, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
            $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
            $amountFromDesc = (float) ($matches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = 'html_table_description';
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $senderName = trim(strtolower($matches[6]));
        }
        // Pattern 3: Description field with space between accounts
        // Format: <td>Description</td><td>9008771210 021008599511000020260111080847554 FROM...</td>
        elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})[\s]+(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)\s+TO/i', $html, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account (first 10 digits)
            $payerAccountNumber = trim($matches[2]); // Sender account (next 10 digits)
            $amountFromDesc = (float) ($matches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = 'html_table_description';
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $senderName = trim(strtolower($matches[6]));
        }
        // Pattern 4: Description field with dash separator (old format)
        // Format: <td>Description</td><td>090405260110001723439231932126-AMITHY ONE M TRF FOR...</td>
        elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[\d\-]*\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = 'html_table_description';
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            $potentialName = trim($matches[6]);
            $potentialName = preg_replace('/^[\d\-\s]+/i', '', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 5: Fallback - Extract from any cell containing the description pattern (very flexible)
        // This catches cases where the HTML structure is slightly different
        // CRITICAL: Use .*? for flexible matching - allows ANY characters between digits and FROM
        // CRITICAL: Make TO optional - HTML might be truncated or not have TO (like: FROM SOLOMON with no TO)
        elseif (!$accountNumber && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>.*?(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $html, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = 'html_table_description';
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($matches[6]));
            }
        }
        // Pattern 6: Ultra-flexible - Just look for the digit pattern followed by FROM in description context
        // This is a last resort that should catch almost any format
        // CRITICAL: Use .*? for flexible matching - allows ANY characters between digits and FROM
        // CRITICAL: Make TO optional - HTML might be truncated or not have TO (like: FROM SOLOMON with no TO)
        elseif (!$accountNumber && preg_match('/(?s)description[^>]*>.*?(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $html, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = 'html_flexible';
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($matches[6]));
            }
        }
        
        // STRATEGY 1: Convert HTML to plain text and try text extraction (simplest, most reliable)
        // This handles complex HTML structures by stripping tags first
        $plainText = strip_tags($html);
        $plainText = preg_replace('/\s+/', ' ', $plainText); // Normalize whitespace
        
        // Also try extracting account number from plain text description field (backup if HTML patterns failed)
        // CRITICAL: Use .*? for flexible matching - allows ANY characters between digits and FROM
        // This should match: "Description : 900877121002100859959000020260111094651392 FROM SOLOMON..."
        // CRITICAL: Make TO optional - text might be truncated or not have TO
        if (!$accountNumber && preg_match('/description[\s]*:[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:.*?TO|$)/i', $plainText, $textMatches)) {
            $accountNumber = trim($textMatches[1]); // PRIMARY source: recipient account (first 10 digits)
            $payerAccountNumber = trim($textMatches[2]);
            $amountFromDesc = (float) ($textMatches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = $method ?: 'html_rendered_text';
            }
            $dateStr = $textMatches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($textMatches[6]));
            }
        }
        // Last resort: Look for the digit pattern anywhere in plain text (ultra-flexible)
        // Pattern: 10 digits, 10 digits, 6 digits, 8 digits, 9 digits followed by FROM ... TO
        // CRITICAL: Use .*? for flexible matching - allows ANY characters between digits and FROM
        // CRITICAL: Make TO optional - text might be truncated or not have TO (like: FROM SOLOMON with no TO)
        elseif (!$accountNumber && preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $plainText, $textMatches)) {
            $accountNumber = trim($textMatches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($textMatches[2]);
            $amountFromDesc = (float) ($textMatches[3] / 100);
            if (!$amount && $amountFromDesc >= 10) {
                $amount = $amountFromDesc;
                $method = $method ?: 'html_rendered_text';
            }
            $dateStr = $textMatches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($textMatches[6]));
            }
        }
        
        if (!$amount && preg_match('/amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)/i', $plainText, $textMatches)) {
            $potentialAmount = (float) str_replace(',', '', $textMatches[1]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
                $method = $method ?: 'html_rendered_text';
                // Continue to extract sender name below
            }
        }
        
        // STRATEGY 2: HTML table extraction (more precise but can fail with complex structures)
        if (!$amount) {
            // Pattern 1: GTBank HTML table - Amount in separate cell after label and colon
            // Format from SQL dump: <td>Amount</td><td>:</td><td colspan="8">NGN 1000</td>
            // Uses DOTALL flag (?s) and non-greedy (.*?) to handle newlines/whitespace
            if (preg_match('/(?s)<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>.*?(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 1b: Amount in next cell (multiple cells in row, flexible whitespace)
            elseif (preg_match('/(?s)<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>.*?<\/td>\s*<td[^>]*>.*?(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 2: GTBank HTML table - Amount in same cell with label
            // Format: <td>Amount: NGN 1000</td> (after HTML entity decode)
            elseif (preg_match('/<td[^>]*>[\s]*amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 3: Look for any table row containing "Amount" label followed by NGN amount
            // This catches cases like <tr><td>Amount</td><td>:</td><td>NGN 1000</td></tr>
            elseif (preg_match('/(?s)<tr[^>]*>.*?<td[^>]*>[\s]*amount[\s:]*<\/td>.*?<td[^>]*>.*?(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 4: Very flexible - any table cell after "Amount" cell containing NGN
            elseif (preg_match('/(?s)<td[^>]*>[\s]*amount[\s:]*<\/td>.*?<td[^>]*>.*?(?:ngn|naira|₦|NGN)\s*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
        }
        
        // STRATEGY 3: Fallback patterns (if table extraction failed)
        if (!$amount) {
            // Pattern 3: Description field contains amount
            if (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>.*?(?:ngn|naira|₦|NGN)\s*([\d,]+\.?\d*).*?<\/td>/i', $html, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    $method = 'html_table';
                }
            }
            // Pattern 4: Any HTML table cell containing NGN/Naira (broader match)
            if (!$amount && preg_match('/<td[^>]*>[\s]*(?:ngn|naira|₦|NGN)\s*([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    $method = 'html_table';
                }
            }
            // Pattern 5: HTML text (not in table) - amount format
            if (!$amount && preg_match('/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦|NGN)\s*([\d,]+\.?\d*)/i', $html, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    $method = 'html_text';
                }
            }
            // Pattern 6: Standalone NGN in HTML (last resort)
            if (!$amount && preg_match('/(?:ngn|naira|₦|NGN)\s*([\d,]+\.?\d*)/i', $html, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    $method = 'html_text';
                }
            }
        }
        
        // Extract sender name from HTML
        // Pattern 1: GTBank HTML table - Description field contains "FROM NAME TO"
        if (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 2: GTBank HTML table - Description field contains "CODE-NAME TRF FOR" (new format)
        // Format: <td>Description</td><td>090405260110014006799532206126-AMITHY ONE M TRF FOR...</td>
        // Note: HTML is already decoded from quoted-printable by decodeQuotedPrintable()
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>.*?([\d\-]+\s*-\s*)([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
            $potentialName = trim($matches[2] ?? '');
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 2b: Direct format in description cell "CODE-NAME TRF FOR"
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
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
            'account_number' => $accountNumber,
            'sender_name' => $senderName,
            'payer_account_number' => $payerAccountNumber,
            'transaction_time' => $transactionTime,
            'extracted_date' => $extractedDate,
            'method' => $method ?? 'html_body',
        ];
    }

    /**
     * Decode quoted-printable encoding (e.g., =20 becomes space, =3D becomes =)
     */
    protected function decodeQuotedPrintable(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode quoted-printable format: =XX where XX is hex
        // =20 is space, =0D=0A is CRLF, =3D is =, etc.
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            $hex = hexdec($matches[1]);
            // Convert hex to character (0-255)
            return chr($hex);
        }, $text);
        
        // Handle soft line breaks (trailing = at end of line)
        // This removes = followed by CRLF or LF
        $text = preg_replace('/=\r?\n/', '', $text);
        
        // Also handle standalone = at end of line (in case CRLF is missing)
        $text = preg_replace('/=\s*\n/', "\n", $text);
        
        return $text;
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

    /**
     * Clean UTF-8 string for JSON encoding
     * Removes or fixes malformed UTF-8 characters
     * 
     * @param string $string
     * @return string
     */
    protected function cleanUtf8ForJson(string $string): string
    {
        if (empty($string)) {
            return '';
        }

        // Remove invalid UTF-8 sequences using iconv
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        
        // If iconv failed, use mb_convert_encoding
        if ($cleaned === false || !mb_check_encoding($cleaned, 'UTF-8')) {
            $cleaned = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // Remove control characters except newlines, carriage returns, and tabs
        $cleaned = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $cleaned);
        
        // Ensure valid UTF-8
        if (!mb_check_encoding($cleaned, 'UTF-8')) {
            // Last resort: encode as UTF-8 and ignore invalid
            $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
        }

        return $cleaned ?: '';
    }
}
