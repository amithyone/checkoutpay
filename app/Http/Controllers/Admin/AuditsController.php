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
        return view('admin.audits.index', [
            'float' => $this->bankFloat->summarize(),
            'providers' => [
                [
                    'name' => 'Mevon Pay',
                    'description' => 'Inbound fee ledger, outbound API fees, and balance reconciliation for Mevon Pay webhooks and payouts.',
                    'route' => 'admin.audits.mevonpay.index',
                    'icon' => 'fa-wallet',
                ],
            ],
        ]);
    }
}
