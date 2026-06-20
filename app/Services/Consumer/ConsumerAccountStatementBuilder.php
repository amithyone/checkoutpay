<?php

namespace App\Services\Consumer;

use Carbon\Carbon;

/**
 * Server-side account statement CSV/HTML matching checkoutnow/packages/shared/src/accountStatement.ts.
 */
final class ConsumerAccountStatementBuilder
{
    /** @var list<string> */
    private const CREDIT_TYPES = [
        'topup', 'p2p_credit', 'adjustment', 'virtual_card_withdraw', 'business_rubies_in',
        'merchant_payment_in', 'savings_withdraw', 'savings_maturity_bonus', 'savings_completion_bonus',
        'savings_flexible_bonus', 'savings_interest_payout',
    ];

    /** @var list<string> */
    private const DEBIT_TYPES = [
        'bank_transfer_out', 'p2p_debit', 'vtu_airtime', 'vtu_data', 'vtu_electricity', 'vtu_cable',
        'vtu_betting', 'partner_merchant_pay', 'tagine_merchant_pay', 'virtual_card_fee', 'virtual_card_topup',
        'merchant_withdrawal_out', 'business_name_registration_fee', 'savings_deposit', 'savings_auto_save',
        'savings_strict_save', 'spend_to_save', 'savings_lock',
    ];

    /**
     * @param  list<array<string, mixed>>  $transactions
     */
    public static function statementCsvContent(array $input, array $transactions): string
    {
        $summary = self::aggregateUtilitySummary($transactions);
        $lines = self::statementLineItems($transactions);
        $esc = static fn (string $s): string => '"'.str_replace('"', '""', $s).'"';

        $header = [
            'CheckoutNow Account Statement',
            'Ledger,'.$esc((string) $input['ledger_label']),
            'Period,'.$esc((string) $input['period_label']),
            'From,'.(string) $input['from'],
            'To,'.(string) $input['to'],
            'Phone,'.$esc((string) $input['phone']),
            'Account name,'.$esc((string) $input['account_name']),
            '',
            'Money in,'.$summary['money_in'],
            'Money out,'.$summary['money_out'],
            'Net,'.$summary['net'],
            '',
            'Date,Description,Type,Direction,Amount',
        ];

        $rows = array_map(static function (array $line): string {
            return sprintf(
                '%s,%s,%s,%s,%s',
                $line['date'],
                '"'.str_replace('"', '""', (string) $line['description']).'"',
                '"'.str_replace('"', '""', (string) $line['type']).'"',
                $line['direction'],
                $line['amount'],
            );
        }, $lines);

        return implode("\n", array_merge($header, $rows));
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     */
    public static function statementHtmlContent(array $input, array $transactions): string
    {
        $summary = self::aggregateUtilitySummary($transactions);
        $lines = self::statementLineItems($transactions);
        $currency = (string) ($input['currency'] ?? 'NGN');

        $lineRows = '';
        foreach ($lines as $line) {
            $lineRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td style="text-align:right">%s</td></tr>',
                self::escapeHtml((string) $line['date']),
                self::escapeHtml((string) $line['description']),
                self::escapeHtml((string) $line['type']),
                self::escapeHtml((string) $line['direction']),
                self::escapeHtml(self::fmtMoney($currency, (float) $line['amount'])),
            );
        }

        return '<!DOCTYPE html>
<html><head><meta charset="utf-8"/>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111; padding: 32px; font-size: 11px; }
  h1 { font-size: 18px; margin: 0 0 4px; }
  .meta { color: #555; margin-bottom: 20px; line-height: 1.6; }
  .summary { margin-bottom: 24px; }
  .summary td { padding: 4px 16px 4px 0; }
  table.items { width: 100%; border-collapse: collapse; }
  table.items th, table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: left; }
  table.items th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; }
</style></head><body>
  <h1>CheckoutNow — Account Statement</h1>
  <div class="meta">
    <div><strong>'.self::escapeHtml((string) $input['ledger_label']).'</strong></div>
    <div>'.self::escapeHtml((string) $input['period_label']).' ('.self::escapeHtml((string) $input['from']).' → '.self::escapeHtml((string) $input['to']).')</div>
    <div>'.self::escapeHtml((string) $input['account_name']).' · '.self::escapeHtml((string) $input['phone']).'</div>
  </div>
  <table class="summary">
    <tr><td>Money in</td><td><strong>'.self::escapeHtml(self::fmtMoney($currency, (float) $summary['money_in'])).'</strong></td></tr>
    <tr><td>Money out</td><td><strong>'.self::escapeHtml(self::fmtMoney($currency, (float) $summary['money_out'])).'</strong></td></tr>
    <tr><td>Net</td><td><strong>'.self::escapeHtml(self::fmtMoney($currency, (float) $summary['net'])).'</strong></td></tr>
  </table>
  <table class="items">
    <thead><tr><th>Date</th><th>Description</th><th>Type</th><th>In/Out</th><th>Amount</th></tr></thead>
    <tbody>'.$lineRows.'</tbody>
  </table>
</body></html>';
    }

    public static function statementPeriodLabel(?string $period): string
    {
        return match ($period) {
            '12mo' => 'Last 12 months',
            '6mo' => 'Last 6 months',
            default => 'Custom period',
        };
    }

    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $first = substr($local, 0, 1);

        return $first.'***@'.$domain;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function filterTransactionsInRange(array $items, string $from, string $to): array
    {
        return array_values(array_filter($items, static function (array $tx) use ($from, $to): bool {
            $ymd = self::txCreatedYmdLagos($tx);

            return $ymd !== null && $ymd >= $from && $ymd <= $to;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{money_in: float, money_out: float, net: float, count: int}
     */
    private static function aggregateUtilitySummary(array $items): array
    {
        $moneyIn = 0.0;
        $moneyOut = 0.0;
        $count = 0;
        foreach ($items as $tx) {
            if (! self::txCountsTowardSummary($tx)) {
                continue;
            }
            $dir = self::txDirection((string) ($tx['type'] ?? ''));
            $amt = self::txAmount($tx);
            if ($dir === 'received') {
                $moneyIn += $amt;
                $count++;
            } elseif ($dir === 'sent') {
                $moneyOut += $amt;
                $count++;
            }
        }

        return [
            'money_in' => round($moneyIn, 2),
            'money_out' => round($moneyOut, 2),
            'net' => round($moneyIn - $moneyOut, 2),
            'count' => $count,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<array{date: string, description: string, type: string, direction: string, amount: float}>
     */
    private static function statementLineItems(array $transactions): array
    {
        $lines = [];
        foreach ($transactions as $tx) {
            if (! self::txIncludedInActivityList($tx)) {
                continue;
            }
            $type = (string) ($tx['type'] ?? '');
            $dir = self::txDirection($type);
            $lines[] = [
                'date' => self::txCreatedYmdLagos($tx) ?? '—',
                'description' => self::txDescription($tx),
                'type' => $type,
                'direction' => $dir === 'received' ? 'in' : ($dir === 'sent' ? 'out' : '—'),
                'amount' => self::txAmount($tx),
            ];
        }

        usort($lines, static function (array $a, array $b): int {
            return $b['date'] <=> $a['date'];
        });

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function txDescription(array $tx): string
    {
        $meta = is_array($tx['meta'] ?? null) ? $tx['meta'] : [];
        $base = '';
        foreach (['label', 'description', 'narration', 'remark', 'website_url'] as $key) {
            $v = $meta[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $base = trim($v);
                break;
            }
        }
        if ($base === '') {
            $base = self::spendingCategoryLabel((string) ($tx['type'] ?? ''));
        }
        $type = (string) ($tx['type'] ?? '');
        $status = self::merchantTxStatus($tx);
        if (
            $status !== ''
            && in_array($type, ['merchant_payment_in', 'merchant_withdrawal_out'], true)
            && $status !== 'approved'
        ) {
            return $base.' ('.ucfirst($status).')';
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function txAmount(array $tx): float
    {
        $amt = $tx['amount'] ?? 0;
        if (is_string($amt)) {
            $amt = (float) $amt;
        }
        $n = (float) $amt;

        return is_finite($n) ? round(abs($n), 2) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function txSuccessful(array $tx): bool
    {
        $meta = is_array($tx['meta'] ?? null) ? $tx['meta'] : [];
        $s = strtolower(trim((string) ($meta['status'] ?? $meta['state'] ?? '')));
        if (in_array($s, ['failed', 'reversed', 'cancelled', 'declined'], true)) {
            return false;
        }

        return ($meta['failed'] ?? false) !== true;
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function merchantTxStatus(array $tx): string
    {
        $meta = is_array($tx['meta'] ?? null) ? $tx['meta'] : [];

        return strtolower(trim((string) ($meta['status'] ?? $meta['state'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function txCountsTowardSummary(array $tx): bool
    {
        if (! self::txSuccessful($tx)) {
            return false;
        }
        $type = (string) ($tx['type'] ?? '');
        if ($type === 'merchant_payment_in') {
            $status = self::merchantTxStatus($tx);

            return $status === '' || $status === 'approved';
        }
        if ($type === 'merchant_withdrawal_out') {
            $status = self::merchantTxStatus($tx);
            if (in_array($status, ['pending', 'rejected'], true)) {
                return false;
            }

            return $status === '' || in_array($status, ['approved', 'processed'], true);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function txIncludedInActivityList(array $tx): bool
    {
        $type = (string) ($tx['type'] ?? '');
        if (in_array($type, ['merchant_payment_in', 'merchant_withdrawal_out'], true)) {
            $status = self::merchantTxStatus($tx);
            if (in_array($status, ['failed', 'reversed', 'cancelled', 'declined'], true)) {
                return false;
            }

            return true;
        }

        return self::txSuccessful($tx);
    }

    private static function txDirection(string $type): string
    {
        if (in_array($type, self::CREDIT_TYPES, true)) {
            return 'received';
        }
        if (in_array($type, self::DEBIT_TYPES, true)) {
            return 'sent';
        }

        return 'neutral';
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private static function txCreatedYmdLagos(array $tx): ?string
    {
        $raw = $tx['created_at'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $raw)->timezone(config('app.timezone', 'Africa/Lagos'))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function spendingCategoryLabel(string $type): string
    {
        return match (true) {
            $type === 'merchant_payment_in' => 'Website & checkout',
            $type === 'merchant_withdrawal_out' => 'Withdrawals',
            $type === 'business_rubies_in' => 'Business deposits',
            $type === 'business_name_registration_fee' => 'BNR fee',
            $type === 'bank_transfer_out' => 'Bank transfer',
            str_starts_with($type, 'vtu_') => match ($type) {
                'vtu_airtime' => 'Airtime',
                'vtu_data' => 'Data',
                default => 'Bills',
            },
            str_contains($type, 'p2p') => 'P2P',
            $type === 'topup' => 'Top up',
            str_starts_with($type, 'virtual_card') => 'Virtual card',
            in_array($type, ['partner_merchant_pay', 'tagine_merchant_pay'], true) => 'Merchant pay',
            str_starts_with($type, 'savings_') || in_array($type, ['spend_to_save', 'savings_lock'], true) => 'Savings',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    private static function escapeHtml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function fmtMoney(string $currency, float $n): string
    {
        $sym = $currency === 'NGN' ? '₦' : $currency.' ';
        $formatted = number_format($n, 2, '.', ',');

        return $sym.$formatted;
    }
}
