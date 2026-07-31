<?php

namespace App\Services\Broadcast;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Match inbound business payments to open Pay at Shop broadcast sessions (POS polling).
 */
class BroadcastSessionPaymentMatcher
{
    public const STATUS_PARTIAL = 'partial';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    private const MATCH_WINDOW_MS = 600_000;

    public function __construct(
        private readonly BroadcastSessionService $sessions,
    ) {}

    public function handleApprovedPayment(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_APPROVED || ! $payment->business_id) {
            return;
        }

        $receivedKobo = $this->paymentAmountKobo($payment);
        if ($receivedKobo <= 0) {
            return;
        }

        $accountNumber = preg_replace('/\D/', '', (string) ($payment->account_number ?? '')) ?: null;

        $terminals = DB::table('broadcast_terminals')
            ->where('business_id', $payment->business_id)
            ->where('active', 1)
            ->pluck('terminal_id');

        if ($terminals->isEmpty()) {
            return;
        }

        $candidates = DB::table('broadcast_sessions')
            ->whereIn('terminal_id', $terminals->all())
            ->whereIn('status', [
                BroadcastSessionService::STATUS_OPEN,
                self::STATUS_PARTIAL,
                self::STATUS_AWAITING_PAYMENT,
            ])
            ->orderBy('opened_at')
            ->get();

        foreach ($candidates as $session) {
            $sessionMode = (string) ($session->settlement_mode ?? 'permanent');
            if ($sessionMode === 'permanent' && empty($session->expecting_payment_at)) {
                continue;
            }

            if ($accountNumber !== null) {
                $sessionAccount = preg_replace('/\D/', '', (string) ($session->settlement_account_number ?? ''));
                $terminalAccount = DB::table('broadcast_terminals')
                    ->where('terminal_id', $session->terminal_id)
                    ->value('account_number');
                $terminalAccount = preg_replace('/\D/', '', (string) ($terminalAccount ?? ''));

                if ($sessionAccount !== '' && $sessionAccount !== $accountNumber) {
                    continue;
                }
                if ($sessionAccount === '' && $terminalAccount !== '' && $terminalAccount !== $accountNumber) {
                    continue;
                }
            }

            if (! $this->withinMatchWindow((int) $session->opened_at)) {
                continue;
            }

            $expectedKobo = (int) $session->amount_ngn;
            $alreadyReceived = (int) ($session->amount_received_ngn ?? 0);
            $remainingKobo = $expectedKobo - $alreadyReceived;
            if ($remainingKobo <= 0) {
                continue;
            }

            $sessionMode = (string) ($session->settlement_mode ?? 'permanent');
            if ($sessionMode === 'permanent' && $receivedKobo !== $remainingKobo) {
                continue;
            }

            $this->applyCredit($session, $receivedKobo, (int) $payment->id, $sessionMode);

            return;
        }
    }

    public function markExpectingPayment(string $sessionUuid, string $terminalId): bool
    {
        if (! \Illuminate\Support\Str::isUuid($sessionUuid)) {
            return false;
        }

        $nowMs = (int) (microtime(true) * 1000);

        $updated = DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->where('terminal_id', $terminalId)
            ->whereIn('status', [
                BroadcastSessionService::STATUS_OPEN,
                self::STATUS_AWAITING_PAYMENT,
                self::STATUS_PARTIAL,
            ])
            ->update([
                'expecting_payment_at' => $nowMs,
                'status' => self::STATUS_AWAITING_PAYMENT,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    private function applyCredit(object $session, int $receivedKobo, int $paymentId, string $settlementMode = 'permanent'): void
    {
        $expectedKobo = (int) $session->amount_ngn;
        $alreadyReceived = (int) ($session->amount_received_ngn ?? 0);
        $totalReceived = $alreadyReceived + $receivedKobo;
        $nowMs = (int) (microtime(true) * 1000);

        $isPaid = $expectedKobo > 0 && $totalReceived >= $expectedKobo;
        if ($settlementMode === 'permanent' && $expectedKobo > 0) {
            $isPaid = $totalReceived === $expectedKobo;
        }

        if ($isPaid) {
            DB::table('broadcast_sessions')
                ->where('session_uuid', $session->session_uuid)
                ->update([
                    'status' => BroadcastSessionService::STATUS_PAID,
                    'amount_received_ngn' => $totalReceived,
                    'payment_id' => $paymentId,
                    'closed_at' => $nowMs,
                    'updated_at' => now(),
                ]);

            Log::info('broadcast.session.paid', [
                'session_uuid' => $session->session_uuid,
                'terminal_id' => $session->terminal_id,
                'expected_kobo' => $expectedKobo,
                'received_kobo' => $totalReceived,
                'payment_id' => $paymentId,
            ]);

            return;
        }

        DB::table('broadcast_sessions')
            ->where('session_uuid', $session->session_uuid)
            ->update([
                'status' => self::STATUS_PARTIAL,
                'amount_received_ngn' => $totalReceived,
                'payment_id' => $paymentId,
                'updated_at' => now(),
            ]);

        Log::info('broadcast.session.partial', [
            'session_uuid' => $session->session_uuid,
            'terminal_id' => $session->terminal_id,
            'expected_kobo' => $expectedKobo,
            'received_kobo' => $totalReceived,
            'payment_id' => $paymentId,
        ]);
    }

    private function paymentAmountKobo(Payment $payment): int
    {
        $amount = $payment->received_amount ?? $payment->amount;

        return (int) round((float) $amount * 100);
    }

    private function withinMatchWindow(int $openedAtMs): bool
    {
        return abs((int) (microtime(true) * 1000) - $openedAtMs) <= self::MATCH_WINDOW_MS;
    }
}
