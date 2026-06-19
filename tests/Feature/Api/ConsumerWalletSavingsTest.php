<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerWalletApiAccount;
use App\Models\Setting;
use App\Models\WalletSavingsGoal;
use App\Models\WalletSavingsLock;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletSavingsService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerWalletSavingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('savings_enabled', true, 'boolean', 'savings');
        Setting::set('savings_lock_days', 60, 'integer', 'savings');
        Setting::set('savings_interest_rate_percent', 5.0, 'float', 'savings');
        Setting::set('savings_default_spend_to_save_percent', 10.0, 'float', 'savings');
        Setting::set('savings_max_spend_to_save_percent', 25.0, 'float', 'savings');
        Setting::set('savings_default_strict_save_percent', 5.0, 'float', 'savings');
        Setting::set('savings_max_strict_save_percent', 25.0, 'float', 'savings');
        Setting::set('savings_flexible_completion_bonus_percent', 2.0, 'float', 'savings');
    }

    private function actingWallet(float $balance = 10000): WhatsappWallet
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'balance' => $balance,
            'savings_balance' => 0,
            'flexible_savings_balance' => 0,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Saver',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Sanctum::actingAs($account, ['consumer']);

        return $wallet;
    }

    public function test_savings_summary_endpoint_returns_defaults(): void
    {
        $this->actingWallet();

        $this->getJson('/api/v1/consumer/savings')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.product_enabled', true)
            ->assertJsonPath('data.lock_days', 60)
            ->assertJsonPath('data.settings.spend_to_save_enabled', false)
            ->assertJsonPath('data.max_strict_save_percent', 25);
    }

    public function test_goal_preview_returns_pace(): void
    {
        $this->actingWallet();

        $this->postJson('/api/v1/consumer/savings/goals/preview', [
            'target_amount' => 100000,
            'save_type' => 'flexible',
            'duration_days' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['pace' => ['daily', 'weekly', 'monthly', 'pace_status']]]);
    }

    public function test_create_strict_goal_with_duration(): void
    {
        $this->actingWallet();

        $this->postJson('/api/v1/consumer/savings/goals', [
            'name' => 'Rent',
            'target_amount' => 100000,
            'save_type' => 'strict',
            'duration_days' => 90,
            'collection_mode' => 'per_incoming',
            'auto_save_percent' => 10,
            'auto_save_enabled' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.goal.save_type', 'strict')
            ->assertJsonPath('data.goal.auto_save_enabled', true);
    }

    public function test_manual_locked_deposit_debits_personal_balance(): void
    {
        $wallet = $this->actingWallet(5000);

        $this->postJson('/api/v1/consumer/savings/deposit', [
            'amount' => 1000,
            'lock_type' => 'locked',
            'ledger_scope' => 'personal',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
        $this->assertSame(1000.0, (float) $wallet->savings_balance);
        $this->assertSame(0.0, (float) $wallet->flexible_savings_balance);
    }

    public function test_flexible_deposit_and_withdraw(): void
    {
        $wallet = $this->actingWallet(5000);

        $this->postJson('/api/v1/consumer/savings/deposit', [
            'amount' => 500,
            'lock_type' => 'flexible',
        ])->assertCreated();

        $wallet->refresh();
        $this->assertSame(4500.0, (float) $wallet->balance);
        $this->assertSame(500.0, (float) $wallet->flexible_savings_balance);

        $this->postJson('/api/v1/consumer/savings/withdraw', ['amount' => 200])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $wallet->refresh();
        $this->assertSame(4700.0, (float) $wallet->balance);
        $this->assertSame(300.0, (float) $wallet->flexible_savings_balance);
    }

    public function test_strict_incoming_save_creates_locked_savings(): void
    {
        $wallet = $this->actingWallet(10000);
        $savings = app(ConsumerWalletSavingsService::class);

        $savings->createGoal($wallet, [
            'name' => 'Emergency',
            'target_amount' => 50000,
            'save_type' => WalletSavingsGoal::SAVE_TYPE_STRICT,
            'duration_days' => 60,
            'collection_mode' => WalletSavingsGoal::COLLECTION_PER_INCOMING,
            'auto_save_percent' => 10,
            'auto_save_enabled' => true,
            'ledger_scope' => WalletSavingsGoal::LEDGER_PERSONAL,
        ]);

        $creditTxn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_TOPUP,
            'amount' => 5000,
            'balance_after' => 15000,
        ]);

        $wallet->update(['balance' => 15000]);

        $savings->applySaveOnIncoming(
            $wallet->fresh(),
            5000,
            (int) $creditTxn->id,
            'topup',
            ConsumerWalletTransactionScope::SCOPE_PERSONAL,
        );

        $wallet->refresh();
        $this->assertSame(14500.0, (float) $wallet->balance);
        $this->assertSame(500.0, (float) $wallet->savings_balance);
    }

    public function test_balance_threshold_save_uses_percent_of_incoming_credit_when_above_threshold(): void
    {
        $wallet = $this->actingWallet(8000);
        $savings = app(ConsumerWalletSavingsService::class);

        $savings->createGoal($wallet, [
            'name' => 'Reserve',
            'target_amount' => 20000,
            'save_type' => WalletSavingsGoal::SAVE_TYPE_STRICT,
            'duration_days' => 30,
            'collection_mode' => WalletSavingsGoal::COLLECTION_BALANCE_THRESHOLD,
            'balance_threshold' => 10000,
            'auto_save_percent' => 50,
            'auto_save_enabled' => true,
            'ledger_scope' => WalletSavingsGoal::LEDGER_PERSONAL,
        ]);

        $creditTxn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_TOPUP,
            'amount' => 3000,
            'balance_after' => 11000,
        ]);

        $wallet->update(['balance' => 11000]);

        $savings->applyBalanceThresholdSave(
            $wallet->fresh(),
            3000,
            (int) $creditTxn->id,
            ConsumerWalletTransactionScope::SCOPE_PERSONAL,
            11000,
        );

        $wallet->refresh();
        // 50% of incoming credit 3000 = 1500
        $this->assertSame(9500.0, (float) $wallet->balance);
        $this->assertSame(1500.0, (float) $wallet->savings_balance);
    }

    public function test_strict_cap_blocks_second_goal_when_total_exceeds_max(): void
    {
        $wallet = $this->actingWallet(10000);

        $this->postJson('/api/v1/consumer/savings/goals', [
            'name' => 'Goal A',
            'target_amount' => 50000,
            'save_type' => 'strict',
            'duration_days' => 60,
            'auto_save_percent' => 15,
            'auto_save_enabled' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/consumer/savings/goals', [
            'name' => 'Goal B',
            'target_amount' => 30000,
            'save_type' => 'strict',
            'duration_days' => 30,
            'auto_save_percent' => 15,
            'auto_save_enabled' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_maturity_credit_does_not_trigger_strict_incoming_save(): void
    {
        $wallet = $this->actingWallet(10000);
        $savings = app(ConsumerWalletSavingsService::class);

        $savings->createGoal($wallet, [
            'name' => 'Strict',
            'target_amount' => 50000,
            'save_type' => WalletSavingsGoal::SAVE_TYPE_STRICT,
            'duration_days' => 60,
            'collection_mode' => WalletSavingsGoal::COLLECTION_PER_INCOMING,
            'auto_save_percent' => 10,
            'auto_save_enabled' => true,
        ]);

        $maturityTxn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_SAVINGS_MATURITY,
            'amount' => 5000,
            'balance_after' => 15000,
        ]);

        $wallet->update(['balance' => 15000]);

        $savings->handleIncomingCredit(
            $wallet->fresh(),
            5000,
            (int) $maturityTxn->id,
            'topup',
            ConsumerWalletTransactionScope::SCOPE_PERSONAL,
            10000,
            15000,
        );

        $wallet->refresh();
        $this->assertSame(15000.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->savings_balance);
    }

    public function test_savings_summary_includes_remaining_strict_percent(): void
    {
        $this->actingWallet();

        $this->getJson('/api/v1/consumer/savings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['remaining_strict_percent']]);
    }

    public function test_spend_to_save_creates_locked_savings_without_flexible_balance(): void
    {
        $wallet = $this->actingWallet(10000);
        $savings = app(ConsumerWalletSavingsService::class);
        $savings->updateSettings($wallet, [
            'spend_to_save_enabled' => true,
            'spend_to_save_percent' => 10,
        ]);

        $sourceTxn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
            'amount' => 2000,
            'balance_after' => 8000,
        ]);

        $wallet->update(['balance' => 8000]);

        $savings->applySpendToSave($wallet->fresh(), 2000, (int) $sourceTxn->id, 'p2p');

        $wallet->refresh();
        $this->assertSame(7800.0, (float) $wallet->balance);
        $this->assertSame(200.0, (float) $wallet->savings_balance);
    }

    public function test_maturity_credits_wallet_with_interest(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $wallet = $this->actingWallet(5000);
        $savings = app(ConsumerWalletSavingsService::class);
        $savings->lockDeposit($wallet->fresh(), 1000, WalletSavingsLock::SOURCE_MANUAL);

        $lock = WalletSavingsLock::query()->where('whatsapp_wallet_id', $wallet->id)->firstOrFail();
        $lock->update(['matures_at' => now()->subMinute()]);

        Carbon::setTestNow('2026-03-02 12:00:00');
        $result = $savings->processDueMaturities();

        $this->assertSame(1, $result['processed']);
        $wallet->refresh();
        $this->assertSame(0.0, (float) $wallet->savings_balance);
        $this->assertSame(5050.0, (float) $wallet->balance);
        $this->assertDatabaseHas('wallet_savings_locks', [
            'id' => $lock->id,
            'status' => WalletSavingsLock::STATUS_MATURED,
            'interest_amount' => 50,
        ]);

        Carbon::setTestNow();
    }
}
