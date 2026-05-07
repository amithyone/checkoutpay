<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessLoan;
use App\Models\BusinessLendingOffer;
use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeerLendingAdminController extends Controller
{
    public function offersIndex(): View
    {
        $offers = BusinessLendingOffer::with('lender')
            ->where('status', BusinessLendingOffer::STATUS_PENDING_ADMIN)
            ->latest()
            ->paginate(25);

        return view('admin.peer-lending.offers-index', compact('offers'));
    }

    public function approveOffer(BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }
        if ($business_lending_offer->status !== BusinessLendingOffer::STATUS_PENDING_ADMIN) {
            return back()->with('error', 'Offer is not pending.');
        }
        $business_lending_offer->update([
            'status' => BusinessLendingOffer::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(60),
        ]);

        return redirect()->route('admin.peer-lending.offers.index')
            ->with('success', 'Offer approved and listed.');
    }

    public function rejectOffer(Request $request, BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }
        $request->validate(['admin_notes' => 'nullable|string|max:1000']);
        $business_lending_offer->update([
            'status' => BusinessLendingOffer::STATUS_REJECTED,
            'admin_notes' => $request->input('admin_notes'),
        ]);

        return redirect()->route('admin.peer-lending.offers.index')
            ->with('success', 'Offer rejected.');
    }

    public function loansIndex(): View
    {
        $loans = BusinessLoan::with(['offer.lender', 'borrower'])
            ->where('status', BusinessLoan::STATUS_PENDING_ADMIN)
            ->latest()
            ->paginate(25);

        return view('admin.peer-lending.loans-index', compact('loans'));
    }

    public function approveLoan(BusinessPeerLoanService $loanService, BusinessLoan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }
        try {
            $loanService->disburse($loan);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.peer-lending.loans.index')
            ->with('success', 'Loan disbursed.');
    }

    public function rejectLoan(Request $request, BusinessLoan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }
        if ($loan->status !== BusinessLoan::STATUS_PENDING_ADMIN) {
            return back()->with('error', 'Loan is not pending.');
        }
        $loan->update(['status' => BusinessLoan::STATUS_REJECTED]);

        return redirect()->route('admin.peer-lending.loans.index')
            ->with('success', 'Loan application rejected.');
    }
}
