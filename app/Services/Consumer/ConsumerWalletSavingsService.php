<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\WalletSavingsGoal;
use App\Models\WalletSavingsLock;
use App\Models\WalletSavingsSetting;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ConsumerWalletSavingsService
{
    public function __construct(
        private ConsumerBusinessWalletLedgerService $businessLedger,
    ) {}

    public function isProductEnabled(): bool
    {
        return (bool) Setting::get('savings_enabled', true);
    }

    public function lockDays(): int
    {
        return max(1, (int) Setting::get('savings_lock_days', 60));
    }

    public function interestRatePercent(): float
    {
        return max(0.0, (float) Setting::get('savings_interest_rate_percent', 5.0));
    }

    public function defaultSpendToSavePercent(): float
    {
        return max(0.0, (float) Setting::get('savings_default_spend_to_save_percent', 10.0));
    }

    public function maxSpendToSavePercent(): float
    {
        return max(0.0, (float) Setting::get('savings_max_spend_to_save_percent', 25.0));
    }

    public function minDepositAmount(): float
    {
        return 1.0;
    }

    public function ensureSettings(WhatsappWallet $wallet): WalletSavingsSetting
    {
        $existing = WalletSavingsSetting::query()->where('whatsapp_wallet_id', $wallet->id)->first();
        if ($existing) {
            return $existing;
        }

        return WalletSavingsSetting::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'spend_to_save_enabled' => false,
            'spend_to_save_percent' => $this->defaultSpendToSavePercent(),
            'reminder_enabled' => false,
            'reminder_frequency' => WalletSavingsSetting::FREQUENCY_OFF,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(WhatsappWallet $wallet): array
    {
        $settings = $this->ensureSettings($wallet->fresh());
        $wallet = $wallet->fresh();

        $activeLocks = WalletSavingsLock::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', WalletSavingsLock::STATUS_ACTIVE)
            ->orderByRaw('CASE WHEN matures_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('matures_at')
            ->get();

        $lockedActive = $activeLocks->where('lock_type', WalletSavingsLock::LOCK_TYPE_LOCKED)->values();
        $flexibleActive = $activeLocks->where('lock_type', WalletSavingsLock::LOCK_TYPE_FLEXIBLE)->values();

        $interestEarned = (float) WalletSavingsLock::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', WalletSavingsLock::STATUS_MATURED)
            ->where('lock_type', WalletSavingsLock::LOCK_TYPE_LOCKED)
            ->sum('interest_amount');

        $nextMaturity = $lockedActive->first();

        $goals = WalletSavingsGoal::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', WalletSavingsGoal::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->get()
            ->map(fn (WalletSavingsGoal $g) => $this->formatGoal($g))
            ->values()
            ->all();

        return [
            'product_enabled' => $this->isProductEnabled(),
            'savings_balance' => (float) ($wallet->savings_balance ?? 0),
            'flexible_savings_balance' => (float) ($wallet->flexible_savings_balance ?? 0),
            'locked_savings_balance' => (float) ($wallet->savings_balance ?? 0),
            'interest_earned' => round($interestEarned, 2),
            'lock_days' => $this->lockDays(),
            'interest_rate_percent' => $this->interestRatePercent(),
            'max_spend_to_save_percent' => $this->maxSpendToSavePercent(),
            'default_spend_to_save_percent' => $this->defaultSpendToSavePercent(),
            'business_wallet_enabled' => $wallet->hasBusinessWallet(),
            'next_maturity_at' => $nextMaturity?->matures_at?->toIso8601String(),
            'settings' => $this->formatSettings($settings),
            'goals' => $goals,
            'active_locks' => $lockedActive->take(10)->map(fn (WalletSavingsLock $l) => $this->formatLock($l))->values()->all(),
            'flexible_locks' => $flexibleActive->take(10)->map(fn (WalletSavingsLock $l) => $this->formatLock($l))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message?: string, settings?: array<string, mixed>}
     */
    public function updateSettings(WhatsappWallet $wallet, array $payload): array
    {
        if (! $this->isProductEnabled()) {
            return ['ok' => false, 'message' => 'Savings is not available right now.'];
        }

        $settings = $this->ensureSettings($wallet);
        $maxPercent = $this->maxSpendToSavePercent();

        if (array_key_exists('spend_to_save_enabled', $payload)) {
            $settings->spend_to_save_enabled = (bool) $payload['spend_to_save_enabled'];
        }
        if (array_key_exists('spend_to_save_percent', $payload)) {
            $percent = round((float) $payload['spend_to_save_percent'], 2);
            if ($percent < 0 || $percent > $maxPercent + 0.0001) {
                return ['ok' => false, 'message' => 'Save percentage must be between 0 and '.$maxPercent.'.'];
            }
            $settings->spend_to_save_percent = $percent;
        }
        if (array_key_exists('reminder_enabled', $payload)) {
            $settings->reminder_enabled = (bool) $payload['reminder_enabled'];
        }
        if (array_key_exists('reminder_frequency', $payload)) {
            $freq = (string) $payload['reminder_frequency'];
            if (! in_array($freq, [
                WalletSavingsSetting::FREQUENCY_OFF,
                WalletSavingsSetting::FREQUENCY_WEEKLY,
                WalletSavingsSetting::FREQUENCY_AFTER_SPEND,
            ], true)) {
                return ['ok' => false, 'message' => 'Invalid reminder frequency.'];
            }
            $settings->reminder_frequency = $freq;
        }
        if (array_key_exists('reminder_weekday', $payload)) {
            $day = $payload['reminder_weekday'];
            $settings->reminder_weekday = $day === null ? null : max(0, min(6, (int) $day));
        }
        if (array_key_exists('reminder_hour_local', $payload)) {
            $hour = $payload['reminder_hour_local'];
            $settings->reminder_hour_local = $hour === null ? null : max(0, min(23, (int) $hour));
        }

        $settings->save();

        return ['ok' => true, 'settings' => $this->formatSettings($settings->fresh())];
    }

    /**
     * @return array{ok: bool, message?: string, goal?: array<string, mixed>}
     */
    public function createGoal(WhatsappWallet $wallet, string $name, float $targetAmount): array
    {
        if (! $this->isProductEnabled()) {
            return ['ok' => false, 'message' => 'Savings is not available right now.'];
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'message' => 'Enter a goal name (max 120 characters).'];
        }
        if ($targetAmount < 100) {
            return ['ok' => false, 'message' => 'Target amount must be at least ₦100.'];
        }

        $goal = WalletSavingsGoal::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'name' => $name,
            'target_amount' => round($targetAmount, 2),
            'saved_amount' => 0,
            'status' => WalletSavingsGoal::STATUS_ACTIVE,
        ]);

        return ['ok' => true, 'goal' => $this->formatGoal($goal)];
    }

    /**
     * @return array{ok: bool, message?: string, goal?: array<string, mixed>}
     */
    public function updateGoal(WhatsappWallet $wallet, int $goalId, array $payload): array
    {
        $goal = WalletSavingsGoal::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('id', $goalId)
            ->first();

        if (! $goal) {
            return ['ok' => false, 'message' => 'Goal not found.'];
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return ['ok' => false, 'message' => 'Goal name is required.'];
            }
            $goal->name = $name;
        }
        if (array_key_exists('target_amount', $payload)) {
            $target = round((float) $payload['target_amount'], 2);
            if ($target < 100) {
                return ['ok' => false, 'message' => 'Target amount must be at least ₦100.'];
            }
            $goal->target_amount = $target;
        }
        if (array_key_exists('status', $payload)) {
            $status = (string) $payload['status'];
            if (! in_array($status, [WalletSavingsGoal::STATUS_ACTIVE, WalletSavingsGoal::STATUS_ARCHIVED], true)) {
                return ['ok' => false, 'message' => 'Invalid goal status.'];
            }
            $goal->status = $status;
        }

        $goal->save();

        return ['ok' => true, 'goal' => $this->formatGoal($goal->fresh())];
    }

    /**
     * @return array{ok: bool, message?: string, lock?: array<string, mixed>}
     */
    public function lockDeposit(
        WhatsappWallet $wallet,
        float $amount,
        string $source,
        ?int $goalId = null,
        ?int $sourceTransactionId = null,
        string $lockType = WalletSavingsLock::LOCK_TYPE_LOCKED,
        string $ledgerScope = ConsumerWalletTransactionScope::SCOPE_PERSONAL,
    ): array {
        if (! $this->isProductEnabled()) {
            return ['ok' => false, 'message' => 'Savings is not available right now.'];
        }

        $lockType = $lockType === WalletSavingsLock::LOCK_TYPE_FLEXIBLE
            ? WalletSavingsLock::LOCK_TYPE_FLEXIBLE
            : WalletSavingsLock::LOCK_TYPE_LOCKED;
        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);

        if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS && ! $wallet->fresh()->hasBusinessWallet()) {
            return ['ok' => false, 'message' => 'Business wallet is not linked yet.'];
        }

        $amount = round($amount, 2);
        if ($amount < $this->minDepositAmount()) {
            return ['ok' => false, 'message' => 'Minimum save amount is ₦'.number_format($this->minDepositAmount(), 0).'.'];
        }

        if ($sourceTransactionId !== null) {
            $exists = WalletSavingsLock::query()->where('source_transaction_id', $sourceTransactionId)->exists();
            if ($exists) {
                return ['ok' => true, 'message' => 'Already saved for this transaction.'];
            }
        }

        $goal = null;
        if ($goalId !== null) {
            $goal = WalletSavingsGoal::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->where('id', $goalId)
                ->where('status', WalletSavingsGoal::STATUS_ACTIVE)
                ->first();
            if (! $goal) {
                return ['ok' => false, 'message' => 'Goal not found.'];
            }
            $source = WalletSavingsLock::SOURCE_GOAL;
        }

        $isFlexible = $lockType === WalletSavingsLock::LOCK_TYPE_FLEXIBLE;
        $isSpendToSave = $source === WalletSavingsLock::SOURCE_SPEND_TO_SAVE;

        try {
            $lock = DB::transaction(function () use ($wallet, $amount, $source, $goal, $sourceTransactionId, $lockType, $ledgerScope, $isFlexible) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('Wallet not found.');
                }
                if (! $w->hasPin()) {
                    throw new \RuntimeException('Set your wallet PIN before saving.');
                }

                $balanceAfterSpendable = $this->debitForSavings($w, $amount, $ledgerScope);

                $now = now();
                $rate = $isFlexible ? 0.0 : $this->interestRatePercent();
                $maturesAt = $isFlexible ? null : $now->copy()->addDays($this->lockDays());

                if ($isFlexible) {
                    $w->flexible_savings_balance = round((float) $w->flexible_savings_balance + $amount, 2);
                } else {
                    $w->savings_balance = round((float) $w->savings_balance + $amount, 2);
                }
                $w->save();

                $txn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_SAVINGS_LOCK,
                    'ledger_scope' => $ledgerScope,
                    'amount' => $amount,
                    'balance_after' => $balanceAfterSpendable,
                    'meta' => [
                        'channel' => 'consumer_savings',
                        'savings_source' => $source,
                        'lock_type' => $lockType,
                        'ledger_scope' => $ledgerScope,
                        'lock_days' => $isFlexible ? null : $this->lockDays(),
                        'interest_rate_percent' => $rate,
                        'matures_at' => $maturesAt?->toIso8601String(),
                        'goal_id' => $goal?->id,
                    ],
                ]);

                $lock = WalletSavingsLock::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'wallet_savings_goal_id' => $goal?->id,
                    'source_transaction_id' => $sourceTransactionId ?? $txn->id,
                    'source' => $source,
                    'lock_type' => $lockType,
                    'ledger_scope' => $ledgerScope,
                    'amount' => $amount,
                    'interest_rate_percent' => $rate,
                    'locked_at' => $now,
                    'matures_at' => $maturesAt,
                    'status' => WalletSavingsLock::STATUS_ACTIVE,
                    'meta' => ['wallet_transaction_id' => $txn->id],
                ]);

                if ($goal) {
                    $goal->saved_amount = round((float) $goal->saved_amount + $amount, 2);
                    if ((float) $goal->saved_amount >= (float) $goal->target_amount) {
                        $goal->status = WalletSavingsGoal::STATUS_COMPLETED;
                    }
                    $goal->save();
                }

                return $lock;
            });
        } catch (\Throwable $e) {
            Log::warning('consumer_savings.lock_deposit_failed', [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $freshWallet = $wallet->fresh();

        if ($isFlexible && ! $isSpendToSave) {
            $this->notifyFlexibleStarted($freshWallet, (float) $lock->amount, $ledgerScope);
        }

        $message = $isFlexible
            ? 'Flexible savings started — withdraw anytime, no interest.'
            : 'Locked savings started. Unlocks '.$lock->matures_at?->timezone('Africa/Lagos')->format('M j, Y').' with bonus interest.';

        return [
            'ok' => true,
            'message' => $message,
            'lock' => $this->formatLock($lock->fresh()),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, amount?: float, ledger_scope?: string}
     */
    public function withdrawFlexible(
        WhatsappWallet $wallet,
        float $amount,
        ?string $ledgerScope = null,
    ): array {
        if (! $this->isProductEnabled()) {
            return ['ok' => false, 'message' => 'Savings is not available right now.'];
        }

        $amount = round($amount, 2);
        if ($amount < $this->minDepositAmount()) {
            return ['ok' => false, 'message' => 'Minimum withdrawal is ₦'.number_format($this->minDepositAmount(), 0).'.'];
        }

        try {
            $result = DB::transaction(function () use ($wallet, $amount, $ledgerScope) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('Wallet not found.');
                }
                if ((float) $w->flexible_savings_balance + 0.0001 < $amount) {
                    throw new \RuntimeException('Insufficient flexible savings balance.');
                }

                $remaining = $amount;
                $locks = WalletSavingsLock::query()
                    ->where('whatsapp_wallet_id', $w->id)
                    ->where('status', WalletSavingsLock::STATUS_ACTIVE)
                    ->where('lock_type', WalletSavingsLock::LOCK_TYPE_FLEXIBLE)
                    ->when($ledgerScope !== null, fn ($q) => $q->where('ledger_scope', ConsumerWalletTransactionScope::normalize($ledgerScope)))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($locks->isEmpty()) {
                    throw new \RuntimeException('No flexible savings available to withdraw.');
                }

                $available = round((float) $locks->sum('amount'), 2);
                if ($available + 0.0001 < $amount) {
                    throw new \RuntimeException('Insufficient flexible savings in this wallet.');
                }

                $creditsByScope = [];
                foreach ($locks as $lock) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min((float) $lock->amount, $remaining);
                    $scope = ConsumerWalletTransactionScope::normalize((string) $lock->ledger_scope);
                    $creditsByScope[$scope] = ($creditsByScope[$scope] ?? 0) + $take;

                    $newLockAmount = round((float) $lock->amount - $take, 2);
                    if ($newLockAmount <= 0) {
                        $lock->status = WalletSavingsLock::STATUS_WITHDRAWN;
                        $lock->amount = 0;
                    } else {
                        $lock->amount = $newLockAmount;
                    }
                    $lock->save();

                    $remaining = round($remaining - $take, 2);
                }

                $w->flexible_savings_balance = max(0, round((float) $w->flexible_savings_balance - $amount, 2));
                $w->save();

                $primaryScope = $ledgerScope !== null
                    ? ConsumerWalletTransactionScope::normalize($ledgerScope)
                    : array_key_first($creditsByScope);

                $balanceAfter = null;
                foreach ($creditsByScope as $scope => $creditAmount) {
                    $balanceAfter = $this->creditSpendable($w, $creditAmount, $scope);
                }

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_SAVINGS_WITHDRAW,
                    'ledger_scope' => $primaryScope ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter ?? (float) $w->balance,
                    'meta' => [
                        'channel' => 'consumer_savings',
                        'lock_type' => WalletSavingsLock::LOCK_TYPE_FLEXIBLE,
                        'credits_by_scope' => $creditsByScope,
                    ],
                ]);

                return [
                    'amount' => $amount,
                    'ledger_scope' => $primaryScope ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                    'balance_after' => $balanceAfter,
                ];
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => 'Flexible savings withdrawn to your wallet.',
            'amount' => $result['amount'],
            'ledger_scope' => $result['ledger_scope'],
        ];
    }

    private function debitForSavings(WhatsappWallet $wallet, float $amount, string $ledgerScope): float
    {
        if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
            $debit = $this->businessLedger->debitLockedWallet($wallet, $amount);
            if (! ($debit['ok'] ?? false)) {
                throw new \RuntimeException($debit['message'] ?? 'Insufficient business balance.');
            }
            $wallet->save();

            return (float) ($debit['balance_after'] ?? $this->businessLedger->resolvedBalance($wallet));
        }

        $wallet->resetDailyTransferIfNeeded();
        $check = $wallet->canDebit($amount);
        if (! $check['ok']) {
            throw new \RuntimeException($check['message'] ?? 'Insufficient balance.');
        }

        $newBal = round((float) $wallet->balance - $amount, 2);
        $wallet->balance = $newBal;
        $wallet->daily_transfer_total = round((float) $wallet->daily_transfer_total + $amount, 2);
        $wallet->daily_transfer_for_date = now()->toDateString();

        return $newBal;
    }

    private function creditSpendable(WhatsappWallet $wallet, float $amount, string $ledgerScope): float
    {
        if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
            $credit = $this->businessLedger->creditLockedWallet($wallet, $amount);
            $wallet->save();

            return (float) ($credit['balance_after'] ?? $this->businessLedger->resolvedBalance($wallet));
        }

        $wallet->balance = round((float) $wallet->balance + $amount, 2);
        $wallet->save();

        return (float) $wallet->balance;
    }

    private function notifyFlexibleStarted(WhatsappWallet $wallet, float $amount, string $ledgerScope): void
    {
        $scopeLabel = $ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS ? 'business' : 'personal';
        app(ConsumerWalletPushNotificationService::class)->notifyGeneric(
            $wallet,
            'Flexible savings started',
            sprintf(
                'You saved %s to flexible savings (%s wallet). Withdraw anytime — no lock, no interest.',
                $this->moneyLabel($amount, $wallet),
                $scopeLabel,
            ),
            ['type' => 'savings_flexible_started', 'screen' => 'saving'],
        );
    }

    public function applySpendToSave(
        WhatsappWallet $wallet,
        float $spendAmount,
        int $sourceTransactionId,
        string $sourceType,
    ): void {
        if (! $this->isProductEnabled() || $spendAmount <= 0) {
            return;
        }

        $settings = $this->ensureSettings($wallet->fresh());
        if (! $settings->spend_to_save_enabled || (float) $settings->spend_to_save_percent <= 0) {
            return;
        }

        $saveAmount = round($spendAmount * ((float) $settings->spend_to_save_percent / 100), 2);
        if ($saveAmount < $this->minDepositAmount()) {
            return;
        }

        $fresh = $wallet->fresh();
        if ((float) $fresh->balance + 0.0001 < $saveAmount) {
            return;
        }

        $result = $this->lockDeposit(
            $fresh,
            $saveAmount,
            WalletSavingsLock::SOURCE_SPEND_TO_SAVE,
            null,
            $sourceTransactionId,
            WalletSavingsLock::LOCK_TYPE_LOCKED,
            ConsumerWalletTransactionScope::SCOPE_PERSONAL,
        );

        if (! ($result['ok'] ?? false) && ($result['message'] ?? '') !== 'Already saved for this transaction.') {
            Log::info('consumer_savings.spend_to_save_skipped', [
                'wallet_id' => $wallet->id,
                'source_transaction_id' => $sourceTransactionId,
                'source_type' => $sourceType,
                'message' => $result['message'] ?? 'unknown',
            ]);
        }
    }

    /**
     * @return array{processed: int, failed: int}
     */
    public function processDueMaturities(): array
    {
        $processed = 0;
        $failed = 0;

        WalletSavingsLock::query()
            ->where('status', WalletSavingsLock::STATUS_ACTIVE)
            ->where('lock_type', WalletSavingsLock::LOCK_TYPE_LOCKED)
            ->whereNotNull('matures_at')
            ->where('matures_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($locks) use (&$processed, &$failed) {
                foreach ($locks as $lock) {
                    try {
                        $this->matureLock($lock);
                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('consumer_savings.maturity_failed', [
                            'lock_id' => $lock->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['processed' => $processed, 'failed' => $failed];
    }

    public function matureLock(WalletSavingsLock $lock): void
    {
        DB::transaction(function () use ($lock) {
            $row = WalletSavingsLock::query()->lockForUpdate()->find($lock->id);
            if (! $row || $row->status !== WalletSavingsLock::STATUS_ACTIVE) {
                return;
            }
            if ($row->matures_at === null || $row->matures_at->isFuture()) {
                return;
            }
            if ($row->lock_type !== WalletSavingsLock::LOCK_TYPE_LOCKED) {
                return;
            }

            $w = WhatsappWallet::query()->lockForUpdate()->find($row->whatsapp_wallet_id);
            if (! $w) {
                $row->status = WalletSavingsLock::STATUS_FAILED;
                $row->save();

                throw new \RuntimeException('Wallet missing for lock '.$row->id);
            }

            $principal = round((float) $row->amount, 2);
            $interest = round($principal * ((float) $row->interest_rate_percent / 100), 2);
            $payout = round($principal + $interest, 2);
            $ledgerScope = ConsumerWalletTransactionScope::normalize((string) $row->ledger_scope);

            $balanceAfter = $this->creditSpendable($w, $payout, $ledgerScope);
            $newSavings = max(0, round((float) $w->savings_balance - $principal, 2));

            $w->savings_balance = $newSavings;
            $w->save();

            $row->interest_amount = $interest;
            $row->status = WalletSavingsLock::STATUS_MATURED;
            $row->matured_at = now();
            $row->save();

            WhatsappWalletTransaction::query()->create([
                'whatsapp_wallet_id' => $w->id,
                'sender_name' => $w->normalizedSenderName(),
                'type' => WhatsappWalletTransaction::TYPE_SAVINGS_MATURITY,
                'ledger_scope' => $ledgerScope,
                'amount' => $payout,
                'balance_after' => $balanceAfter,
                'meta' => [
                    'channel' => 'consumer_savings',
                    'lock_id' => $row->id,
                    'principal' => $principal,
                    'interest' => $interest,
                    'interest_rate_percent' => (float) $row->interest_rate_percent,
                    'ledger_scope' => $ledgerScope,
                ],
            ]);

            if ($row->wallet_savings_goal_id) {
                $goal = WalletSavingsGoal::query()->find($row->wallet_savings_goal_id);
                if ($goal) {
                    $goal->saved_amount = max(0, round((float) $goal->saved_amount - $principal, 2));
                    $goal->save();
                }
            }
        });

        $lock = $lock->fresh();
        if ($lock && $lock->status === WalletSavingsLock::STATUS_MATURED) {
            $wallet = WhatsappWallet::query()->find($lock->whatsapp_wallet_id);
            if ($wallet) {
                $payout = round((float) $lock->amount + (float) ($lock->interest_amount ?? 0), 2);
                $ledgerScope = ConsumerWalletTransactionScope::normalize((string) $lock->ledger_scope);
                app(ConsumerWalletPushNotificationService::class)->notifyMoneyReceived(
                    $wallet,
                    $payout,
                    (float) ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS
                        ? $this->businessLedger->resolvedBalance($wallet->fresh())
                        : $wallet->fresh()->balance),
                    sprintf(
                        'Your savings unlocked — %s including %s interest.',
                        $this->moneyLabel($payout, $wallet),
                        $this->moneyLabel((float) ($lock->interest_amount ?? 0), $wallet),
                    ),
                    ['credit_source' => 'savings_maturity'],
                );
            }
        }
    }

    /**
     * @return array{sent: int}
     */
    public function sendDueReminders(): array
    {
        $sent = 0;
        $now = now()->timezone('Africa/Lagos');

        WalletSavingsSetting::query()
            ->where('reminder_enabled', true)
            ->where('reminder_frequency', WalletSavingsSetting::FREQUENCY_WEEKLY)
            ->with('wallet')
            ->chunkById(100, function ($rows) use ($now, &$sent) {
                foreach ($rows as $settings) {
                    $wallet = $settings->wallet;
                    if (! $wallet || ! $wallet->isActive()) {
                        continue;
                    }
                    if ($settings->reminder_hour_local !== null && (int) $now->hour !== (int) $settings->reminder_hour_local) {
                        continue;
                    }
                    if ($settings->reminder_weekday !== null && (int) $now->dayOfWeek !== (int) $settings->reminder_weekday) {
                        continue;
                    }
                    if ($settings->last_reminder_sent_at && $settings->last_reminder_sent_at->greaterThan($now->copy()->subDays(6))) {
                        continue;
                    }

                    $this->pushReminder($wallet, 'Weekly savings check-in — open Saving to add to your goals.');
                    $settings->last_reminder_sent_at = now();
                    $settings->save();
                    $sent++;
                }
            });

        return ['sent' => $sent];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLocks(WhatsappWallet $wallet, int $limit = 30): array
    {
        return WalletSavingsLock::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (WalletSavingsLock $l) => $this->formatLock($l))
            ->values()
            ->all();
    }

    private function pushReminder(WhatsappWallet $wallet, string $body): void
    {
        app(ConsumerWalletPushNotificationService::class)->notifyGeneric(
            $wallet,
            'Savings reminder',
            $body,
            ['type' => 'savings_reminder', 'screen' => 'saving'],
        );
    }

    private function moneyLabel(float $amount, WhatsappWallet $wallet): string
    {
        $currency = app(\App\Services\Whatsapp\WhatsappWalletCountryResolver::class)
            ->currencyForPhoneE164((string) $wallet->phone_e164);

        return \App\Services\Whatsapp\WhatsappWalletMoneyFormatter::format($amount, $currency);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSettings(WalletSavingsSetting $settings): array
    {
        return [
            'spend_to_save_enabled' => (bool) $settings->spend_to_save_enabled,
            'spend_to_save_percent' => (float) $settings->spend_to_save_percent,
            'reminder_enabled' => (bool) $settings->reminder_enabled,
            'reminder_frequency' => (string) $settings->reminder_frequency,
            'reminder_weekday' => $settings->reminder_weekday,
            'reminder_hour_local' => $settings->reminder_hour_local,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatGoal(WalletSavingsGoal $goal): array
    {
        $target = (float) $goal->target_amount;
        $saved = (float) $goal->saved_amount;

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => $target,
            'saved_amount' => $saved,
            'progress_percent' => $target > 0 ? min(100, round(($saved / $target) * 100, 1)) : 0,
            'status' => $goal->status,
            'created_at' => $goal->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLock(WalletSavingsLock $lock): array
    {
        $isFlexible = $lock->lock_type === WalletSavingsLock::LOCK_TYPE_FLEXIBLE;

        return [
            'id' => $lock->id,
            'amount' => (float) $lock->amount,
            'source' => $lock->source,
            'lock_type' => $lock->lock_type ?? WalletSavingsLock::LOCK_TYPE_LOCKED,
            'ledger_scope' => ConsumerWalletTransactionScope::normalize((string) ($lock->ledger_scope ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL)),
            'goal_id' => $lock->wallet_savings_goal_id,
            'interest_rate_percent' => (float) $lock->interest_rate_percent,
            'interest_amount' => $lock->interest_amount !== null ? (float) $lock->interest_amount : null,
            'locked_at' => $lock->locked_at?->toIso8601String(),
            'matures_at' => $lock->matures_at?->toIso8601String(),
            'matured_at' => $lock->matured_at?->toIso8601String(),
            'status' => $lock->status,
            'can_withdraw' => $isFlexible && $lock->status === WalletSavingsLock::STATUS_ACTIVE && (float) $lock->amount > 0,
        ];
    }
}
