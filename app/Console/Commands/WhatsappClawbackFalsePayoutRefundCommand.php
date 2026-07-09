<?php

namespace App\Console\Commands;

use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappWalletBankPayoutClawbackService;
use Illuminate\Console\Command;

class WhatsappClawbackFalsePayoutRefundCommand extends Command
{
    protected $signature = 'whatsapp:clawback-false-refund
                            {reference : external_reference / payout reference}
                            {--force : Skip live MevonPay success confirmation}
                            {--yes : Run without interactive confirmation}
                            {--admin= : Optional admin id to record on meta}';

    protected $description = 'Debit a wallet after a false auto-refund when MevonPay confirms the bank payout succeeded';

    public function handle(WhatsappWalletBankPayoutClawbackService $clawback): int
    {
        $reference = trim((string) $this->argument('reference'));
        $txn = WhatsappWalletTransaction::query()
            ->where('external_reference', $reference)
            ->orWhere('meta->payout_reference', $reference)
            ->first();

        if (! $txn) {
            $this->error("Transaction not found for reference: {$reference}");

            return self::FAILURE;
        }

        $this->line("Transaction #{$txn->id} wallet={$txn->whatsapp_wallet_id} amount={$txn->amount} scope=".($txn->ledger_scope ?: 'personal'));

        if (! $this->option('yes') && ! $this->confirm('Claw back the false refund from this wallet?', true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $adminId = $this->option('admin');
        $result = $clawback->clawbackTransaction(
            $txn,
            is_numeric($adminId) ? (int) $adminId : null,
            (bool) $this->option('force'),
        );

        if (! ($result['ok'] ?? false)) {
            $this->error($result['message'] ?? 'Clawback failed.');

            return self::FAILURE;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
