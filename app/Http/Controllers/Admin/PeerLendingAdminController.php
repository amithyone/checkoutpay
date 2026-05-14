<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessLendingOffer;
use App\Models\BusinessLoan;
use App\Models\BusinessLoanLedgerEntry;
use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $pendingLoans = BusinessLoan::with(['offer.lender', 'borrower', 'schedules'])
            ->withExists([
                'ledgerEntries as has_peer_collection_activity' => function ($q) {
                    $q->where('entry_type', BusinessLoanLedgerEntry::TYPE_COLLECTION);
                },
            ])
            ->where('status', BusinessLoan::STATUS_PENDING_ADMIN)
            ->latest()
            ->paginate(25);

        $activeLoans = BusinessLoan::with(['offer.lender', 'borrower', 'schedules'])
            ->withExists([
                'ledgerEntries as has_peer_collection_activity' => function ($q) {
                    $q->where('entry_type', BusinessLoanLedgerEntry::TYPE_COLLECTION);
                },
            ])
            ->where('status', BusinessLoan::STATUS_ACTIVE)
            ->latest('disbursed_at')
            ->limit(200)
            ->get();

        return view('admin.peer-lending.loans-index', compact('pendingLoans', 'activeLoans'));
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

    public function editLoanRepayment(BusinessLoan $loan): View|RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }
        if (! $loan->canAdminEditRepaymentSchedule()) {
            return redirect()->route('admin.peer-lending.loans.index')
                ->with('error', 'Only pending or active loans can be edited.');
        }

        $loan->load(['offer.lender', 'borrower']);

        return view('admin.peer-lending.loan-repayment-edit', compact('loan'));
    }

    public function updateLoanRepayment(Request $request, BusinessLoan $loan, BusinessPeerLoanService $loanService): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }
        if (! $loan->canAdminEditRepaymentSchedule()) {
            return redirect()->route('admin.peer-lending.loans.index')
                ->with('error', 'Only pending or active loans can be edited.');
        }

        $validated = $request->validate([
            'repayment_type' => ['required', Rule::in([BusinessLendingOffer::REPAYMENT_LUMP, BusinessLendingOffer::REPAYMENT_SPLIT])],
            'repayment_frequency' => [
                'nullable',
                Rule::requiredIf(($request->input('repayment_type') ?? '') === BusinessLendingOffer::REPAYMENT_SPLIT),
                Rule::in(BusinessLendingOffer::FREQUENCIES),
            ],
        ]);

        $frequency = $validated['repayment_type'] === BusinessLendingOffer::REPAYMENT_SPLIT
            ? ($validated['repayment_frequency'] ?? BusinessLendingOffer::FREQUENCY_WEEKLY)
            : null;

        DB::transaction(function () use ($loan, $validated, $frequency, $loanService) {
            $loan->update([
                'admin_repayment_type' => $validated['repayment_type'],
                'admin_repayment_frequency' => $frequency,
            ]);

            if ($loan->status !== BusinessLoan::STATUS_ACTIVE) {
                return;
            }

            $loan->refresh()->load(['offer', 'schedules']);
            $paidSum = $loan->repaidAmount();
            $loan->schedules()->delete();

            $working = $loan->fresh(['offer']);
            if ($paidSum < 0.01) {
                $loanService->createSchedules($working);
            } else {
                $loanService->createSchedulesPreservingPriorPaid($working, $paidSum);
            }
        });

        return redirect()->route('admin.peer-lending.loans.index')
            ->with('success', 'Loan repayment schedule updated.');
    }
}
