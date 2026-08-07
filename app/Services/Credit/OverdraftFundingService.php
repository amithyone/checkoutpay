<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\Setting;
use App\Models\TransactionLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central / master loan account backs overdraft:
 * - Borrower draws (balance goes more negative) → debit master
 * - Borrower repays (balance recovers) → credit master
 */
class OverdraftFundingService
{
    public const FUNDING_PLATFORM = 'platform';

    public const FUNDING_PEER_POOL = 'peer_pool';

    public const FUNDING_CAPITAL_RESERVE = 'capital_reserve';

    /** Any chosen master loan business account. */
    public const FUNDING_MASTER_LOAN = 'master_loan';

    public const SETTING_KEY = 'overdraft_capital_reserve_email';

    public const DEFAULT_CAPITAL_RESERVE_EMAIL = 'admin@check-outpay.com';

    public function capitalReserveEmail(): string
    {
        return (string) Setting::get(self::SETTING_KEY, self::DEFAULT_CAPITAL_RESERVE_EMAIL);
    }

    public function fundingBusiness(string $source): ?Business
    {
        if ($source === self::FUNDING_CAPITAL_RESERVE) {
            return Business::query()->where('email', $this->capitalReserveEmail())->first();
        }

        return null;
    }

    /**
     * Master account that funds this borrower's overdraft draws / receives repayments.
     */
    public function resolveFunder(Business $borrower): ?Business
    {
        $funderId = (int) ($borrower->overdraft_funder_business_id ?? 0);
        if ($funderId > 0) {
            $funder = Business::query()->find($funderId);
            if ($funder) {
                return $funder;
            }
        }

        $source = (string) ($borrower->overdraft_funding_source ?? '');
        if (in_array($source, [self::FUNDING_CAPITAL_RESERVE, self::FUNDING_MASTER_LOAN], true)) {
            return $this->fundingBusiness(self::FUNDING_CAPITAL_RESERVE);
        }

        return null;
    }

    public function isMasterBacked(Business $borrower): bool
    {
        return $this->resolveFunder($borrower) !== null;
    }

    /**
     * Capacity available on a master account (cannot back more than current balance).
     */
    public function availableCapacityForFunder(?Business $funder): float
    {
        if (! $funder) {
            return 0.0;
        }

        return max(0.0, round((float) $funder->balance, 2));
    }

    public function availableCapacity(string $source): float
    {
        return $this->availableCapacityForFunder($this->fundingBusiness($source));
    }

    /**
     * Businesses an admin can pick as the central loan / overdraft float account.
     *
     * @return Collection<int, Business>
     */
    public function masterLoanAccounts(): Collection
    {
        $reserveEmail = $this->capitalReserveEmail();

        return Business::query()
            ->where('is_active', true)
            ->where(function ($q) use ($reserveEmail) {
                $q->where('is_master_loan_account', true)
                    ->orWhere('email', $reserveEmail);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Sync funding business balance with how much overdraft principal the borrower
     * currently uses (master-backed overdraft only).
     *
     * - Borrower goes more negative → debit the master account
     * - Borrower recovers toward zero → credit the master account
     */
    public function syncOnBalanceChange(Business $borrower, float $previousBalance, float $newBalance): void
    {
        if (! $borrower->hasOverdraftApproved()) {
            return;
        }

        $funder = $this->resolveFunder($borrower);
        if (! $funder) {
            return;
        }
        if ($funder->id === $borrower->id) {
            return;
        }

        $limit = (float) $borrower->overdraft_limit;
        $priorDraw = $this->draw($previousBalance, $limit);
        $newDraw = $this->draw($newBalance, $limit);
        $delta = round($newDraw - $priorDraw, 2);

        if (abs($delta) < 0.01) {
            return;
        }

        DB::transaction(function () use ($funder, $borrower, $delta) {
            $locked = Business::query()->whereKey($funder->id)->lockForUpdate()->firstOrFail();

            if ($delta > 0) {
                $locked->decrement('balance', $delta);
            } else {
                $locked->increment('balance', abs($delta));
            }
        });

        TransactionLog::create([
            'transaction_id' => 'ODR-FUND-'.$borrower->id.'-'.now()->timestamp,
            'business_id' => $funder->id,
            'event_type' => $delta > 0
                ? TransactionLog::EVENT_OVERDRAFT_FUNDING_DEBIT
                : TransactionLog::EVENT_OVERDRAFT_FUNDING_CREDIT,
            'description' => $delta > 0
                ? 'Overdraft draw funded by master loan account: ₦'.number_format($delta, 2)
                : 'Overdraft repaid to master loan account: ₦'.number_format(abs($delta), 2),
            'metadata' => [
                'borrower_business_id' => $borrower->id,
                'borrower_email' => $borrower->email,
                'funder_business_id' => $funder->id,
                'delta' => $delta,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'overdraft_limit' => $limit,
            ],
        ]);
    }

    private function draw(float $balance, float $limit): float
    {
        if ($balance >= 0) {
            return 0.0;
        }

        return min($limit, abs($balance));
    }
}
