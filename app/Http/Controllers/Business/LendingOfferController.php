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
        $offers = Auth::guard('business')->user()
            ->lendingOffers()
            ->latest()
            ->paginate(20);

        return view('business.lending-offers.index', compact('offers'));
    }

    public function create(): View
    {
        $business = Auth::guard('business')->user();
        if (! $business->peer_lending_lend_eligible) {
            abort(403, 'Your business is not approved to publish lending offers.');
        }

        return view('business.lending-offers.create', compact('business'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        if (! $business->peer_lending_lend_eligible) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'interest_rate_percent' => 'required|numeric|min:0|max:100',
            'term_days' => 'required|integer|min:7|max:730',
            'repayment_type' => ['required', Rule::in([BusinessLendingOffer::REPAYMENT_LUMP, BusinessLendingOffer::REPAYMENT_SPLIT])],
        ]);

        if ((float) $validated['amount'] > (float) $business->balance + 0.0001) {
            return back()->withErrors(['amount' => 'Amount cannot exceed your current balance.'])->withInput();
        }

        $rawList = $request->input('list_publicly');
        $listPublicly = is_array($rawList)
            ? (bool) (int) end($rawList)
            : (bool) (int) ($rawList ?? 1);

        BusinessLendingOffer::create([
            'lender_business_id' => $business->id,
            'amount' => $validated['amount'],
            'interest_rate_percent' => $validated['interest_rate_percent'],
            'term_days' => $validated['term_days'],
            'repayment_type' => $validated['repayment_type'],
            'status' => BusinessLendingOffer::STATUS_PENDING_ADMIN,
            'list_publicly' => $listPublicly,
        ]);

        return redirect()->route('business.lending-offers.index')
            ->with('success', 'Offer submitted for admin approval.');
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
