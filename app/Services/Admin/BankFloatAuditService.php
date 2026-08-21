<?php

namespace App\Services\Admin;

use App\Models\Business;
use App\Models\Renter;
use App\Models\WhatsappWallet;
use App\Services\MevonPay\MevonPayBalanceSnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer liability totals that should match money held in the bank (float).
 * Excludes balance_audit_exempt test businesses / wallets / renters.
 */
class BankFloatAuditService
{
    public function __construct(
        private MevonPayBalanceSnapshotService $mevonBalances,
    ) {}

    /**
     * @return array{
     *   business: array{total: float, exempt_total: float, count: int, exempt_count: int},
     *   wallet: array{total: float, exempt_total: float, count: int, exempt_count: int, personal: float, savings: float, business_standalone: float},
     *   rentals: array{total: float, exempt_total: float, count: int, exempt_count: int},
     *   site: array{total: float, exempt_total: float},
     *   exempt_businesses: list<array{id: int, name: string, email: string, balance: float}>,
     *   exempt_wallets: list<array{id: int, phone: string, name: ?string, liability: float}>
     * }
     */
    public function summarize(): array
    {
        $business = $this->businessTotals();
        $wallet = $this->walletTotals();
        $rentals = $this->rentalsTotals();

        return [
            'business' => $business,
            'wallet' => $wallet,
            'rentals' => $rentals,
            'site' => [
                'total' => round($business['total'] + $wallet['total'] + $rentals['total'], 2),
                'exempt_total' => round($business['exempt_total'] + $wallet['exempt_total'] + $rentals['exempt_total'], 2),
            ],
            'exempt_businesses' => $this->exemptBusinessRows(),
            'exempt_wallets' => $this->exemptWalletRows(),
        ];
    }

    /**
     * Ops / audit primary check: site customer liabilities vs live Mevon NGN wallet.
     * variance = mevon_live − site_float (positive = Mevon holds more than we owe customers).
     *
     * @return array{
     *   site_float_total: float,
     *   site_float: array{business: float, wallet: float, rentals: float},
     *   mevon_live_balance: ?float,
     *   mevon_ok: bool,
     *   mevon_message: string,
     *   variance_amount: ?float,
     *   within_tolerance: bool,
     *   tolerance: float,
     *   formula: string
     * }
     */
    public function compareToMevonLive(?array $float = null): array
    {
        $float ??= $this->summarize();
        $siteTotal = (float) ($float['site']['total'] ?? 0);
        $tolerance = max(0.0, (float) config('mevonpay_fees.float_vs_mevon_tolerance', 1000));

        $snap = $this->mevonBalances->forDashboard();
        $mevonOk = (bool) ($snap['ok'] ?? false);
        $mevonLive = $mevonOk ? ($snap['naira_balance'] ?? null) : null;
        $variance = is_numeric($mevonLive) ? round((float) $mevonLive - $siteTotal, 2) : null;
        $within = $variance !== null && abs($variance) <= $tolerance;

        return [
            'site_float_total' => $siteTotal,
            'site_float' => [
                'business' => (float) ($float['business']['total'] ?? 0),
                'wallet' => (float) ($float['wallet']['total'] ?? 0),
                'rentals' => (float) ($float['rentals']['total'] ?? 0),
            ],
            'mevon_live_balance' => is_numeric($mevonLive) ? round((float) $mevonLive, 2) : null,
            'mevon_ok' => $mevonOk,
            'mevon_message' => (string) ($snap['message'] ?? ($mevonOk ? 'OK' : 'Mevon balance unavailable')),
            'variance_amount' => $variance,
            'within_tolerance' => $within,
            'tolerance' => $tolerance,
            'formula' => 'variance = mevon_live_balance − site_float_total',
        ];
    }

    /**
     * @return array{total: float, exempt_total: float, count: int, exempt_count: int}
     */
    private function businessTotals(): array
    {
        if (! Schema::hasTable('businesses')) {
            return ['total' => 0.0, 'exempt_total' => 0.0, 'count' => 0, 'exempt_count' => 0];
        }

        $hasExempt = Schema::hasColumn('businesses', 'balance_audit_exempt');
        $included = Business::query();
        $exempt = Business::query();
        if ($hasExempt) {
            $included->where('balance_audit_exempt', false);
            $exempt->where('balance_audit_exempt', true);
        } else {
            $exempt->whereRaw('0 = 1');
        }

        return [
            'total' => round((float) $included->sum('balance'), 2),
            'exempt_total' => round((float) $exempt->sum('balance'), 2),
            'count' => (int) $included->count(),
            'exempt_count' => (int) $exempt->count(),
        ];
    }

    /**
     * @return array{total: float, exempt_total: float, count: int, exempt_count: int, personal: float, savings: float, business_standalone: float}
     */
    private function walletTotals(): array
    {
        if (! Schema::hasTable('whatsapp_wallets')) {
            return [
                'total' => 0.0,
                'exempt_total' => 0.0,
                'count' => 0,
                'exempt_count' => 0,
                'personal' => 0.0,
                'savings' => 0.0,
                'business_standalone' => 0.0,
            ];
        }

        $hasExempt = Schema::hasColumn('whatsapp_wallets', 'balance_audit_exempt');
        $base = WhatsappWallet::query();
        if ($hasExempt) {
            $base->where('balance_audit_exempt', false);
        }

        $personal = (float) (clone $base)->sum('balance');
        $savings = (float) (clone $base)->sum(DB::raw('COALESCE(savings_balance,0) + COALESCE(flexible_savings_balance,0)'));
        $bizStandalone = (float) (clone $base)->whereNull('linked_business_id')->sum('business_balance');

        $exemptLiability = 0.0;
        $exemptCount = 0;
        if ($hasExempt) {
            $exemptQ = WhatsappWallet::query()->where('balance_audit_exempt', true);
            $exemptCount = (int) (clone $exemptQ)->count();
            $exemptLiability = (float) (clone $exemptQ)->sum('balance')
                + (float) (clone $exemptQ)->sum(DB::raw('COALESCE(savings_balance,0) + COALESCE(flexible_savings_balance,0)'))
                + (float) (clone $exemptQ)->whereNull('linked_business_id')->sum('business_balance');
        }

        $total = $personal + $savings + $bizStandalone;

        return [
            'total' => round($total, 2),
            'exempt_total' => round($exemptLiability, 2),
            'count' => (int) (clone $base)->count(),
            'exempt_count' => $exemptCount,
            'personal' => round($personal, 2),
            'savings' => round($savings, 2),
            'business_standalone' => round($bizStandalone, 2),
        ];
    }

    /**
     * @return array{total: float, exempt_total: float, count: int, exempt_count: int}
     */
    private function rentalsTotals(): array
    {
        if (! Schema::hasTable('renters')) {
            return ['total' => 0.0, 'exempt_total' => 0.0, 'count' => 0, 'exempt_count' => 0];
        }

        $hasExempt = Schema::hasColumn('renters', 'balance_audit_exempt');
        $included = Renter::query();
        $exempt = Renter::query();
        if ($hasExempt) {
            $included->where('balance_audit_exempt', false);
            $exempt->where('balance_audit_exempt', true);
        } else {
            $exempt->whereRaw('0 = 1');
        }

        return [
            'total' => round((float) $included->sum('wallet_balance'), 2),
            'exempt_total' => round((float) $exempt->sum('wallet_balance'), 2),
            'count' => (int) $included->count(),
            'exempt_count' => (int) $exempt->count(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: string, balance: float}>
     */
    private function exemptBusinessRows(): array
    {
        if (! Schema::hasTable('businesses') || ! Schema::hasColumn('businesses', 'balance_audit_exempt')) {
            return [];
        }

        return Business::query()
            ->where('balance_audit_exempt', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'balance'])
            ->map(fn (Business $b) => [
                'id' => (int) $b->id,
                'name' => (string) $b->name,
                'email' => (string) $b->email,
                'balance' => round((float) $b->balance, 2),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, phone: string, name: ?string, liability: float}>
     */
    private function exemptWalletRows(): array
    {
        if (! Schema::hasTable('whatsapp_wallets') || ! Schema::hasColumn('whatsapp_wallets', 'balance_audit_exempt')) {
            return [];
        }

        return WhatsappWallet::query()
            ->where('balance_audit_exempt', true)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (WhatsappWallet $w) {
                $liability = (float) $w->balance
                    + (float) $w->savings_balance
                    + (float) $w->flexible_savings_balance
                    + ($w->linked_business_id ? 0.0 : (float) $w->business_balance);

                return [
                    'id' => (int) $w->id,
                    'phone' => (string) $w->phone_e164,
                    'name' => $w->displayName(),
                    'liability' => round($liability, 2),
                ];
            })
            ->all();
    }
}
