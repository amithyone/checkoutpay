<?php

namespace App\Services\Broadcast;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BroadcastSessionService
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return object{session_uuid: string, terminal_id: string, status: string, amount_ngn: int}|null
     */
    public function find(string $sessionUuid): ?object
    {
        if (! Str::isUuid($sessionUuid)) {
            return null;
        }

        return DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->first();
    }

    public function open(
        string $sessionUuid,
        string $terminalId,
        int $amountNgn,
        string $settlementMode = 'permanent',
        ?string $settlementAccountNumber = null,
    ): void {
        if (! Str::isUuid($sessionUuid)) {
            return;
        }

        $nowMs = (int) (microtime(true) * 1000);
        $now = now();

        $existing = $this->find($sessionUuid);
        if ($existing !== null) {
            DB::table('broadcast_sessions')
                ->where('session_uuid', $sessionUuid)
                ->whereIn('status', [self::STATUS_OPEN, BroadcastSessionPaymentMatcher::STATUS_PARTIAL, BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT])
                ->update([
                    'amount_ngn' => max(0, $amountNgn),
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('broadcast_sessions')->insertOrIgnore([
            'session_uuid' => $sessionUuid,
            'terminal_id' => $terminalId,
            'status' => self::STATUS_OPEN,
            'settlement_mode' => $settlementMode,
            'amount_ngn' => max(0, $amountNgn),
            'amount_received_ngn' => 0,
            'settlement_account_number' => $settlementAccountNumber,
            'opened_at' => $nowMs,
            'closed_at' => null,
            'expecting_payment_at' => $settlementMode === 'temporary' ? $nowMs : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function markPaid(string $sessionUuid): bool
    {
        if (! Str::isUuid($sessionUuid)) {
            return false;
        }

        $nowMs = (int) (microtime(true) * 1000);

        $updated = DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->where('status', self::STATUS_OPEN)
            ->update([
                'status' => self::STATUS_PAID,
                'closed_at' => $nowMs,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            return true;
        }

        $updated = DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->whereIn('status', [
                BroadcastSessionPaymentMatcher::STATUS_PARTIAL,
                BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
            ])
            ->update([
                'status' => self::STATUS_PAID,
                'closed_at' => $nowMs,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    public function markCancelled(string $sessionUuid, string $terminalId): bool
    {
        if (! Str::isUuid($sessionUuid)) {
            return false;
        }

        $nowMs = (int) (microtime(true) * 1000);

        $updated = DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->where('terminal_id', $terminalId)
            ->whereIn('status', [
                self::STATUS_OPEN,
                BroadcastSessionPaymentMatcher::STATUS_PARTIAL,
                BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
            ])
            ->update([
                'status' => self::STATUS_CANCELLED,
                'closed_at' => $nowMs,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }
}
