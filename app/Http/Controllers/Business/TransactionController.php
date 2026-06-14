<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessTransaction;
use App\Services\Business\BusinessActivityFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        private BusinessActivityFeedService $activityFeed,
    ) {}

    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();
        $transactions = $this->activityFeed->paginate($business, $request);

        return view('business.transactions.index', compact('transactions'));
    }

    public function show($id)
    {
        $business = Auth::guard('business')->user();
        $transaction = $business->payments()->with('website')->findOrFail($id);

        return view('business.transactions.show', [
            'transaction' => $transaction,
            'kind' => 'payment',
        ]);
    }

    public function showLoanRepayment(BusinessTransaction $loanTransaction): View
    {
        $business = Auth::guard('business')->user();

        abort_unless((int) $loanTransaction->business_id === (int) $business->id, 404);
        abort_unless($loanTransaction->business_loan_ledger_entry_id !== null, 404);

        $loanTransaction->load(['counterparty', 'loanLedgerEntry.loan.borrower', 'loanLedgerEntry.loan.offer.lender']);

        return view('business.transactions.show-loan-repayment', [
            'transaction' => $loanTransaction,
            'kind' => 'loan_repayment',
        ]);
    }
}
