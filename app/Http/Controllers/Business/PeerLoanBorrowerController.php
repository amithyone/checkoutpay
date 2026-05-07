<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessLoan;
use App\Models\BusinessLendingOffer;
use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PeerLoanBorrowerController extends Controller
{
    public function myLoans(): View
    {
        $loans = Auth::guard('business')->user()
            ->loansAsBorrower()
            ->with(['offer.lender', 'schedules'])
            ->latest()
            ->paginate(20);

        return view('business.peer-loans.my-loans', compact('loans'));
    }

    public function apply(Request $request, string $slug, BusinessPeerLoanService $loanService): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        if (! $business->peer_lending_borrow_eligible) {
            return back()->with('error', 'Your business is not approved to borrow on this program.');
        }

        $offer = BusinessLendingOffer::where('public_slug', $slug)->publiclyListed()->firstOrFail();

        $request->validate([
            'borrower_message' => 'nullable|string|max:2000',
        ]);

        if ($offer->lender_business_id === $business->id) {
            return back()->with('error', 'You cannot borrow from your own offer.');
        }

        $exists = BusinessLoan::query()
            ->where('business_lending_offer_id', $offer->id)
            ->where('borrower_business_id', $business->id)
            ->whereIn('status', [BusinessLoan::STATUS_PENDING_ADMIN, BusinessLoan::STATUS_ACTIVE])
            ->exists();
        if ($exists) {
            return back()->with('error', 'You already have an active or pending application for this offer.');
        }

        $offer->load('lender');
        if ((float) $offer->lender->balance < (float) $offer->amount) {
            return back()->with('error', 'This offer is temporarily unavailable (lender balance).');
        }

        $principal = (float) $offer->amount;
        $total = $loanService->computeTotalRepayment(
            $principal,
            (float) $offer->interest_rate_percent,
            (int) $offer->term_days
        );

        BusinessLoan::create([
            'business_lending_offer_id' => $offer->id,
            'borrower_business_id' => $business->id,
            'principal' => $principal,
            'total_repayment' => $total,
            'status' => BusinessLoan::STATUS_PENDING_ADMIN,
            'borrower_message' => $request->input('borrower_message'),
        ]);

        return redirect()->route('business.peer-loans.my-loans')
            ->with('success', 'Application submitted. Admin will review before funds are released.');
    }
}
