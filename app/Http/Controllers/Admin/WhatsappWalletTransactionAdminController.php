<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MevonPayLedgerEntry;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPay\MevonPayTransferStatusService;
use App\Services\MavonPayTransferService;
use App\Services\Whatsapp\WhatsappWalletBankPayoutRefundService;
use App\Services\Whatsapp\WhatsappWalletPendingPayoutReconciliationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WhatsappWalletTransactionAdminController extends Controller
{
    public function __construct(
        private MevonPayTransferStatusService $transferStatus,
        private WhatsappWalletPendingPayoutReconciliationService $payoutReconciliation,
        private WhatsappWalletBankPayoutRefundService $refundService,
    ) {}

    public function index(Request $request): View
    {
        return $this->renderList($request, 'index');
    }

    public function failed(Request $request): View
    {
        $request->merge(['payout_status' => MavonPayTransferService::BUCKET_FAILED]);

        return $this->renderList($request, 'failed');
    }

    public function pending(Request $request): View
    {
        $request->merge(['payout_status' => MavonPayTransferService::BUCKET_PENDING]);

        return $this->renderList($request, 'pending');
    }

    public function p2p(Request $request): View
    {
        $request->merge(['type' => 'p2p']);

        return $this->renderList($request, 'p2p');
    }

    public function show(WhatsappWalletTransaction $transaction): View
    {
        $transaction->load([
            'wallet:id,phone_e164,tier,mevon_virtual_account_number,balance',
            'mevonLedgerEntries' => fn ($q) => $q->orderByDesc('occurred_at'),
        ]);

        $auditUrl = route('admin.audits.mevonpay.index', array_filter([
            'flow_type' => MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER,
            'from' => $transaction->created_at?->copy()->subDays(7)->toDateString(),
            'to' => $transaction->created_at?->copy()->addDays(7)->toDateString(),
        ]));

        return view('admin.whatsapp-wallet.transactions.show', [
            'transaction' => $transaction,
            'payoutBucket' => $transaction->payoutBucketLabel(),
            'statusCheckAvailable' => $this->transferStatus->isAvailable(),
            'canManualRefund' => $transaction->canManualRefund(),
            'auditUrl' => $auditUrl,
        ]);
    }

    public function checkStatus(WhatsappWalletTransaction $transaction): JsonResponse
    {
        $adminId = Auth::guard('admin')->id();
        $result = $this->payoutReconciliation->reconcileTransaction(
            $transaction,
            is_int($adminId) ? $adminId : null,
            onlyIfPending: false,
        );

        return response()->json($result);
    }

    public function manualRefund(Request $request, WhatsappWalletTransaction $transaction): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin || ! $admin->isSuperAdmin()) {
            abort(403);
        }

        if (! $transaction->canManualRefund()) {
            return redirect()
                ->route('admin.whatsapp-wallet.transactions.show', $transaction)
                ->with('error', 'Manual refund is only allowed for pending bank payouts that have not been reversed.');
        }

        $result = $this->refundService->refundTransaction(
            $transaction,
            $admin->id,
            'admin_manual_refund',
        );

        return redirect()
            ->route('admin.whatsapp-wallet.transactions.show', $transaction)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function renderList(Request $request, string $viewMode): View
    {
        $transactions = $this->filteredQuery($request)
            ->paginate(25)
            ->withQueryString();

        $titles = match ($viewMode) {
            'failed' => ['title' => 'Failed wallet payouts', 'subtitle' => 'Bank transfers marked failed or reversed after provider rejection.'],
            'pending' => ['title' => 'Pending wallet payouts', 'subtitle' => 'Bank transfers still awaiting final confirmation from MevonPay.'],
            'p2p' => ['title' => 'P2P transfers', 'subtitle' => 'WhatsApp wallet-to-wallet sends (debit and credit legs).'],
            default => ['title' => 'Wallet transactions', 'subtitle' => 'Universal wallet ledger — filter by payout status, type, or reference.'],
        };

        return view('admin.whatsapp-wallet.transactions.index', [
            'transactions' => $transactions,
            'viewMode' => $viewMode,
            'pageTitle' => $titles['title'],
            'pageSubtitle' => $titles['subtitle'],
            'failedCount' => WhatsappWalletTransaction::countFailedBankPayoutsRecent(),
            'pendingCount' => WhatsappWalletTransaction::countPendingBankPayoutsRecent(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    /**
     * @return Builder<WhatsappWalletTransaction>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = WhatsappWalletTransaction::query()
            ->with(['wallet:id,phone_e164,tier,mevon_virtual_account_number'])
            ->orderByDesc('id');

        if ($request->filled('wallet_id') && is_numeric($request->query('wallet_id'))) {
            $query->where('whatsapp_wallet_id', (int) $request->query('wallet_id'));
        }

        $type = (string) $request->query('type', 'all');
        if ($type === 'p2p') {
            $query->p2p();
        } elseif ($type !== 'all') {
            $query->where('type', $type);
        }

        $payoutStatus = (string) $request->query('payout_status', '');
        if ($payoutStatus !== '' && $payoutStatus !== 'all') {
            $query->bankTransferOut()->payoutStatus($payoutStatus);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        } elseif (! $request->filled('date_to') && ! $request->filled('search') && ! $request->filled('payout_status')) {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        return $query;
    }

    /** @return array<string, string> */
    private function typeOptions(): array
    {
        return [
            'all' => 'All types',
            'p2p' => 'P2P (send & receive)',
            WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT => 'Bank transfer out',
            WhatsappWalletTransaction::TYPE_TOPUP => 'Top-up',
            WhatsappWalletTransaction::TYPE_P2P_DEBIT => 'P2P debit only',
            WhatsappWalletTransaction::TYPE_P2P_CREDIT => 'P2P credit only',
            WhatsappWalletTransaction::TYPE_VTU_AIRTIME => 'VTU airtime',
            WhatsappWalletTransaction::TYPE_VTU_DATA => 'VTU data',
            WhatsappWalletTransaction::TYPE_PARTNER_MERCHANT_PAY => 'Partner pay',
        ];
    }
}
