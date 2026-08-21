<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\BankFloatAuditService;
use Illuminate\View\View;

class AuditsController extends Controller
{
    public function __construct(
        private BankFloatAuditService $bankFloat,
    ) {}

    public function index(): View
    {
        $float = $this->bankFloat->summarize();

        return view('admin.audits.index', [
            'float' => $float,
            'floatVsMevon' => $this->bankFloat->compareToMevonLive($float),
            'providers' => [
                [
                    'name' => 'Mevon Pay',
                    'description' => 'Inbound/outbound fee ledger and transaction export.',
                    'route' => 'admin.audits.mevonpay.index',
                    'icon' => 'fa-wallet',
                ],
                [
                    'name' => 'Mevon ledger monitor',
                    'description' => 'Optional deploy-baseline ledger check (secondary). Ops uses site float vs Mevon live.',
                    'route' => 'admin.audits.mevonpay.monitor',
                    'icon' => 'fa-chart-line',
                ],
            ],
        ]);
    }
}
