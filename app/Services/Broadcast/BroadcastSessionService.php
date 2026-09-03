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

    /**
     * @param  array{
     *   payer_name?: string|null,
     *   payer_account?: string|null,
     *   payer_bank?: string|null,
     *   payer_reference?: string|null,
     *   whatsapp_wallet_id?: int|null
     * }  $payer
     */
    public function markPaid(string $sessionUuid, array $payer = []): bool
    {
        if (! Str::isUuid($sessionUuid)) {
            return false;
        }

        $nowMs = (int) (microtime(true) * 1000);
        $update = array_merge([
            'status' => self::STATUS_PAID,
            'closed_at' => $nowMs,
            'updated_at' => now(),
        ], $this->payerColumnUpdate($payer));

        $updated = DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->where('status', self::STATUS_OPEN)
            ->update($update);

        if ($updated > 0) {
            return true;
        }

        $updated = DB::table('broadcast_sessions')
            ->where('session_uuid', $sessionUuid)
            ->whereIn('status', [
                BroadcastSessionPaymentMatcher::STATUS_PARTIAL,
                BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
            ])
            ->update($update);

        return $updated > 0;
    }

    /**
     * Cheko POS aliases: payer_name / payerName / sender_name, payer_account / sender_account, payer_bank / bank_name.
     *
     * @return array<string, mixed>
     */
    public function chekoPayerPayload(?object $session): array
    {
        $name = trim((string) ($session->payer_name ?? ''));
        $account = preg_replace('/\D+/', '', (string) ($session->payer_account ?? '')) ?: '';
        $bank = trim((string) ($session->payer_bank ?? ''));
        $reference = trim((string) ($session->payer_reference ?? ''));

        if (($name === '' || $account === '' || $bank === '' || $reference === '') && ! empty($session->payment_id)) {
            $payment = \App\Models\Payment::query()->find($session->payment_id);
            if ($payment) {
                if ($name === '') {
                    $name = trim((string) ($payment->payer_name ?? ''));
                }
                if ($account === '') {
                    $account = preg_replace('/\D+/', '', (string) ($payment->payer_account_number ?? '')) ?: '';
                }
                if ($bank === '') {
                    $bank = trim((string) ($payment->bank ?? ''));
                }
                if ($reference === '') {
                    $reference = trim((string) ($payment->transaction_id ?? $payment->external_reference ?? ''));
                }
            }
        }

        $name = $name !== '' ? $name : null;
        $account = $account !== '' ? $account : null;
        $bank = $bank !== '' ? $bank : null;
        $reference = $reference !== '' ? $reference : null;

        return [
            'event' => 'payment.confirmed',
            'session_id' => (string) ($session->session_uuid ?? ''),
            'reference' => $reference,
            'payer_name' => $name,
            'payerName' => $name,
            'sender_name' => $name,
            'payer_account' => $account,
            'payer_account_number' => $account,
            'sender_account' => $account,
            'payer_bank' => $bank,
            'bank_name' => $bank,
            'payer' => [
                'name' => $name,
                'account' => $account,
                'bank' => $bank,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payer
     * @return array<string, mixed>
     */
    public function payerColumnUpdate(array $payer): array
    {
        $out = [];
        if (array_key_exists('payer_name', $payer)) {
            $name = trim((string) ($payer['payer_name'] ?? ''));
            $out['payer_name'] = $name !== '' ? $name : null;
        }
        if (array_key_exists('payer_account', $payer)) {
            $account = preg_replace('/\D+/', '', (string) ($payer['payer_account'] ?? '')) ?: '';
            $out['payer_account'] = $account !== '' ? $account : null;
        }
        if (array_key_exists('payer_bank', $payer)) {
            $bank = trim((string) ($payer['payer_bank'] ?? ''));
            $out['payer_bank'] = $bank !== '' ? $bank : null;
        }
        if (array_key_exists('payer_reference', $payer)) {
            $ref = trim((string) ($payer['payer_reference'] ?? ''));
            $out['payer_reference'] = $ref !== '' ? $ref : null;
        }
        if (array_key_exists('whatsapp_wallet_id', $payer) && $payer['whatsapp_wallet_id'] !== null) {
            $out['whatsapp_wallet_id'] = (int) $payer['whatsapp_wallet_id'];
        }

        return $out;
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
