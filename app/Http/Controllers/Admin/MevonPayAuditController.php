<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MevonPayLedgerEntry;
use App\Services\MevonPay\MevonPayReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MevonPayAuditController extends Controller
{
    public function __construct(
        private MevonPayReconciliationService $reconciliation,
    ) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $report = $this->reconciliation->buildReport($from, $to, $this->openingBalance($request));

        $ledger = $this->reconciliation->paginateLedger(
            $from,
            $to,
            $request->query('direction'),
            $request->query('flow_type'),
        );

        return view('admin.mevonpay-audit.index', [
            'report' => $report,
            'ledger' => $ledger,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'direction' => $request->query('direction'),
            'flowType' => $request->query('flow_type'),
            'openingBalance' => $request->query('opening_balance'),
            'flowTypes' => [
                MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP,
                MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER,
                MevonPayLedgerEntry::FLOW_MERCHANT_CHECKOUT,
                MevonPayLedgerEntry::FLOW_BUSINESS_RUBIES_VA,
                MevonPayLedgerEntry::FLOW_BUSINESS_WITHDRAWAL,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $filename = 'mevonpay-ledger-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($from, $to, $request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, [
                'occurred_at', 'direction', 'flow_type', 'gross_amount',
                'inbound_fee', 'outbound_fee', 'net_mevon_impact',
                'external_reference', 'payout_reference', 'account_number',
                'payout_api', 'payout_bucket',
            ]);
            $q = MevonPayLedgerEntry::query()
                ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->orderBy('occurred_at');
            if ($request->query('direction')) {
                $q->where('direction', $request->query('direction'));
            }
            if ($request->query('flow_type')) {
                $q->where('flow_type', $request->query('flow_type'));
            }
            $q->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->occurred_at?->toDateTimeString(),
                        $row->direction,
                        $row->flow_type,
                        $row->gross_amount,
                        $row->mevon_inbound_fee,
                        $row->mevon_outbound_fee,
                        $row->net_mevon_impact,
                        $row->external_reference,
                        $row->payout_reference,
                        $row->account_number,
                        $row->payout_api,
                        $row->payout_bucket,
                    ]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function openingBalance(Request $request): float
    {
        $raw = $request->query('opening_balance');

        return ($raw !== null && $raw !== '' && is_numeric($raw)) ? (float) $raw : 0.0;
    }
}
