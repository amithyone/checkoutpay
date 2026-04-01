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
            ->with(['business', 'items', 'payment.accountNumberDetails', 'secondaryPayment.accountNumberDetails'])
            ->firstOrFail();

        if ($rental->status === 'cancelled' || $rental->status === 'rejected') {
            return redirect()->route('rentals.index')->with('error', 'This rental is no longer valid.');
        }

        $primaryPayment = $rental->payment;
        $secondaryPayment = $rental->secondaryPayment;

        if (
            ($primaryPayment && $primaryPayment->status === \App\Models\Payment::STATUS_APPROVED)
            || ($secondaryPayment && $secondaryPayment->status === \App\Models\Payment::STATUS_APPROVED)
        ) {
            return view('rentals.paid', compact('rental'));
        }

        if (
            (!$primaryPayment && !$secondaryPayment)
            || (($primaryPayment && ! $primaryPayment->account_number) && ($secondaryPayment && ! $secondaryPayment->account_number))
        ) {
            return redirect()->route('rentals.index')->with('error', 'Payment details are not available. Please contact the business.');
        }

        $primaryExpired = $primaryPayment?->isExpired() ?? true;
        $secondaryExpired = $secondaryPayment?->isExpired() ?? true;

        if ($primaryExpired && $secondaryExpired) {
            return view('rentals.pay-expired', compact('rental'));
        }

        return view('rentals.pay', compact('rental', 'primaryPayment', 'secondaryPayment'));
    }
}
