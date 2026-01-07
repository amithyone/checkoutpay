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
    protected function extractPaymentInfo(array $emailData): ?array
    {
        $subject = strtolower($emailData['subject'] ?? '');
        $text = strtolower($emailData['text'] ?? '');
        $html = strtolower($emailData['html'] ?? '');
        $from = strtolower($emailData['from'] ?? '');

        $fullText = $subject . ' ' . $text . ' ' . strip_tags($html);

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
        $namePatterns = [
            '/from\s+([A-Z][A-Z\s]+?)\s+to/i', // "FROM SOLOMON INNOCENT AMITHY TO"
            '/(?:from|sender|payer|depositor|account\s*name|name)[\s:]*([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i',
            '/(?:credited\s+by|from)\s+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i',
            '/([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/', // Fallback: any capitalized name pattern
        ];

        $senderName = null;
        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $senderName = trim(strtolower($matches[1]));
                break;
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
    protected function matchPayment(Payment $payment, array $extractedInfo): array
    {
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

        // If payer name is provided, it must match exactly (case-insensitive)
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

            // Exact match required
            if ($expectedName !== $receivedName) {
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
            'reason' => 'Amount and name match',
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
}
