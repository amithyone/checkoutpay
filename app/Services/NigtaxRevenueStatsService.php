<?php

namespace App\Services;

use App\Models\MembershipSubscription;
use App\Models\NigtaxCertifiedOrder;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class NigtaxRevenueStatsService
{
    /**
     * Approved payment totals for NigTax PRO membership (slug from config).
     *
     * @return array{
     *     total_revenue: float,
     *     today_revenue: float,
     *     payment_count: int,
     *     today_payment_count: int,
     *     membership_configured: bool
     * }
     */
    public function proPlanStats(CarbonInterface $today): array
    {
        $membership = app(NigtaxProSubscriptionService::class)->findMembership();
        if (! $membership) {
            return [
                'total_revenue' => 0.0,
                'today_revenue' => 0.0,
                'payment_count' => 0,
                'today_payment_count' => 0,
                'membership_configured' => false,
            ];
        }

        $paymentIds = MembershipSubscription::query()
            ->where('membership_id', $membership->id)
            ->whereNotNull('payment_id')
            ->pluck('payment_id');

        if ($paymentIds->isEmpty()) {
            return [
                'total_revenue' => 0.0,
                'today_revenue' => 0.0,
                'payment_count' => 0,
                'today_payment_count' => 0,
                'membership_configured' => true,
            ];
        }

        $base = Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->whereIn('id', $paymentIds);

        return [
            'total_revenue' => (float) (clone $base)->sum(DB::raw('COALESCE(received_amount, amount)')),
            'today_revenue' => (float) (clone $base)->whereDate('created_at', $today)->sum(DB::raw('COALESCE(received_amount, amount)')),
            'payment_count' => (clone $base)->count(),
            'today_payment_count' => (clone $base)->whereDate('created_at', $today)->count(),
            'membership_configured' => true,
        ];
    }

    /**
     * Revenue from paid certified consultant report orders (signature / stamp flow).
     *
     * @return array{
     *     total_revenue: float,
     *     today_revenue: float,
     *     order_count: int,
     *     today_order_count: int
     * }
     */
    public function certifiedRevenueStats(CarbonInterface $today): array
    {
        $statuses = [
            NigtaxCertifiedOrder::STATUS_PAID,
            NigtaxCertifiedOrder::STATUS_SIGNED,
            NigtaxCertifiedOrder::STATUS_DELIVERED,
        ];

        $base = NigtaxCertifiedOrder::query()->whereIn('status', $statuses);

        return [
            'total_revenue' => (float) (clone $base)->sum(DB::raw('COALESCE(amount_paid, 0)')),
            'today_revenue' => (float) (clone $base)->whereDate('paid_at', $today)->sum(DB::raw('COALESCE(amount_paid, 0)')),
            'order_count' => (clone $base)->count(),
            'today_order_count' => (clone $base)->whereDate('paid_at', $today)->count(),
        ];
    }
}
