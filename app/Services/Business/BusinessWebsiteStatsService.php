<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class BusinessWebsiteStatsService
{
    public const UNATTRIBUTED_LABEL = 'Other (bank transfer, invoice, etc.)';

    public function __construct(
        private PaymentWebsiteAttributionService $attribution,
    ) {}

    public function approvedAtExpression(): Expression
    {
        return DB::raw('COALESCE(matched_at, created_at)');
    }

    /**
     * Nigeria calendar windows for dashboard revenue cards (today / month / year to now).
     *
     * @return array{
     *     approved_at: Expression,
     *     today_start: \Carbon\Carbon,
     *     today_end: \Carbon\Carbon,
     *     month_start: \Carbon\Carbon,
     *     month_end: \Carbon\Carbon,
     *     year_start: \Carbon\Carbon,
     *     year_end: \Carbon\Carbon
     * }
     */
    public function nigeriaRevenueWindows(): array
    {
        $nigeriaTz = 'Africa/Lagos';
        $nigeriaNow = now($nigeriaTz);
        $nowUtc = $nigeriaNow->copy()->utc();

        return [
            'approved_at' => $this->approvedAtExpression(),
            'today_start' => $nigeriaNow->copy()->startOfDay()->utc(),
            'today_end' => $nowUtc,
            'month_start' => $nigeriaNow->copy()->startOfMonth()->utc(),
            'month_end' => $nowUtc,
            'year_start' => $nigeriaNow->copy()->startOfYear()->utc(),
            'year_end' => $nowUtc,
        ];
    }

    public function paymentsQueryForBusinessWebsite(int $businessId, ?int $websiteId): Builder
    {
        $query = Payment::query()->where('business_id', $businessId);

        if ($websiteId === null) {
            return $query->whereNull('business_website_id');
        }

        return $query->where('business_website_id', $websiteId);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboardRow(Builder $websitePaymentsQuery, array $windows): array
    {
        $approvedAtExpr = $windows['approved_at'];
        $revenueExpr = DB::raw('COALESCE(business_receives, amount)');

        $todayRevenue = (clone $websitePaymentsQuery)
            ->where('status', Payment::STATUS_APPROVED)
            ->whereBetween($approvedAtExpr, [$windows['today_start'], $windows['today_end']])
            ->sum($revenueExpr) ?? 0;

        $monthlyRevenue = (clone $websitePaymentsQuery)
            ->where('status', Payment::STATUS_APPROVED)
            ->whereBetween($approvedAtExpr, [$windows['month_start'], $windows['month_end']])
            ->sum($revenueExpr) ?? 0;

        $yearlyRevenue = (clone $websitePaymentsQuery)
            ->where('status', Payment::STATUS_APPROVED)
            ->whereBetween($approvedAtExpr, [$windows['year_start'], $windows['year_end']])
            ->sum($revenueExpr) ?? 0;

        $todayPayments = (clone $websitePaymentsQuery)
            ->where('status', Payment::STATUS_APPROVED)
            ->whereBetween($approvedAtExpr, [$windows['today_start'], $windows['today_end']])
            ->count();

        $monthlyPayments = (clone $websitePaymentsQuery)
            ->where('status', Payment::STATUS_APPROVED)
            ->whereBetween($approvedAtExpr, [$windows['month_start'], $windows['month_end']])
            ->count();

        return [
            'total_revenue' => (clone $websitePaymentsQuery)
                ->where('status', Payment::STATUS_APPROVED)
                ->sum($revenueExpr) ?? 0,
            'total_payments' => (clone $websitePaymentsQuery)->count(),
            'approved_payments' => (clone $websitePaymentsQuery)->where('status', Payment::STATUS_APPROVED)->count(),
            'pending_payments' => (clone $websitePaymentsQuery)->where('status', Payment::STATUS_PENDING)->count(),
            'today_revenue' => $todayRevenue,
            'today_payments' => $todayPayments,
            'monthly_revenue' => $monthlyRevenue,
            'yearly_revenue' => $yearlyRevenue,
            'monthly_payments' => $monthlyPayments,
        ];
    }

    /**
     * Per-website revenue rows for the business dashboard, including unattributed payments.
     *
     * @return list<array<string, mixed>>
     */
    public function buildDashboardBreakdown(Business $business): array
    {
        $windows = $this->nigeriaRevenueWindows();
        $rows = [];
        $business->load('websites');

        $inferredByWebsite = [];
        $strictlyUnattributed = collect();

        $unattributedPayments = Payment::query()
            ->where('business_id', $business->id)
            ->whereNull('business_website_id')
            ->get();

        foreach ($unattributedPayments as $payment) {
            $matched = $this->attribution->resolveWebsite($payment);
            if ($matched) {
                $inferredByWebsite[$matched->id][] = $payment;
            } else {
                $strictlyUnattributed->push($payment);
            }
        }

        foreach ($business->websites as $website) {
            $directQuery = $this->paymentsQueryForBusinessWebsite($business->id, $website->id);
            $directStats = $this->buildDashboardRow($directQuery, $windows);
            $inferredStats = $this->buildDashboardRowFromPayments(
                collect($inferredByWebsite[$website->id] ?? []),
                $windows
            );

            $rows[] = array_merge(
                $this->mergeStatRows($directStats, $inferredStats),
                [
                    'website' => $website,
                    'is_unattributed' => false,
                    'label' => null,
                ]
            );
        }

        if ($strictlyUnattributed->isNotEmpty()) {
            $rows[] = array_merge(
                $this->buildDashboardRowFromPayments($strictlyUnattributed, $windows),
                [
                    'website' => null,
                    'is_unattributed' => true,
                    'label' => self::UNATTRIBUTED_LABEL,
                ]
            );
        }

        usort($rows, fn (array $a, array $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Payment>  $payments
     * @return array<string, mixed>
     */
    private function buildDashboardRowFromPayments(\Illuminate\Support\Collection $payments, array $windows): array
    {
        $approvedAtFor = fn (Payment $payment) => $payment->matched_at ?? $payment->created_at;
        $revenueFor = fn (Payment $payment) => (float) ($payment->business_receives ?? $payment->amount);

        $approved = $payments->where('status', Payment::STATUS_APPROVED);

        $inWindow = function (\Illuminate\Support\Collection $collection, $start, $end) use ($approvedAtFor) {
            return $collection->filter(function (Payment $payment) use ($approvedAtFor, $start, $end) {
                $at = $approvedAtFor($payment);

                return $at !== null && $at->betweenIncluded($start, $end);
            });
        };

        $todayApproved = $inWindow($approved, $windows['today_start'], $windows['today_end']);
        $monthApproved = $inWindow($approved, $windows['month_start'], $windows['month_end']);
        $yearApproved = $inWindow($approved, $windows['year_start'], $windows['year_end']);

        return [
            'total_revenue' => $approved->sum($revenueFor),
            'total_payments' => $payments->count(),
            'approved_payments' => $approved->count(),
            'pending_payments' => $payments->where('status', Payment::STATUS_PENDING)->count(),
            'today_revenue' => $todayApproved->sum($revenueFor),
            'today_payments' => $todayApproved->count(),
            'monthly_revenue' => $monthApproved->sum($revenueFor),
            'yearly_revenue' => $yearApproved->sum($revenueFor),
            'monthly_payments' => $monthApproved->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $direct
     * @param  array<string, mixed>  $inferred
     * @return array<string, mixed>
     */
    private function mergeStatRows(array $direct, array $inferred): array
    {
        $merged = $direct;
        foreach ($inferred as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $merged[$key] = ($merged[$key] ?? 0) + $value;
        }

        return $merged;
    }
}
