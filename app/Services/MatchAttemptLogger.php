<?php

namespace App\Services;

use App\Models\MatchAttempt;
use App\Models\ProcessedEmail;
use Illuminate\Support\Facades\Log;

class MatchAttemptLogger
{
    /**
     * Log a match attempt to database with full details
     */
    public function logAttempt(array $data): MatchAttempt
    {
        $startTime = microtime(true);

        // Sanitize details array to fix malformed UTF-8 characters
        $details = $data['details'] ?? null;
        if ($details !== null && is_array($details)) {
            $details = $this->sanitizeArrayForJson($details);
        }

        // Prepare data for storage
        $attemptData = [
            'payment_id' => $data['payment_id'] ?? null,
            'processed_email_id' => $data['processed_email_id'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'match_result' => $data['match_result'] ?? MatchAttempt::RESULT_UNMATCHED,
            'reason' => $this->sanitizeUtf8($data['reason'] ?? 'No reason provided'),
            
            // Payment details
            'payment_amount' => $data['payment_amount'] ?? null,
            'payment_name' => $this->sanitizeUtf8($data['payment_name'] ?? null),
            'payment_account_number' => $this->sanitizeUtf8($data['payment_account_number'] ?? null),
            'payment_created_at' => $data['payment_created_at'] ?? null,
            
            // Extracted email details
            'extracted_amount' => $data['extracted_amount'] ?? null,
            'extracted_name' => $this->sanitizeUtf8($data['extracted_name'] ?? null),
            'extracted_account_number' => $this->sanitizeUtf8($data['extracted_account_number'] ?? null),
            'email_subject' => $this->sanitizeUtf8($data['email_subject'] ?? null),
            'email_from' => $this->sanitizeUtf8($data['email_from'] ?? null),
            'email_date' => $data['email_date'] ?? null,
            
            // Comparison metrics
            'amount_diff' => $data['amount_diff'] ?? null,
            'name_similarity_percent' => $data['name_similarity_percent'] ?? null,
            'time_diff_minutes' => $data['time_diff_minutes'] ?? null,
            
            // Extraction method
            'extraction_method' => $this->sanitizeUtf8($data['extraction_method'] ?? null),
            
            // Details JSON (sanitized)
            'details' => $details,
            
            // HTML/text snippets (truncated to 500 chars and sanitized)
            'html_snippet' => isset($data['html_snippet']) ? $this->sanitizeUtf8(mb_substr($data['html_snippet'], 0, 500)) : null,
            'text_snippet' => isset($data['text_snippet']) ? $this->sanitizeUtf8(mb_substr($data['text_snippet'], 0, 500)) : null,
        ];

        // Calculate processing time
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $attemptData['processing_time_ms'] = round($processingTime, 2);

        try {
            // Create match attempt record
            $attempt = MatchAttempt::create($attemptData);
            
            // Update ProcessedEmail with last match reason if unmatched
            if ($attempt->processed_email_id && $attempt->match_result !== MatchAttempt::RESULT_MATCHED) {
                $processedEmail = ProcessedEmail::find($attempt->processed_email_id);
                if ($processedEmail) {
                    $processedEmail->increment('match_attempts_count');
                    $processedEmail->update([
                        'last_match_reason' => $attempt->reason,
                        'extraction_method' => $attempt->extraction_method,
                    ]);
                }
            }

            return $attempt;
        } catch (\Exception $e) {
            // Fallback to logging if database insert fails
            Log::error('Failed to log match attempt to database', [
                'error' => $e->getMessage(),
                'attempt_data' => $attemptData,
            ]);
            
            // Re-throw so caller knows it failed
            throw $e;
        }
    }

    /**
     * Get relevant HTML snippet for debugging (around amount/name fields)
     */
    public function extractHtmlSnippet(string $html, ?string $searchTerm = null): ?string
    {
        if (empty($html)) {
            return null;
        }

        // If search term provided, find context around it
        if ($searchTerm) {
            $pos = stripos($html, $searchTerm);
            if ($pos !== false) {
                $start = max(0, $pos - 200);
                $length = 400;
                return mb_substr($html, $start, $length);
            }
        }

        // Default: get first 500 chars (usually contains table structure)
        return mb_substr($html, 0, 500);
    }

    /**
     * Get relevant text snippet for debugging
     */
    public function extractTextSnippet(string $text, ?string $searchTerm = null): ?string
    {
        if (empty($text)) {
            return null;
        }

        // If search term provided, find context around it
        if ($searchTerm) {
            $pos = stripos($text, $searchTerm);
            if ($pos !== false) {
                $start = max(0, $pos - 100);
                $length = 200;
                return mb_substr($text, $start, $length);
            }
        }

        // Default: get first 300 chars
        return mb_substr($text, 0, 300);
    }

    /**
     * Sanitize UTF-8 string to remove malformed characters
     * 
     * @param string|null $string
     * @return string|null
     */
    protected function sanitizeUtf8(?string $string): ?string
    {
        if ($string === null || $string === '') {
            return $string;
        }

        // Remove invalid UTF-8 sequences
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Remove or replace invalid UTF-8 characters
        $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
        
        // If filter_var returns false, try iconv as fallback
        if ($string === false || !mb_check_encoding($string, 'UTF-8')) {
            // Use iconv to convert and ignore invalid characters
            $string = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
            
            // Final fallback: remove non-printable characters except newlines and tabs
            if ($string === false || !mb_check_encoding($string, 'UTF-8')) {
                $string = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $string);
            }
        }

        return $string;
    }

    /**
     * Recursively sanitize array for JSON encoding
     * 
     * @param array $array
     * @return array
     */
    protected function sanitizeArrayForJson(array $array): array
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $sanitizedKey = is_string($key) ? $this->sanitizeUtf8($key) : $key;
            
            if (is_string($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeUtf8($value);
            } elseif (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArrayForJson($value);
            } elseif (is_numeric($value) || is_bool($value) || $value === null) {
                $sanitized[$sanitizedKey] = $value;
            } else {
                // For other types, convert to string and sanitize
                $sanitized[$sanitizedKey] = $this->sanitizeUtf8((string) $value);
            }
        }
        
        return $sanitized;
    }
}
