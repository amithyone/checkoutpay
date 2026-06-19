<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\WalletSavingsGoal;
use App\Models\WalletSavingsLock;
use App\Models\WalletSavingsSetting;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ConsumerWalletSavingsService
{
    private const SAVINGS_TZ = 'Africa/Lagos';

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

    public function maxStrictSavePercent(): float
    {
        return max(0.0, (float) Setting::get('savings_max_strict_save_percent', 25.0));
    }

    public function defaultStrictSavePercent(): float
    {
        return max(0.0, (float) Setting::get('savings_default_strict_save_percent', 5.0));
    }

    public function flexibleCompletionBonusPercent(): float
    {
        return max(0.0, (float) Setting::get('savings_flexible_completion_bonus_percent', 2.0));
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
            'strict_save_enabled' => false,
            'strict_save_percent' => $this->defaultStrictSavePercent(),
            'strict_ledger_scope' => WalletSavingsGoal::LEDGER_PERSONAL,
            'strict_collection_mode' => WalletSavingsSetting::COLLECTION_PER_INCOMING,
            'strict_balance_threshold' => null,
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
            'max_strict_save_percent' => $this->maxStrictSavePercent(),
            'remaining_strict_percent' => $this->remainingStrictSavePercent($wallet),
            'default_spend_to_save_percent' => $this->defaultSpendToSavePercent(),
            'default_strict_save_percent' => $this->defaultStrictSavePercent(),
            'flexible_completion_bonus_percent' => $this->flexibleCompletionBonusPercent(),
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

        $maxStrict = $this->maxStrictSavePercent();
        if (array_key_exists('strict_save_enabled', $payload)) {
            $settings->strict_save_enabled = (bool) $payload['strict_save_enabled'];
        }
        if (array_key_exists('strict_save_percent', $payload)) {
            $percent = round((float) $payload['strict_save_percent'], 2);
            if ($percent < 0 || $percent > $maxStrict + 0.0001) {
                return ['ok' => false, 'message' => 'Strict save percentage must be between 0 and '.$maxStrict.'.'];
            }
            $settings->strict_save_percent = $percent;
        }
        if (array_key_exists('strict_ledger_scope', $payload)) {
            $scope = (string) $payload['strict_ledger_scope'];
            if (! in_array($scope, [
                WalletSavingsGoal::LEDGER_PERSONAL,
                WalletSavingsGoal::LEDGER_BUSINESS,
                WalletSavingsGoal::LEDGER_BOTH,
            ], true)) {
                return ['ok' => false, 'message' => 'Invalid strict ledger scope.'];
            }
            $settings->strict_ledger_scope = $scope;
        }
        if (array_key_exists('strict_collection_mode', $payload)) {
            $mode = (string) $payload['strict_collection_mode'];
            if (! in_array($mode, [
                WalletSavingsSetting::COLLECTION_PER_INCOMING,
                WalletSavingsSetting::COLLECTION_BALANCE_THRESHOLD,
            ], true)) {
                return ['ok' => false, 'message' => 'Invalid strict collection mode.'];
            }
            $settings->strict_collection_mode = $mode;
        }
        if (array_key_exists('strict_balance_threshold', $payload)) {
            $threshold = $payload['strict_balance_threshold'];
            $settings->strict_balance_threshold = $threshold === null ? null : max(0, round((float) $threshold, 2));
        }

        $settings->save();

        return ['ok' => true, 'settings' => $this->formatSettings($settings->fresh())];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message?: string, goal?: array<string, mixed>}
     */
    public function createGoal(WhatsappWallet $wallet, array $payload): array
    {
        if (! $this->isProductEnabled()) {
            return ['ok' => false, 'message' => 'Savings is not available right now.'];
        }

        $parsed = $this->parseGoalPayload($payload);
        if (! ($parsed['ok'] ?? false)) {
            return $parsed;
        }

        /** @var array<string, mixed> $data */
        $data = $parsed['data'];

        if ($data['save_type'] === WalletSavingsGoal::SAVE_TYPE_STRICT && ($data['auto_save_enabled'] ?? false)) {
            $cap = $this->validateStrictPercentCap($wallet, (float) ($data['auto_save_percent'] ?? 0));
            if (! ($cap['ok'] ?? false)) {
                return $cap;
            }
        }

        $data['whatsapp_wallet_id'] = $wallet->id;
        $data['saved_amount'] = 0;
        $data['status'] = WalletSavingsGoal::STATUS_ACTIVE;
        $data['completion_bonus_percent'] = $data['save_type'] === WalletSavingsGoal::SAVE_TYPE_FLEXIBLE
            ? $this->flexibleCompletionBonusPercent()
            : null;

        if ($data['save_type'] === WalletSavingsGoal::SAVE_TYPE_FLEXIBLE && $data['target_date'] !== null) {
            $data['soft_lock_until'] = Carbon::parse($data['target_date'], self::SAVINGS_TZ)->endOfDay();
        }

        $goal = WalletSavingsGoal::query()->create($data);

        return ['ok' => true, 'goal' => $this->formatGoal($goal)];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function previewGoalPlan(array $input): array
    {
        $targetAmount = round((float) ($input['target_amount'] ?? 0), 2);
        $savedAmount = round((float) ($input['saved_amount'] ?? 0), 2);
        $saveType = (string) ($input['save_type'] ?? WalletSavingsGoal::SAVE_TYPE_FLEXIBLE);
        $targetDate = $this->resolveTargetDate($input);
        $pace = $this->computeGoalPace(
            $targetAmount,
            $savedAmount,
            $targetDate,
            isset($input['created_at']) ? Carbon::parse($input['created_at'], self::SAVINGS_TZ) : $this->savingsNow(),
        );

        return [
            'save_type' => $saveType,
            'target_amount' => $targetAmount,
            'saved_amount' => $savedAmount,
            'target_date' => $targetDate?->toDateString(),
            'duration_days' => $pace['duration_days'],
            'pace' => $pace,
            'projected_maturity_at' => $saveType === WalletSavingsGoal::SAVE_TYPE_STRICT
                ? $targetDate?->copy()->endOfDay()->toIso8601String()
                : null,
            'flexible_completion_bonus_percent' => $saveType === WalletSavingsGoal::SAVE_TYPE_FLEXIBLE
                ? $this->flexibleCompletionBonusPercent()
                : null,
            'strict_interest_rate_percent' => $saveType === WalletSavingsGoal::SAVE_TYPE_STRICT
                ? $this->interestRatePercent()
                : null,
        ];
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
        if (array_key_exists('save_type', $payload)) {
            $saveType = (string) $payload['save_type'];
            if (! in_array($saveType, [WalletSavingsGoal::SAVE_TYPE_FLEXIBLE, WalletSavingsGoal::SAVE_TYPE_STRICT], true)) {
                return ['ok' => false, 'message' => 'Invalid save type.'];
            }
            $goal->save_type = $saveType;
        }
        if (array_key_exists('target_date', $payload) || array_key_exists('duration_days', $payload)) {
            $targetDate = $this->resolveTargetDate(array_merge($goal->toArray(), $payload));
            $goal->target_date = $targetDate;
            $goal->duration_days = isset($payload['duration_days']) ? max(1, (int) $payload['duration_days']) : $goal->duration_days;
            if ($goal->isFlexible() && $targetDate !== null) {
                $goal->soft_lock_until = $targetDate->copy()->timezone(self::SAVINGS_TZ)->endOfDay();
            }
        }
        if (array_key_exists('collection_mode', $payload)) {
            $mode = (string) $payload['collection_mode'];
            if (! in_array($mode, [
                WalletSavingsGoal::COLLECTION_MANUAL,
                WalletSavingsGoal::COLLECTION_PER_INCOMING,
                WalletSavingsGoal::COLLECTION_BALANCE_THRESHOLD,
            ], true)) {
                return ['ok' => false, 'message' => 'Invalid collection mode.'];
            }
            $goal->collection_mode = $mode;
        }
        if (array_key_exists('auto_save_percent', $payload)) {
            $percent = round((float) $payload['auto_save_percent'], 2);
            $maxStrict = $this->maxStrictSavePercent();
            if ($percent < 0 || $percent > $maxStrict + 0.0001) {
                return ['ok' => false, 'message' => 'Auto-save percentage must be between 0 and '.$maxStrict.'.'];
            }
            $goal->auto_save_percent = $percent;
        }
        if (array_key_exists('balance_threshold', $payload)) {
            $threshold = $payload['balance_threshold'];
            $goal->balance_threshold = $threshold === null ? null : max(0, round((float) $threshold, 2));
        }
        if (array_key_exists('ledger_scope', $payload)) {
            $scope = (string) $payload['ledger_scope'];
            if (! in_array($scope, [
                WalletSavingsGoal::LEDGER_PERSONAL,
                WalletSavingsGoal::LEDGER_BUSINESS,
                WalletSavingsGoal::LEDGER_BOTH,
            ], true)) {
                return ['ok' => false, 'message' => 'Invalid ledger scope.'];
            }
            $goal->ledger_scope = $scope;
        }
        if (array_key_exists('auto_save_enabled', $payload)) {
            $goal->auto_save_enabled = (bool) $payload['auto_save_enabled'];
        }

        $isStrictActive = $goal->save_type === WalletSavingsGoal::SAVE_TYPE_STRICT
            && $goal->status === WalletSavingsGoal::STATUS_ACTIVE
            && $goal->auto_save_enabled;
        if ($isStrictActive) {
            $cap = $this->validateStrictPercentCap(
                $wallet,
                (float) ($goal->auto_save_percent ?? 0),
                (int) $goal->id,
            );
            if (! ($cap['ok'] ?? false)) {
                return $cap;
            }
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
            $exists = WalletSavingsLock::query()
                ->where('source_transaction_id', $sourceTransactionId)
                ->when($goalId !== null, fn ($q) => $q->where('wallet_savings_goal_id', $goalId))
                ->when($goalId === null, fn ($q) => $q->whereNull('wallet_savings_goal_id'))
                ->exists();
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

                $now = $this->savingsNow();
                $rate = $isFlexible ? 0.0 : $this->interestRatePercent();
                $maturesAt = $isFlexible ? null : $this->resolveMaturityDate($goal, $now);

                if ($isFlexible) {
                    $w->flexible_savings_balance = round((float) $w->flexible_savings_balance + $amount, 2);
                } else {
                    $w->savings_balance = round((float) $w->savings_balance + $amount, 2);
                }
                $w->save();

                $lockDaysMeta = null;
                if (! $isFlexible && $maturesAt !== null) {
                    $lockDaysMeta = max(1, $now->diffInDays($maturesAt));
                }

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
                        'lock_days' => $lockDaysMeta,
                        'interest_rate_percent' => $rate,
                        'matures_at' => $maturesAt?->toIso8601String(),
                        'goal_id' => $goal?->id,
                    ],
                ]);

                $lockMeta = ['wallet_transaction_id' => $txn->id];
                if ($isFlexible && $goal !== null && $goal->target_date !== null) {
                    $lockMeta['soft_lock_until'] = $goal->soft_lock_until?->toIso8601String();
                    $lockMeta['completion_bonus_percent'] = (float) ($goal->completion_bonus_percent ?? 0);
                }

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
                    'meta' => $lockMeta,
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
                $forfeitedBonus = false;
                foreach ($locks as $lock) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min((float) $lock->amount, $remaining);
                    $scope = ConsumerWalletTransactionScope::normalize((string) $lock->ledger_scope);
                    $creditsByScope[$scope] = ($creditsByScope[$scope] ?? 0) + $take;

                    $lockMeta = is_array($lock->meta) ? $lock->meta : [];
                    $softUntil = isset($lockMeta['soft_lock_until']) ? Carbon::parse($lockMeta['soft_lock_until']) : null;
                    if ($softUntil !== null && $softUntil->isFuture()) {
                        $lockMeta['forfeited_bonus'] = true;
                        $forfeitedBonus = true;
                    }

                    $newLockAmount = round((float) $lock->amount - $take, 2);
                    if ($newLockAmount <= 0) {
                        $lock->status = WalletSavingsLock::STATUS_WITHDRAWN;
                        $lock->amount = 0;
                    } else {
                        $lock->amount = $newLockAmount;
                    }
                    $lock->meta = $lockMeta;
                    $lock->save();

                    if ($forfeitedBonus && $lock->wallet_savings_goal_id) {
                        WalletSavingsGoal::query()
                            ->where('id', $lock->wallet_savings_goal_id)
                            ->where('completion_bonus_paid', false)
                            ->update(['completion_bonus_paid' => true]);
                    }

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
                    'forfeited_bonus' => $forfeitedBonus,
                ];
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $message = 'Flexible savings withdrawn to your wallet.';
        if ($result['forfeited_bonus'] ?? false) {
            $message = 'Withdrawn early — completion bonus forfeited.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'amount' => $result['amount'],
            'ledger_scope' => $result['ledger_scope'],
            'forfeited_bonus' => (bool) ($result['forfeited_bonus'] ?? false),
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

    public function handleIncomingCredit(
        WhatsappWallet $wallet,
        float $creditAmount,
        int $sourceTransactionId,
        string $sourceType,
        string $ledgerScope,
        float $balanceBefore,
        float $balanceAfter,
    ): void {
        if ($creditAmount <= 0) {
            return;
        }

        if (in_array($sourceType, ['savings_maturity', 'savings_withdraw', 'savings_lock'], true)) {
            return;
        }

        $sourceTxn = WhatsappWalletTransaction::query()->find($sourceTransactionId);
        if ($sourceTxn !== null && $sourceTxn->type === WhatsappWalletTransaction::TYPE_SAVINGS_MATURITY) {
            return;
        }

        $this->applySaveOnIncoming($wallet, $creditAmount, $sourceTransactionId, $sourceType, $ledgerScope);
        $this->applyBalanceThresholdSave($wallet, $creditAmount, $sourceTransactionId, $ledgerScope, $balanceAfter);
    }

    public function applySaveOnIncoming(
        WhatsappWallet $wallet,
        float $creditAmount,
        int $sourceTransactionId,
        string $sourceType,
        string $ledgerScope,
    ): void {
        if (! $this->isProductEnabled() || $creditAmount <= 0) {
            return;
        }

        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);
        $goals = WalletSavingsGoal::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', WalletSavingsGoal::STATUS_ACTIVE)
            ->where('save_type', WalletSavingsGoal::SAVE_TYPE_STRICT)
            ->where('auto_save_enabled', true)
            ->where('collection_mode', WalletSavingsGoal::COLLECTION_PER_INCOMING)
            ->get();

        foreach ($goals as $goal) {
            if (! $this->goalLedgerScopeMatches($goal, $ledgerScope)) {
                continue;
            }

            $percent = (float) ($goal->auto_save_percent ?? 0);
            if ($percent <= 0) {
                continue;
            }

            $saveAmount = round($creditAmount * ($percent / 100), 2);
            if ($saveAmount < $this->minDepositAmount()) {
                continue;
            }

            $fresh = $wallet->fresh();
            $available = $ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS
                ? $this->businessLedger->resolvedBalance($fresh)
                : (float) $fresh->balance;
            if ($available + 0.0001 < $saveAmount) {
                continue;
            }

            $result = $this->lockDeposit(
                $fresh,
                $saveAmount,
                WalletSavingsLock::SOURCE_INCOMING,
                (int) $goal->id,
                $sourceTransactionId,
                WalletSavingsLock::LOCK_TYPE_LOCKED,
                $ledgerScope,
            );

            if (! ($result['ok'] ?? false) && ($result['message'] ?? '') !== 'Already saved for this transaction.') {
                Log::info('consumer_savings.incoming_skipped', [
                    'wallet_id' => $wallet->id,
                    'goal_id' => $goal->id,
                    'source_transaction_id' => $sourceTransactionId,
                    'source_type' => $sourceType,
                    'message' => $result['message'] ?? 'unknown',
                ]);
            }
        }
    }

    public function applyBalanceThresholdSave(
        WhatsappWallet $wallet,
        float $creditAmount,
        int $sourceTransactionId,
        string $ledgerScope,
        float $balanceAfter,
    ): void {
        if (! $this->isProductEnabled() || $creditAmount <= 0) {
            return;
        }

        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);
        $goals = WalletSavingsGoal::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', WalletSavingsGoal::STATUS_ACTIVE)
            ->where('save_type', WalletSavingsGoal::SAVE_TYPE_STRICT)
            ->where('auto_save_enabled', true)
            ->where('collection_mode', WalletSavingsGoal::COLLECTION_BALANCE_THRESHOLD)
            ->whereNotNull('balance_threshold')
            ->get();

        foreach ($goals as $goal) {
            if (! $this->goalLedgerScopeMatches($goal, $ledgerScope)) {
                continue;
            }

            $threshold = (float) $goal->balance_threshold;
            if ($threshold <= 0 || $balanceAfter + 0.0001 < $threshold) {
                continue;
            }

            $percent = (float) ($goal->auto_save_percent ?? 0);
            if ($percent <= 0) {
                continue;
            }

            $saveAmount = round($creditAmount * ($percent / 100), 2);
            if ($saveAmount < $this->minDepositAmount()) {
                continue;
            }

            $fresh = $wallet->fresh();
            $available = $ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS
                ? $this->businessLedger->resolvedBalance($fresh)
                : (float) $fresh->balance;
            if ($available + 0.0001 < $saveAmount) {
                continue;
            }

            $result = $this->lockDeposit(
                $fresh,
                $saveAmount,
                WalletSavingsLock::SOURCE_BALANCE_THRESHOLD,
                (int) $goal->id,
                $sourceTransactionId,
                WalletSavingsLock::LOCK_TYPE_LOCKED,
                $ledgerScope,
            );

            if (! ($result['ok'] ?? false) && ($result['message'] ?? '') !== 'Already saved for this transaction.') {
                Log::info('consumer_savings.threshold_skipped', [
                    'wallet_id' => $wallet->id,
                    'goal_id' => $goal->id,
                    'source_transaction_id' => $sourceTransactionId,
                    'message' => $result['message'] ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * @return array{processed: int, failed: int}
     */
    public function processDueFlexibleBonuses(): array
    {
        $processed = 0;
        $failed = 0;

        WalletSavingsGoal::query()
            ->where('save_type', WalletSavingsGoal::SAVE_TYPE_FLEXIBLE)
            ->where('status', WalletSavingsGoal::STATUS_ACTIVE)
            ->where('completion_bonus_paid', false)
            ->whereNotNull('soft_lock_until')
            ->where('soft_lock_until', '<=', now())
            ->where('completion_bonus_percent', '>', 0)
            ->chunkById(50, function ($goals) use (&$processed, &$failed) {
                foreach ($goals as $goal) {
                    try {
                        if ($this->payFlexibleCompletionBonus($goal)) {
                            $processed++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('consumer_savings.flexible_bonus_failed', [
                            'goal_id' => $goal->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return ['processed' => $processed, 'failed' => $failed];
    }

    public function payFlexibleCompletionBonus(WalletSavingsGoal $goal): bool
    {
        return (bool) DB::transaction(function () use ($goal) {
            $row = WalletSavingsGoal::query()->lockForUpdate()->find($goal->id);
            if (! $row || $row->completion_bonus_paid || ! $row->isFlexible()) {
                return false;
            }
            if ($row->soft_lock_until === null || $row->soft_lock_until->isFuture()) {
                return false;
            }

            $flexibleTotal = (float) WalletSavingsLock::query()
                ->where('wallet_savings_goal_id', $row->id)
                ->where('status', WalletSavingsLock::STATUS_ACTIVE)
                ->where('lock_type', WalletSavingsLock::LOCK_TYPE_FLEXIBLE)
                ->sum('amount');

            if ($flexibleTotal <= 0) {
                $row->completion_bonus_paid = true;
                $row->save();

                return false;
            }

            $bonus = round($flexibleTotal * ((float) $row->completion_bonus_percent / 100), 2);
            if ($bonus <= 0) {
                $row->completion_bonus_paid = true;
                $row->save();

                return false;
            }

            $w = WhatsappWallet::query()->lockForUpdate()->find($row->whatsapp_wallet_id);
            if (! $w) {
                return false;
            }

            $ledgerScope = ConsumerWalletTransactionScope::normalize((string) ($row->ledger_scope ?? WalletSavingsGoal::LEDGER_PERSONAL));
            if ($ledgerScope === WalletSavingsGoal::LEDGER_BOTH) {
                $ledgerScope = ConsumerWalletTransactionScope::SCOPE_PERSONAL;
            }

            $w->flexible_savings_balance = round((float) $w->flexible_savings_balance + $bonus, 2);
            $w->save();

            WalletSavingsLock::query()->create([
                'whatsapp_wallet_id' => $w->id,
                'wallet_savings_goal_id' => $row->id,
                'source' => WalletSavingsLock::SOURCE_GOAL,
                'lock_type' => WalletSavingsLock::LOCK_TYPE_FLEXIBLE,
                'ledger_scope' => $ledgerScope,
                'amount' => $bonus,
                'interest_rate_percent' => 0,
                'locked_at' => now(),
                'matures_at' => null,
                'status' => WalletSavingsLock::STATUS_ACTIVE,
                'meta' => ['completion_bonus' => true],
            ]);

            $row->saved_amount = round((float) $row->saved_amount + $bonus, 2);
            $row->completion_bonus_paid = true;
            $row->save();

            return true;
        });
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
            'strict_save_enabled' => (bool) ($settings->strict_save_enabled ?? false),
            'strict_save_percent' => (float) ($settings->strict_save_percent ?? $this->defaultStrictSavePercent()),
            'strict_ledger_scope' => (string) ($settings->strict_ledger_scope ?? WalletSavingsGoal::LEDGER_PERSONAL),
            'strict_collection_mode' => (string) ($settings->strict_collection_mode ?? WalletSavingsSetting::COLLECTION_PER_INCOMING),
            'strict_balance_threshold' => $settings->strict_balance_threshold !== null
                ? (float) $settings->strict_balance_threshold
                : null,
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
        $pace = $this->computeGoalPace($target, $saved, $goal->target_date, $goal->created_at);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => $target,
            'saved_amount' => $saved,
            'progress_percent' => $target > 0 ? min(100, round(($saved / $target) * 100, 1)) : 0,
            'status' => $goal->status,
            'save_type' => $goal->save_type ?? WalletSavingsGoal::SAVE_TYPE_FLEXIBLE,
            'target_date' => $goal->target_date?->toDateString(),
            'duration_days' => $goal->duration_days,
            'collection_mode' => $goal->collection_mode ?? WalletSavingsGoal::COLLECTION_MANUAL,
            'auto_save_percent' => $goal->auto_save_percent !== null ? (float) $goal->auto_save_percent : null,
            'balance_threshold' => $goal->balance_threshold !== null ? (float) $goal->balance_threshold : null,
            'ledger_scope' => $goal->ledger_scope ?? WalletSavingsGoal::LEDGER_PERSONAL,
            'auto_save_enabled' => (bool) ($goal->auto_save_enabled ?? false),
            'soft_lock_until' => $goal->soft_lock_until?->toIso8601String(),
            'completion_bonus_percent' => $goal->completion_bonus_percent !== null
                ? (float) $goal->completion_bonus_percent
                : null,
            'completion_bonus_paid' => (bool) ($goal->completion_bonus_paid ?? false),
            'pace' => $pace,
            'created_at' => $goal->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    private function parseGoalPayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'message' => 'Enter a goal name (max 120 characters).'];
        }

        $targetAmount = round((float) ($payload['target_amount'] ?? 0), 2);
        if ($targetAmount < 100) {
            return ['ok' => false, 'message' => 'Target amount must be at least ₦100.'];
        }

        $saveType = (string) ($payload['save_type'] ?? WalletSavingsGoal::SAVE_TYPE_FLEXIBLE);
        if (! in_array($saveType, [WalletSavingsGoal::SAVE_TYPE_FLEXIBLE, WalletSavingsGoal::SAVE_TYPE_STRICT], true)) {
            return ['ok' => false, 'message' => 'Invalid save type.'];
        }

        $targetDate = $this->resolveTargetDate($payload);
        if ($targetDate === null) {
            return ['ok' => false, 'message' => 'Provide a target date or duration in days.'];
        }

        $collectionMode = (string) ($payload['collection_mode'] ?? WalletSavingsGoal::COLLECTION_MANUAL);
        if ($saveType === WalletSavingsGoal::SAVE_TYPE_STRICT && $collectionMode === WalletSavingsGoal::COLLECTION_MANUAL) {
            $collectionMode = WalletSavingsSetting::COLLECTION_PER_INCOMING;
        }
        if (! in_array($collectionMode, [
            WalletSavingsGoal::COLLECTION_MANUAL,
            WalletSavingsGoal::COLLECTION_PER_INCOMING,
            WalletSavingsGoal::COLLECTION_BALANCE_THRESHOLD,
        ], true)) {
            return ['ok' => false, 'message' => 'Invalid collection mode.'];
        }

        $autoSaveEnabled = (bool) ($payload['auto_save_enabled'] ?? ($saveType === WalletSavingsGoal::SAVE_TYPE_STRICT));
        $autoSavePercent = array_key_exists('auto_save_percent', $payload)
            ? round((float) $payload['auto_save_percent'], 2)
            : $this->defaultStrictSavePercent();

        if ($saveType === WalletSavingsGoal::SAVE_TYPE_STRICT) {
            $maxStrict = $this->maxStrictSavePercent();
            if ($autoSavePercent < 0 || $autoSavePercent > $maxStrict + 0.0001) {
                return ['ok' => false, 'message' => 'Auto-save percentage must be between 0 and '.$maxStrict.'.'];
            }
        } else {
            $autoSavePercent = null;
            $autoSaveEnabled = false;
            $collectionMode = WalletSavingsGoal::COLLECTION_MANUAL;
        }

        $ledgerScope = (string) ($payload['ledger_scope'] ?? WalletSavingsGoal::LEDGER_PERSONAL);
        if (! in_array($ledgerScope, [
            WalletSavingsGoal::LEDGER_PERSONAL,
            WalletSavingsGoal::LEDGER_BUSINESS,
            WalletSavingsGoal::LEDGER_BOTH,
        ], true)) {
            return ['ok' => false, 'message' => 'Invalid ledger scope.'];
        }

        $balanceThreshold = null;
        if ($collectionMode === WalletSavingsGoal::COLLECTION_BALANCE_THRESHOLD) {
            $balanceThreshold = round((float) ($payload['balance_threshold'] ?? 0), 2);
            if ($balanceThreshold <= 0) {
                return ['ok' => false, 'message' => 'Balance threshold is required for threshold auto-save.'];
            }
        }

        $durationDays = isset($payload['duration_days']) ? max(1, (int) $payload['duration_days']) : null;
        if ($durationDays === null && $targetDate !== null) {
            $durationDays = max(1, $this->savingsNow()->startOfDay()->diffInDays($targetDate->copy()->startOfDay()));
        }

        return [
            'ok' => true,
            'data' => [
                'name' => $name,
                'target_amount' => $targetAmount,
                'save_type' => $saveType,
                'target_date' => $targetDate,
                'duration_days' => $durationDays,
                'collection_mode' => $collectionMode,
                'auto_save_percent' => $autoSavePercent,
                'balance_threshold' => $balanceThreshold,
                'ledger_scope' => $ledgerScope,
                'auto_save_enabled' => $autoSaveEnabled,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTargetDate(array $payload): ?Carbon
    {
        if (! empty($payload['target_date'])) {
            try {
                return Carbon::parse((string) $payload['target_date'], self::SAVINGS_TZ)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        if (! empty($payload['duration_days'])) {
            return $this->savingsNow()->copy()->startOfDay()->addDays(max(1, (int) $payload['duration_days']));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeGoalPace(
        float $targetAmount,
        float $savedAmount,
        $targetDate,
        $createdAt = null,
    ): array {
        $remaining = max(0, round($targetAmount - $savedAmount, 2));
        $targetEnd = null;
        if ($targetDate instanceof Carbon) {
            $targetEnd = $targetDate->copy()->timezone(self::SAVINGS_TZ)->endOfDay();
        } elseif ($targetDate) {
            $targetEnd = Carbon::parse($targetDate, self::SAVINGS_TZ)->endOfDay();
        }

        $daysLeft = $targetEnd ? max(1, $this->savingsNow()->startOfDay()->diffInDays($targetEnd, false)) : null;

        $daily = $daysLeft ? round($remaining / $daysLeft, 2) : null;
        $weekly = $daily !== null ? round($daily * 7, 2) : null;
        $monthly = $daily !== null ? round($daily * 30, 2) : null;

        $expectedByNow = 0.0;
        $paceStatus = 'on_track';
        if ($targetEnd && $createdAt) {
            $start = $createdAt instanceof Carbon
                ? $createdAt->copy()->timezone(self::SAVINGS_TZ)->startOfDay()
                : Carbon::parse($createdAt, self::SAVINGS_TZ)->startOfDay();
            $targetStart = $targetEnd->copy()->startOfDay();
            $totalDays = max(1, $start->diffInDays($targetStart) + 1);
            $elapsedDays = min($totalDays, max(0, $start->diffInDays($this->savingsNow()->startOfDay())));
            $expectedByNow = round($targetAmount * ($elapsedDays / $totalDays), 2);
            $paceStatus = $savedAmount + 0.0001 >= $expectedByNow ? 'on_track' : 'behind';
            if ($savedAmount >= $targetAmount) {
                $paceStatus = 'completed';
            }
        }

        return [
            'remaining' => $remaining,
            'days_left' => $daysLeft,
            'duration_days' => $targetEnd && $createdAt
                ? max(1, ($createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt, self::SAVINGS_TZ))
                    ->timezone(self::SAVINGS_TZ)->startOfDay()->diffInDays($targetEnd->copy()->startOfDay()) + 1)
                : ($daysLeft ?? null),
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
            'expected_saved_by_now' => $expectedByNow,
            'pace_status' => $paceStatus,
        ];
    }

    private function resolveMaturityDate(?WalletSavingsGoal $goal, Carbon $now): Carbon
    {
        if ($goal !== null && $goal->target_date !== null) {
            return Carbon::parse($goal->target_date, self::SAVINGS_TZ)->endOfDay();
        }

        if ($goal !== null && $goal->duration_days !== null) {
            return $now->copy()->addDays(max(1, (int) $goal->duration_days))->endOfDay();
        }

        return $now->copy()->addDays($this->lockDays())->endOfDay();
    }

    public function sumActiveStrictSavePercent(WhatsappWallet $wallet, ?int $excludeGoalId = null): float
    {
        return (float) WalletSavingsGoal::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('status', WalletSavingsGoal::STATUS_ACTIVE)
            ->where('save_type', WalletSavingsGoal::SAVE_TYPE_STRICT)
            ->where('auto_save_enabled', true)
            ->when($excludeGoalId !== null, fn ($q) => $q->where('id', '!=', $excludeGoalId))
            ->sum('auto_save_percent');
    }

    public function remainingStrictSavePercent(WhatsappWallet $wallet, ?int $excludeGoalId = null): float
    {
        return max(0.0, round($this->maxStrictSavePercent() - $this->sumActiveStrictSavePercent($wallet, $excludeGoalId), 2));
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function validateStrictPercentCap(WhatsappWallet $wallet, float $newPercent, ?int $excludeGoalId = null): array
    {
        if ($newPercent <= 0) {
            return ['ok' => true];
        }

        $total = round($this->sumActiveStrictSavePercent($wallet, $excludeGoalId) + $newPercent, 2);
        $max = $this->maxStrictSavePercent();
        if ($total > $max + 0.0001) {
            return [
                'ok' => false,
                'message' => sprintf('Total strict auto-save would be %.1f%%; max is %.1f%%.', $total, $max),
            ];
        }

        return ['ok' => true];
    }

    private function savingsNow(): Carbon
    {
        return now(self::SAVINGS_TZ);
    }

    private function goalLedgerScopeMatches(WalletSavingsGoal $goal, string $ledgerScope): bool
    {
        $goalScope = (string) ($goal->ledger_scope ?? WalletSavingsGoal::LEDGER_PERSONAL);

        return $goalScope === WalletSavingsGoal::LEDGER_BOTH || $goalScope === $ledgerScope;
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
