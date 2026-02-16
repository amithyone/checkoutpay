<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Invoice;
use App\Models\TicketOrder;
use App\Models\MembershipSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UserDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $email = $user->email;

        $activeRentals = Rental::where('renter_email', $email)
            ->whereNull('returned_at')
            ->whereIn('status', [Rental::STATUS_ACTIVE, Rental::STATUS_APPROVED])
            ->with('business')
            ->orderBy('end_date')
            ->get();

        $recentRentals = Rental::where('renter_email', $email)
            ->with('business')
            ->latest()
            ->limit(10)
            ->get();

        $pendingInvoices = Invoice::where('client_email', $email)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->orderBy('due_date')
            ->get();

        $validTicketOrders = TicketOrder::where('customer_email', $email)
            ->where('payment_status', TicketOrder::PAYMENT_STATUS_PAID)
            ->whereHas('event', fn ($q) => $q->where('start_date', '>=', now()->toDateString()))
            ->with('event')
            ->orderBy('purchased_at')
            ->get();

        $activeMemberships = MembershipSubscription::where('member_email', $email)
            ->where('status', 'active')
            ->where('expires_at', '>=', now()->toDateString())
            ->with('membership')
            ->get();

        $reviewCount = 0;
        $reviewAvg = 0;

        return view('account.dashboard', compact(
            'user',
            'activeRentals',
            'recentRentals',
            'pendingInvoices',
            'validTicketOrders',
            'activeMemberships',
            'reviewCount',
            'reviewAvg'
        ));
    }

    public function purchases(Request $request): View
    {
        $user = $request->user();
        $email = $user->email;

        $rentals = Rental::where('renter_email', $email)->with('business')->latest()->limit(20)->get();
        $ticketOrders = TicketOrder::where('customer_email', $email)
            ->where('payment_status', TicketOrder::PAYMENT_STATUS_PAID)
            ->with('event')
            ->latest('purchased_at')
            ->limit(20)
            ->get();
        $memberships = MembershipSubscription::where('member_email', $email)->with('membership')->latest()->limit(20)->get();

        return view('account.purchases', compact('user', 'rentals', 'ticketOrders', 'memberships'));
    }

    public function invoices(Request $request): View
    {
        $user = $request->user();
        $invoices = Invoice::where('client_email', $user->email)->with('business')->latest()->limit(30)->get();
        return view('account.invoices', compact('user', 'invoices'));
    }

    public function profile(Request $request): View
    {
        $user = $request->user();
        return view('account.profile', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);
        $user->update($validated);
        return redirect()->route('user.profile')->with('success', 'Profile updated.');
    }

    public function reviewsIndex(Request $request): View
    {
        $user = $request->user();
        return view('account.reviews.index', compact('user'));
    }

    public function supportIndex(Request $request): View
    {
        $user = $request->user();
        return view('account.support.index', compact('user'));
    }

    public function settings(Request $request): View
    {
        return $this->profile($request);
    }

    public function referral(Request $request): RedirectResponse
    {
        return redirect()->route('user.dashboard');
    }
}
