<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessLendingOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LendingOfferController extends Controller
{
    public function index(): View
    {
        $business = Auth::guard('business')->user();

        $offers = $business
            ->lendingOffers()
            ->latest()
            ->paginate(20);

        $activeLoans = \App\Models\BusinessLoan::query()
            ->with(['borrower', 'offer', 'schedules'])
            ->where('status', \App\Models\BusinessLoan::STATUS_ACTIVE)
            ->whereHas('offer', function ($q) use ($business) {
                $q->where('lender_business_id', $business->id);
            })
            ->latest('disbursed_at')
            ->limit(200)
            ->get();

        return view('business.lending-offers.index', compact('offers', 'activeLoans'));
    }

    public function create(): View
    {
        $business = Auth::guard('business')->user();
        if (! $business->peer_lending_lend_eligible) {
            abort(403, 'Your business is not approved to publish lending offers.');
        }

        $lenderCaps = $business->peerLendingLenderRulesSummary();

        return view('business.lending-offers.create', compact('business', 'lenderCaps'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        if (! $business->peer_lending_lend_eligible) {
            abort(403);
        }

        $maxAmount = $business->peerLendingMaxOfferAmountAllowed();
        $minTerm = $business->peerLendingMinTermDaysForOffers();
        $maxTerm = $business->peerLendingMaxTermDaysForOffers();
        $maxInterest = $business->peerLendingMaxInterestPercentForOffers();

        if ($maxAmount < 0.01) {
            return back()->withErrors(['amount' => 'You cannot post an offer until your available balance (after reserve) is greater than zero.'])->withInput();
        }

        $minOffer = $maxAmount >= 1000 ? 1000 : max(0.01, $maxAmount);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$minOffer, 'max:'.$maxAmount],
            'interest_rate_percent' => ['required', 'numeric', 'min:0', 'max:'.$maxInterest],
            'term_days' => ['required', 'integer', 'min:'.$minTerm, 'max:'.$maxTerm],
            'repayment_type' => ['required', Rule::in([BusinessLendingOffer::REPAYMENT_LUMP, BusinessLendingOffer::REPAYMENT_SPLIT])],
            'repayment_frequency' => [
                'nullable',
                Rule::requiredIf(($request->input('repayment_type') ?? '') === BusinessLendingOffer::REPAYMENT_SPLIT),
                Rule::in(BusinessLendingOffer::FREQUENCIES),
            ],
        ]);

        $repaymentFrequency = $validated['repayment_type'] === BusinessLendingOffer::REPAYMENT_SPLIT
            ? ($validated['repayment_frequency'] ?? BusinessLendingOffer::FREQUENCY_WEEKLY)
            : BusinessLendingOffer::FREQUENCY_WEEKLY;

        if ((float) $validated['amount'] > (float) $business->balance + 0.0001) {
            return back()->withErrors(['amount' => 'Amount cannot exceed your current balance.'])->withInput();
        }

        $reserve = (float) ($business->peer_lending_lender_min_balance_reserve ?? 0);
        if ((float) $validated['amount'] > (float) $business->balance - $reserve + 0.0001) {
            return back()->withErrors(['amount' => 'Amount exceeds what you can lend after the minimum balance reserve (₦'.number_format($reserve, 2).').'])->withInput();
        }

        $rawList = $request->input('list_publicly');
        $listPublicly = is_array($rawList)
            ? (bool) (int) end($rawList)
            : (bool) (int) ($rawList ?? 1);

        $recentDuplicate = BusinessLendingOffer::where('lender_business_id', $business->id)
            ->where('amount', $validated['amount'])
            ->where('interest_rate_percent', $validated['interest_rate_percent'])
            ->where('term_days', $validated['term_days'])
            ->where('repayment_type', $validated['repayment_type'])
            ->where('repayment_frequency', $repaymentFrequency)
            ->where('status', BusinessLendingOffer::STATUS_PENDING_ADMIN)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('id')
            ->first();

        if ($recentDuplicate) {
            return redirect()->route('business.lending-offers.index')
                ->with('success', 'Offer submitted for admin approval.');
        }

        BusinessLendingOffer::create([
            'lender_business_id' => $business->id,
            'amount' => $validated['amount'],
            'interest_rate_percent' => $validated['interest_rate_percent'],
            'term_days' => $validated['term_days'],
            'repayment_type' => $validated['repayment_type'],
            'repayment_frequency' => $repaymentFrequency,
            'status' => BusinessLendingOffer::STATUS_PENDING_ADMIN,
            'list_publicly' => $listPublicly,
        ]);

        return redirect()->route('business.lending-offers.index')
            ->with('success', 'Offer submitted for admin approval.');
    }

    public function edit(BusinessLendingOffer $business_lending_offer): View|RedirectResponse
    {
        $this->authorizeOffer($business_lending_offer);

        if (! $this->offerIsEditable($business_lending_offer)) {
            return redirect()->route('business.lending-offers.index')
                ->with('error', 'This offer can no longer be edited because a borrower has already applied or it has been disbursed.');
        }

        $business = Auth::guard('business')->user();
        $lenderCaps = $business->peerLendingLenderRulesSummary();
        $offer = $business_lending_offer;

        return view('business.lending-offers.edit', compact('business', 'lenderCaps', 'offer'));
    }

    public function update(Request $request, BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $this->authorizeOffer($business_lending_offer);

        if (! $this->offerIsEditable($business_lending_offer)) {
            return redirect()->route('business.lending-offers.index')
                ->with('error', 'This offer can no longer be edited because a borrower has already applied or it has been disbursed.');
        }

        $business = Auth::guard('business')->user();
        $maxAmount = $business->peerLendingMaxOfferAmountAllowed();
        $minTerm = $business->peerLendingMinTermDaysForOffers();
        $maxTerm = $business->peerLendingMaxTermDaysForOffers();
        $maxInterest = $business->peerLendingMaxInterestPercentForOffers();

        if ($maxAmount < 0.01) {
            return back()->withErrors(['amount' => 'You cannot post an offer until your available balance (after reserve) is greater than zero.'])->withInput();
        }

        $minOffer = $maxAmount >= 1000 ? 1000 : max(0.01, $maxAmount);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$minOffer, 'max:'.$maxAmount],
            'interest_rate_percent' => ['required', 'numeric', 'min:0', 'max:'.$maxInterest],
            'term_days' => ['required', 'integer', 'min:'.$minTerm, 'max:'.$maxTerm],
            'repayment_type' => ['required', Rule::in([BusinessLendingOffer::REPAYMENT_LUMP, BusinessLendingOffer::REPAYMENT_SPLIT])],
            'repayment_frequency' => [
                'nullable',
                Rule::requiredIf(($request->input('repayment_type') ?? '') === BusinessLendingOffer::REPAYMENT_SPLIT),
                Rule::in(BusinessLendingOffer::FREQUENCIES),
            ],
        ]);

        $repaymentFrequency = $validated['repayment_type'] === BusinessLendingOffer::REPAYMENT_SPLIT
            ? ($validated['repayment_frequency'] ?? BusinessLendingOffer::FREQUENCY_WEEKLY)
            : BusinessLendingOffer::FREQUENCY_WEEKLY;

        if ((float) $validated['amount'] > (float) $business->balance + 0.0001) {
            return back()->withErrors(['amount' => 'Amount cannot exceed your current balance.'])->withInput();
        }

        $reserve = (float) ($business->peer_lending_lender_min_balance_reserve ?? 0);
        if ((float) $validated['amount'] > (float) $business->balance - $reserve + 0.0001) {
            return back()->withErrors(['amount' => 'Amount exceeds what you can lend after the minimum balance reserve (₦'.number_format($reserve, 2).').'])->withInput();
        }

        $rawList = $request->input('list_publicly');
        $listPublicly = is_array($rawList)
            ? (bool) (int) end($rawList)
            : (bool) (int) ($rawList ?? 1);

        $newStatus = $business_lending_offer->status === BusinessLendingOffer::STATUS_REJECTED
            ? BusinessLendingOffer::STATUS_PENDING_ADMIN
            : $business_lending_offer->status;

        $business_lending_offer->update([
            'amount' => $validated['amount'],
            'interest_rate_percent' => $validated['interest_rate_percent'],
            'term_days' => $validated['term_days'],
            'repayment_type' => $validated['repayment_type'],
            'repayment_frequency' => $repaymentFrequency,
            'list_publicly' => $listPublicly,
            'status' => $newStatus,
        ]);

        return redirect()->route('business.lending-offers.index')
            ->with('success', 'Offer updated successfully.');
    }

    public function destroy(BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $this->authorizeOffer($business_lending_offer);

        if (! $this->offerIsEditable($business_lending_offer)) {
            return redirect()->route('business.lending-offers.index')
                ->with('error', 'This offer can no longer be deleted because a borrower has already applied or it has been disbursed.');
        }

        $business_lending_offer->delete();

        return redirect()->route('business.lending-offers.index')
            ->with('success', 'Offer deleted.');
    }

    private function offerIsEditable(BusinessLendingOffer $offer): bool
    {
        $blockingStatuses = [
            \App\Models\BusinessLoan::STATUS_PENDING_ADMIN,
            \App\Models\BusinessLoan::STATUS_ACTIVE,
            \App\Models\BusinessLoan::STATUS_REPAID,
            \App\Models\BusinessLoan::STATUS_DEFAULTED,
        ];

        return ! $offer->loans()->whereIn('status', $blockingStatuses)->exists();
    }

    public function pause(BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $this->authorizeOffer($business_lending_offer);
        if ($business_lending_offer->status !== BusinessLendingOffer::STATUS_ACTIVE) {
            return back()->with('error', 'Only active offers can be paused.');
        }
        $business_lending_offer->update(['status' => BusinessLendingOffer::STATUS_PAUSED]);

        return back()->with('success', 'Offer paused.');
    }

    public function resume(BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $this->authorizeOffer($business_lending_offer);
        if ($business_lending_offer->status !== BusinessLendingOffer::STATUS_PAUSED) {
            return back()->with('error', 'Only paused offers can be resumed.');
        }
        $business_lending_offer->update(['status' => BusinessLendingOffer::STATUS_ACTIVE]);

        return back()->with('success', 'Offer resumed.');
    }

    public function close(BusinessLendingOffer $business_lending_offer): RedirectResponse
    {
        $this->authorizeOffer($business_lending_offer);
        $business_lending_offer->update(['status' => BusinessLendingOffer::STATUS_CLOSED]);

        return back()->with('success', 'Offer closed.');
    }

    private function authorizeOffer(BusinessLendingOffer $business_lending_offer): void
    {
        if ($business_lending_offer->lender_business_id !== Auth::guard('business')->id()) {
            abort(403);
        }
    }
}
