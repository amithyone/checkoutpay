<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RentalPaymentController extends Controller
{
    /**
     * Show payment page for an approved rental (renter pays via bank transfer).
     */
    public function show(Request $request, string $code): View|RedirectResponse
    {
        $rental = Rental::where('payment_link_code', $code)
            ->with(['business', 'items', 'payment.accountNumberDetails'])
            ->firstOrFail();

        if ($rental->status === 'cancelled' || $rental->status === 'rejected') {
            return redirect()->route('rentals.index')->with('error', 'This rental is no longer valid.');
        }

        if ($rental->payment && $rental->payment->status === 'approved') {
            return view('rentals.paid', compact('rental'));
        }

        $payment = $rental->payment;
        if (!$payment || !$payment->account_number) {
            return redirect()->route('rentals.index')->with('error', 'Payment details are not available. Please contact the business.');
        }

        if ($payment->isExpired()) {
            return view('rentals.pay-expired', compact('rental'));
        }

        return view('rentals.pay', compact('rental', 'payment'));
    }
}
