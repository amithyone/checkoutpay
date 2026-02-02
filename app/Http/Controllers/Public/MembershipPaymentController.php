<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Services\PaymentService;
use App\Services\ChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class MembershipPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected ChargeService $chargeService
    ) {}

    /**
     * Show payment page for membership
     */
    public function show(string $slug): View|RedirectResponse
    {
        $membership = Membership::where('slug', $slug)
            ->where('is_active', true)
            ->with('business')
            ->firstOrFail();

        // Check if membership is available
        if (!$membership->isAvailable()) {
            return redirect()->route('memberships.show', $slug)
                ->with('error', 'This membership is currently not available.');
        }

        return view('memberships.payment', compact('membership'));
    }

    /**
     * Process membership payment
     */
    public function process(Request $request, string $slug): RedirectResponse
    {
        $membership = Membership::where('slug', $slug)
            ->where('is_active', true)
            ->with('business')
            ->firstOrFail();

        if (!$membership->isAvailable()) {
            return back()->with('error', 'This membership is currently not available.');
        }

        $validated = $request->validate([
            'member_name' => 'required|string|max:255',
            'member_email' => 'required|email|max:255',
            'member_phone' => 'nullable|string|max:20',
        ]);

        try {
            // Create payment
            $paymentData = [
                'amount' => $membership->price,
                'payer_name' => $validated['member_name'],
                'webhook_url' => route('memberships.payment.webhook', ['slug' => $slug]),
                'service' => 'membership',
                'business_website_id' => null,
            ];

            $payment = $this->paymentService->createPayment(
                $paymentData,
                $membership->business,
                $request,
                false // Regular payment pool
            );

            // Store membership and member data in payment email_data
            $emailData = $payment->email_data ?? [];
            $emailData['membership_id'] = $membership->id;
            $emailData['member_name'] = $validated['member_name'];
            $emailData['member_email'] = $validated['member_email'];
            $emailData['member_phone'] = $validated['member_phone'] ?? null;
            $payment->update(['email_data' => $emailData]);

            // Redirect to payment page
            return redirect()->route('payments.show', $payment->transaction_id)
                ->with('success', 'Payment initiated. Please complete the payment to activate your membership.');

        } catch (\Exception $e) {
            Log::error('Failed to create membership payment', [
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to initiate payment. Please try again.');
        }
    }

    /**
     * Handle payment webhook for membership
     */
    public function webhook(Request $request, string $slug)
    {
        $membership = Membership::where('slug', $slug)
            ->with('business')
            ->firstOrFail();

        // This will be called when payment is approved
        // The actual subscription creation happens in the listener
        return response()->json(['success' => true]);
    }

    /**
     * Show success page after payment
     */
    public function success(string $subscriptionNumber): View
    {
        $subscription = MembershipSubscription::where('subscription_number', $subscriptionNumber)
            ->with(['membership.business', 'membership.category'])
            ->firstOrFail();

        return view('memberships.payment-success', compact('subscription'));
    }
}
