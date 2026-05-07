<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\Setting;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OverdraftFundingService
{
    public const FUNDING_PLATFORM = 'platform';

    public const FUNDING_PEER_POOL = 'peer_pool';

    public const FUNDING_CAPITAL_RESERVE = 'capital_reserve';

    public const SETTING_KEY = 'overdraft_capital_reserve_email';

    public const DEFAULT_CAPITAL_RESERVE_EMAIL = 'admin@check-outpay.com';

    public function capitalReserveEmail(): string
    {
        return (string) Setting::get(self::SETTING_KEY, self::DEFAULT_CAPITAL_RESERVE_EMAIL);
    }

    public function fundingBusiness(string $source): ?Business
    {
        if ($source !== self::FUNDING_CAPITAL_RESERVE) {
            return null;
        }

        return Business::query()->where('email', $this->capitalReserveEmail())->first();
    }

    /**
     * Capacity available from the funding business for new overdraft.
     * For capital reserve: their current balance (cannot loan what they do not have).
     */
    public function availableCapacity(string $source): float
    {
        $funder = $this->fundingBusiness($source);
        if (! $funder) {
            return 0.0;
        }

        return max(0.0, (float) $funder->balance);
    }

    /**
     * Sync funding business balance with how much overdraft principal the borrower
     * currently uses, when funding source is capital_reserve.
     *
     * - Borrower goes more negative -> debit the funder by the new draw.
     * - Borrower recovers toward zero -> refund the funder by the recovered amount.
     */
    public function syncOnBalanceChange(Business $borrower, float $previousBalance, float $newBalance): void
    {
        if (! $borrower->hasOverdraftApproved()) {
            return;
        }
        if ($borrower->overdraft_funding_source !== self::FUNDING_CAPITAL_RESERVE) {
            return;
        }

        $limit = (float) $borrower->overdraft_limit;
        $priorDraw = $this->draw($previousBalance, $limit);
        $newDraw = $this->draw($newBalance, $limit);
        $delta = round($newDraw - $priorDraw, 2);

        if (abs($delta) < 0.01) {
            return;
        }

        $funder = $this->fundingBusiness(self::FUNDING_CAPITAL_RESERVE);
        if (! $funder) {
            Log::warning('Overdraft capital reserve funding business not found', [
                'email' => $this->capitalReserveEmail(),
                'borrower_id' => $borrower->id,
                'delta' => $delta,
            ]);

            return;
        }
        if ($funder->id === $borrower->id) {
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
                ? 'Overdraft draw funded by capital reserve: ₦'.number_format($delta, 2)
                : 'Overdraft repaid to capital reserve: ₦'.number_format(abs($delta), 2),
            'metadata' => [
                'borrower_business_id' => $borrower->id,
                'borrower_email' => $borrower->email,
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
