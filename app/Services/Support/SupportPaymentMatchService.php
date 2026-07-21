<?php

namespace App\Services\Support;

use App\Events\PaymentApproved;
use App\Jobs\CheckPaymentEmails;
use App\Models\MatchAttempt;
use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Models\SupportIntakeSession;
use App\Notifications\NewDepositNotification;
use App\Services\ChargeService;
use App\Services\MatchAttemptLogger;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use Illuminate\Support\Facades\Log;

final class SupportPaymentMatchService
{
    public function __construct(
        private PaymentMatchingService $matchingService,
        private MatchAttemptLogger $matchLogger,
        private ChargeService $chargeService,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     matched: bool,
     *     payment?: Payment|null,
     *     message: string,
     *     already_approved?: bool
     * }
     */
    public function attemptForIntake(SupportIntakeSession $session): array
    {
        $payment = $this->resolvePayment($session);

        if (! $payment) {
            $bankKey = strtolower(trim((string) $session->reported_payee_name));
            $messageKey = $bankKey === 'kuda'
                ? 'support.intake_messages.match_not_found_kuda'
                : 'support.intake_messages.match_not_found';

            return [
                'ok' => true,
                'matched' => false,
                'payment' => null,
                'message' => (string) config($messageKey, ''),
            ];
        }

        if ($payment->status === Payment::STATUS_APPROVED) {
            return [
                'ok' => true,
                'matched' => true,
                'already_approved' => true,
                'payment' => $payment,
                'message' => (string) config('support.intake_messages.payment_approved', ''),
            ];
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            return [
                'ok' => true,
                'matched' => false,
                'payment' => $payment,
                'message' => (string) config('support.intake_messages.match_not_pending', ''),
            ];
        }

        if ($payment->isExpired()) {
            return [
                'ok' => true,
                'matched' => false,
                'payment' => $payment,
                'message' => (string) config('support.intake_messages.payment_expired', ''),
            ];
        }

        $this->applyIntakeHintsToPayment($session, $payment);

        try {
            $matched = $this->runMatchAgainstStoredEmails($payment);

            if ($matched) {
                $payment->refresh();

                return [
                    'ok' => true,
                    'matched' => true,
                    'payment' => $payment,
                    'message' => (string) config('support.intake_messages.match_success', ''),
                ];
            }

            CheckPaymentEmails::dispatchSync($payment);
            $payment->refresh();

            if ($payment->status === Payment::STATUS_APPROVED) {
                return [
                    'ok' => true,
                    'matched' => true,
                    'payment' => $payment,
                    'message' => (string) config('support.intake_messages.match_success', ''),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('support.intake: auto-match email check failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'ok' => true,
            'matched' => false,
            'payment' => $payment,
            'message' => (string) config('support.intake_messages.match_not_received', ''),
        ];
    }

    public function resolvePayment(SupportIntakeSession $session): ?Payment
    {
        if ($session->payment_id) {
            $payment = Payment::query()->find($session->payment_id);
            if ($payment) {
                return $payment;
            }
        }

        $bankKey = strtolower(trim((string) $session->reported_payee_name));
        $sessionId = trim((string) $session->payment_session_id);
        $account = SupportPayeeAccountService::normalizeAccountNumber((string) $session->reported_destination_account);

        if ($sessionId !== '') {
            $bySession = Payment::query()->where('transaction_id', $sessionId)->first();
            if ($bySession && $this->sessionLookupAcceptsPayment($bankKey, $bySession, $account)) {
                return $bySession;
            }
        }

        $name = trim((string) $session->visitor_name);
        $amount = $session->payment_amount_reported !== null ? (float) $session->payment_amount_reported : null;

        if ($account === '' || $name === '' || $amount === null || $amount <= 0) {
            return null;
        }

        $candidates = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('account_number', $account)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $nameScore = $this->nameSimilarity($name, (string) ($candidate->payer_name ?? ''));
            $amountDiff = abs((float) $candidate->amount - $amount);
            $amountOk = $amountDiff <= max(1.0, $amount * 0.03);
            $exactAmount = $amountDiff <= 0.01;

            if (! $amountOk) {
                continue;
            }

            $score = $nameScore;
            if ($sessionId !== '' && strcasecmp((string) $candidate->transaction_id, $sessionId) === 0) {
                $score += 50;
            }

            if ($bankKey === 'kuda' && $nameScore === 0 && $exactAmount && blank($candidate->payer_name)) {
                $score = max($score, 55);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        $minScore = $bankKey === 'kuda' ? 55 : 50;

        if ($best && $bestScore >= $minScore) {
            return $best;
        }

        return null;
    }

    private function sessionLookupAcceptsPayment(string $bankKey, Payment $payment, string $reportedAccount): bool
    {
        if ($bankKey === 'rubies_mfb' || $bankKey === '') {
            return true;
        }

        if ($reportedAccount === '') {
            return false;
        }

        return SupportPayeeAccountService::normalizeAccountNumber((string) $payment->account_number) === $reportedAccount;
    }

    private function applyIntakeHintsToPayment(SupportIntakeSession $session, Payment $payment): void
    {
        $name = trim((string) $session->visitor_name);
        if ($name !== '' && blank($payment->payer_name)) {
            $payment->payer_name = strtolower($name);
        }

        $reportedAmount = $session->payment_amount_reported !== null
            ? round((float) $session->payment_amount_reported, 2)
            : null;

        if ($reportedAmount !== null && $reportedAmount > 0 && abs((float) $payment->amount - $reportedAmount) > 0.01) {
            $emailData = is_array($payment->email_data) ? $payment->email_data : [];
            $emailData['support_intake_amount_update'] = [
                'old_amount' => (float) $payment->amount,
                'new_amount' => $reportedAmount,
                'updated_at' => now()->toISOString(),
                'intake_token' => $session->intake_token,
            ];
            $payment->amount = $reportedAmount;
            $payment->email_data = $emailData;

            $payment->loadMissing(['website', 'business']);
            if ($payment->business) {
                $charges = $this->chargeService->calculateCharges($reportedAmount, $payment->website, $payment->business);
                $payment->charge_percentage = $charges['charge_percentage'];
                $payment->charge_fixed = $charges['charge_fixed'];
                $payment->total_charges = $charges['total_charges'];
                $payment->business_receives = $charges['business_receives'];
            }
        }

        $payment->save();
    }

    private function runMatchAgainstStoredEmails(Payment $payment): bool
    {
        $storedEmails = ProcessedEmail::unmatched()
            ->withAmount((float) $payment->amount)
            ->get();

        foreach ($storedEmails as $storedEmail) {
            $emailData = [
                'subject' => $storedEmail->subject,
                'from' => $storedEmail->from_email,
                'text' => $storedEmail->text_body ?? '',
                'html' => $storedEmail->html_body ?? '',
                'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                'email_account_id' => $storedEmail->email_account_id,
                'processed_email_id' => $storedEmail->id,
            ];

            $extractionResult = $this->matchingService->extractPaymentInfo($emailData);
            $extractedInfo = is_array($extractionResult) && isset($extractionResult['data'])
                ? $extractionResult['data']
                : $extractionResult;
            $extractionMethod = is_array($extractionResult) ? ($extractionResult['method'] ?? 'unknown') : 'unknown';

            if (! is_array($extractedInfo) || empty($extractedInfo['amount'])) {
                continue;
            }

            $emailDate = $storedEmail->email_date ? \Carbon\Carbon::parse($storedEmail->email_date) : null;
            $match = $this->matchingService->matchPayment($payment, $extractedInfo, $emailDate);

            try {
                $this->matchLogger->logAttempt([
                    'payment_id' => $payment->id,
                    'processed_email_id' => $storedEmail->id,
                    'transaction_id' => $payment->transaction_id,
                    'match_result' => $match['matched'] ? MatchAttempt::RESULT_MATCHED : MatchAttempt::RESULT_UNMATCHED,
                    'reason' => $match['reason'] ?? 'Support intake auto-match',
                    'payment_amount' => $payment->amount,
                    'payment_name' => $payment->payer_name,
                    'payment_account_number' => $payment->account_number,
                    'payment_created_at' => $payment->created_at,
                    'extracted_amount' => $extractedInfo['amount'] ?? null,
                    'extracted_name' => $extractedInfo['sender_name'] ?? null,
                    'extracted_account_number' => $extractedInfo['account_number'] ?? null,
                    'email_subject' => $storedEmail->subject,
                    'email_from' => $storedEmail->from_email,
                    'email_date' => $emailDate,
                    'amount_diff' => $match['amount_diff'] ?? null,
                    'name_similarity_percent' => $match['name_similarity_percent'] ?? null,
                    'time_diff_minutes' => $match['time_diff_minutes'] ?? null,
                    'extraction_method' => $extractionMethod,
                    'details' => ['source' => 'support_intake'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('support.intake: match attempt log failed', ['error' => $e->getMessage()]);
            }

            if (! ($match['matched'] ?? false)) {
                continue;
            }

            $storedEmail->markAsMatched($payment);
            $payment->approve([
                'subject' => $storedEmail->subject,
                'from' => $storedEmail->from_email,
                'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                'sender_name' => $storedEmail->sender_name,
                'amount' => $payment->amount,
                'processed_email_id' => $storedEmail->id,
            ], $match['is_mismatch'] ?? false, $match['received_amount'] ?? null, $match['mismatch_reason'] ?? null);

            if ($payment->business_id) {
                $payment->business->incrementBalanceWithCharges($payment->amount, $payment);
                $payment->business->refresh();
                $payment->business->notify(new NewDepositNotification($payment));
                $payment->business->triggerAutoWithdrawal();
            }

            $payment->refresh();
            $payment->load(['business.websites', 'website']);
            event(new PaymentApproved($payment));

            return true;
        }

        return false;
    }

    private function nameSimilarity(string $left, string $right): int
    {
        $left = strtolower(trim($left));
        $right = strtolower(trim($right));
        if ($left === '' || $right === '') {
            return 0;
        }
        similar_text($left, $right, $percent);

        return (int) round($percent);
    }
}
