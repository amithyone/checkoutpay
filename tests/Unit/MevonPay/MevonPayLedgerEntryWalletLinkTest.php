<?php

namespace Tests\Unit\MevonPay;

use App\Models\MevonPayLedgerEntry;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MevonPayLedgerEntryWalletLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_entry_links_to_wallet_transaction_by_morph_source(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'balance' => 1000,
        ]);

        $txn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'amount' => 500,
            'external_reference' => 'waw_link_test_ref',
            'meta' => ['payout_bucket' => 'failed'],
        ]);

        $entry = MevonPayLedgerEntry::query()->create([
            'direction' => MevonPayLedgerEntry::DIRECTION_OUTBOUND,
            'flow_type' => MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER,
            'gross_amount' => 500,
            'net_mevon_impact' => -500,
            'payout_reference' => 'waw_link_test_ref',
            'source_type' => $txn->getMorphClass(),
            'source_id' => $txn->id,
            'occurred_at' => now(),
        ]);

        $this->assertSame($txn->id, $entry->resolveWalletTransaction()?->id);
        $this->assertStringContainsString('/whatsapp-wallet/transactions/'.$txn->id, (string) $entry->adminWalletTransactionUrl());
        $this->assertSame('Wallet transaction #'.$txn->id, $entry->adminWalletTransactionLabel());
    }

    public function test_ledger_entry_resolves_wallet_transaction_by_reference_fallback(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348098765432',
            'balance' => 2000,
        ]);

        $txn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'amount' => 300,
            'external_reference' => 'waw_fallback_ref',
            'meta' => [],
        ]);

        $entry = MevonPayLedgerEntry::query()->create([
            'direction' => MevonPayLedgerEntry::DIRECTION_OUTBOUND,
            'flow_type' => MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER,
            'gross_amount' => 300,
            'net_mevon_impact' => -300,
            'payout_reference' => 'waw_fallback_ref',
            'occurred_at' => now(),
        ]);

        $this->assertSame($txn->id, $entry->resolveWalletTransaction()?->id);
    }
}
