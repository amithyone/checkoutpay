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
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        $showWelcomeBack = $request->session()->pull('show_welcome_back', false);

        return view('account.dashboard', compact(
            'user',
            'activeRentals',
            'recentRentals',
            'pendingInvoices',
            'validTicketOrders',
            'activeMemberships',
            'reviewCount',
            'reviewAvg',
            'showWelcomeBack'
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

    /**
     * Show rental detail (details, status, QR code). User must own the rental.
     */
    public function showRental(Request $request, Rental $rental): View|RedirectResponse
    {
        if ($rental->renter_email !== $request->user()->email) {
            abort(403, 'You do not have access to this rental.');
        }
        $rental->load(['business', 'items']);
        $verifyUrl = URL::signedRoute('verify.rental', ['rental' => $rental]);
        $qrSvg = QrCode::format('svg')->size(200)->margin(2)->generate($verifyUrl);
        $rentalQrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        return view('account.purchases.rental', compact('rental', 'rentalQrBase64'));
    }

    /**
     * Show ticket order detail (details, status, QR codes for tickets). User must own the order.
     */
    public function showTicketOrder(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = TicketOrder::where('order_number', $orderNumber)
            ->where('customer_email', $request->user()->email)
            ->with(['event', 'tickets.ticketType', 'tickets.ticketOrder.event'])
            ->firstOrFail();
        $ticketQrs = [];
        foreach ($order->tickets as $ticket) {
            try {
                $verifyUrl = route('verify.ticket', ['token' => $ticket->verification_token]);
                $qrPng = QrCode::format('png')->size(200)->margin(1)->generate($verifyUrl);
                $ticketQrs[$ticket->id] = 'data:image/png;base64,' . base64_encode($qrPng);
            } catch (\Throwable $e) {
                $ticketQrs[$ticket->id] = null;
            }
        }
        return view('account.purchases.ticket', compact('order', 'ticketQrs'));
    }

    /**
     * Show membership subscription detail (details, status, QR code). User must own the subscription.
     */
    public function showMembership(Request $request, MembershipSubscription $membership): View|RedirectResponse
    {
        if ($membership->member_email !== $request->user()->email) {
            abort(403, 'You do not have access to this membership.');
        }
        $membership->load('membership.business');
        $verifyUrl = URL::signedRoute('verify.membership', ['membership' => $membership]);
        $qrSvg = QrCode::format('svg')->size(200)->margin(2)->generate($verifyUrl);
        $membershipQrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        return view('account.purchases.membership', compact('membership', 'membershipQrBase64'));
    }
}
