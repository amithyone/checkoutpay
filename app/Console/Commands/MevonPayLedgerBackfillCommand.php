<?php

namespace App\Console\Commands;

use App\Models\MevonPayLedgerEntry;
use App\Models\Payment;
use App\Models\WhatsappWalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayLedgerRecorder;
use Illuminate\Console\Command;

class MevonPayLedgerBackfillCommand extends Command
{
    protected $signature = 'mevon:ledger-backfill {--dry-run : Show counts without writing}';

    protected $description = 'Backfill mevon_pay_ledger_entries from historical payments, wallet txns, and withdrawals';

    public function handle(MevonPayLedgerRecorder $recorder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $inbound = 0;
        $outbound = 0;

        Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->whereNotNull('external_reference')
            ->whereIn('payment_source', [
                Payment::SOURCE_EXTERNAL_MEVONPAY,
                Payment::SOURCE_EXTERNAL_SLA,
                Payment::SOURCE_EXTERNAL_MAVONPAY,
                Payment::SOURCE_BUSINESS_RUBIES_VA,
                Payment::SOURCE_WHATSAPP_WALLET,
            ])
            ->orderBy('id')
            ->chunk(200, function ($payments) use ($recorder, &$inbound, $dryRun) {
                foreach ($payments as $payment) {
                    $ref = trim((string) $payment->external_reference);
                    if ($ref === '') {
                        continue;
                    }
                    $gross = (float) ($payment->received_amount ?? $payment->amount);
                    if ($gross <= 0) {
                        continue;
                    }
                    $flow = match ($payment->payment_source) {
                        Payment::SOURCE_BUSINESS_RUBIES_VA => MevonPayLedgerEntry::FLOW_BUSINESS_RUBIES_VA,
                        Payment::SOURCE_WHATSAPP_WALLET => MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP,
                        default => MevonPayLedgerEntry::FLOW_MERCHANT_CHECKOUT,
                    };
                    if ($dryRun) {
                        $inbound++;

                        continue;
                    }
                    if ($recorder->recordInbound($flow, $gross, $ref, (string) $payment->account_number, $payment, ['backfilled' => true], $payment->matched_at ?? $payment->created_at)) {
                        $inbound++;
                    }
                }
            });

        WhatsappWalletTransaction::query()
            ->where('type', WhatsappWalletTransaction::TYPE_TOPUP)
            ->whereNotNull('external_reference')
            ->orderBy('id')
            ->chunk(200, function ($txns) use ($recorder, &$inbound, $dryRun) {
                foreach ($txns as $txn) {
                    $ref = trim((string) $txn->external_reference);
                    if ($ref === '') {
                        continue;
                    }
                    $meta = is_array($txn->meta) ? $txn->meta : [];
                    $gross = (float) ($meta['mevon_reported_gross'] ?? $meta['reported_amount'] ?? $txn->amount);
                    if ($gross <= 0) {
                        continue;
                    }
                    if ($dryRun) {
                        $inbound++;

                        continue;
                    }
                    if ($recorder->recordInbound(MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP, $gross, $ref, (string) ($meta['receive_account_number'] ?? ''), $txn, ['backfilled' => true], $txn->created_at)) {
                        $inbound++;
                    }
                }
            });

        WithdrawalRequest::query()
            ->where('payout_provider', MavonPayTransferService::PROVIDER)
            ->whereNotNull('payout_reference')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($recorder, &$outbound, $dryRun) {
                foreach ($rows as $withdrawal) {
                    $ref = trim((string) $withdrawal->payout_reference);
                    if ($ref === '') {
                        continue;
                    }
                    if ($dryRun) {
                        $outbound++;

                        continue;
                    }
                    if ($recorder->recordOutbound(MevonPayLedgerEntry::FLOW_BUSINESS_WITHDRAWAL, (float) $withdrawal->amount, $ref, MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER, (string) ($withdrawal->payout_status ?? MavonPayTransferService::BUCKET_FAILED), (string) config('services.mevonpay.debit_account_number', ''), $withdrawal, ['backfilled' => true], $withdrawal->payout_attempted_at ?? $withdrawal->created_at)) {
                        $outbound++;
                    }
                }
            });

        WhatsappWalletTransaction::query()
            ->where('type', WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT)
            ->whereNotNull('external_reference')
            ->orderBy('id')
            ->chunk(200, function ($txns) use ($recorder, &$outbound, $dryRun) {
                foreach ($txns as $txn) {
                    $ref = trim((string) $txn->external_reference);
                    if ($ref === '') {
                        continue;
                    }
                    $meta = is_array($txn->meta) ? $txn->meta : [];
                    $amount = (float) ($meta['payout_amount'] ?? $txn->amount);
                    $bucket = (string) ($meta['payout_bucket'] ?? MavonPayTransferService::BUCKET_FAILED);
                    if ($dryRun) {
                        $outbound++;

                        continue;
                    }
                    if ($recorder->recordOutbound(MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER, $amount, $ref, (string) ($meta['payout_api'] ?? MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER), $bucket, null, $txn, ['backfilled' => true], $txn->created_at)) {
                        $outbound++;
                    }
                }
            });

        $this->info(($dryRun ? '[dry-run] Would backfill' : 'Backfilled')." inbound: {$inbound}, outbound: {$outbound}");

        return self::SUCCESS;
    }
}
