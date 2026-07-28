<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MevonPayDiscrepancyAlert;
use App\Services\MevonPay\MevonPayBalanceMonitorService;
use App\Services\MevonPay\MevonPayReconBaselineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MevonPayBalanceMonitorController extends Controller
{
    public function __construct(
        private MevonPayBalanceMonitorService $monitor,
        private MevonPayReconBaselineService $baseline,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.mevonpay-audit.monitor', [
            'summary' => $this->monitor->summary(),
            'baseline' => $this->baseline->info(),
            'alerts' => $this->monitor->recentAlerts(),
            'ledger' => $this->monitor->ledgerWithRunningBalance(50),
            'isSuperAdmin' => (bool) $request->user('admin')?->isSuperAdmin(),
        ]);
    }

    public function initializeBaseline(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin !== null, 403);

        try {
            $this->baseline->initialize($admin);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Mevon balance monitoring started. Current live balance is now the baseline.');
    }

    public function resetBaseline(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin !== null && $admin->isSuperAdmin(), 403);

        if (! $request->boolean('confirm_reset')) {
            return back()->with('error', 'Confirm reset to capture a new baseline.');
        }

        try {
            $this->baseline->reset($admin);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Monitoring baseline reset. Ledger entries before the new baseline time are excluded from totals.');
    }

    public function checkNow(Request $request): RedirectResponse
    {
        $result = $this->monitor->checkNow(MevonPayDiscrepancyAlert::TRIGGER_MANUAL);

        if ($result['skipped'] ?? false) {
            return back()->with('error', $result['message']);
        }

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['message']);
        }

        if ($result['alert_created'] ?? false) {
            return back()->with(
                'warning',
                sprintf(
                    'Variance detected: expected ₦%s, live ₦%s (₦%s). Alert recorded.',
                    number_format((float) $result['expected_balance'], 2),
                    number_format((float) $result['live_balance'], 2),
                    number_format((float) $result['variance_amount'], 2),
                )
            );
        }

        return back()->with('success', 'Balance check complete — within tolerance.');
    }
}
