<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Show the hosted checkout page
     */
    public function show(Request $request)
    {
        // Get parameters from query string
        $businessId = $request->query('business_id');
        $amount = $request->query('amount');
        $service = $request->query('service');
        $returnUrl = $request->query('return_url');
        $cancelUrl = $request->query('cancel_url');

        // Validate required parameters
        if (!$businessId || !$amount || !$returnUrl) {
            return view('checkout.error', [
                'error' => 'Missing required parameters. Please ensure business_id, amount, and return_url are provided.',
            ]);
        }

        // Validate business exists and is active (try business_id first, fallback to id for backward compatibility)
        $business = Business::where(function($query) use ($businessId) {
                $query->where('business_id', $businessId)
                      ->orWhere('id', $businessId);
            })
            ->where('is_active', true)
            ->first();

        if (!$business) {
            return view('checkout.error', [
                'error' => 'Business not found or inactive.',
            ]);
        }

        // Validate amount
        $amount = floatval($amount);
        if ($amount < 0.01) {
            return view('checkout.error', [
                'error' => 'Invalid amount. Minimum amount is ₦0.01',
            ]);
        }

        // Validate return URL is from approved website
        if ($business->website && $business->website_approved && !$this->isUrlFromApprovedDomain($returnUrl, $business->website)) {
            return view('checkout.error', [
                'error' => 'Return URL must be from your approved website domain.',
            ]);
        }

        return view('checkout.show', [
            'business' => $business,
            'amount' => $amount,
            'service' => $service,
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl ?? $returnUrl,
        ]);
    }

    /**
     * Process the checkout form submission
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'payer_name' => 'required|string|max:255',
            'service' => 'nullable|string|max:255',
            'return_url' => 'required|url',
            'cancel_url' => 'nullable|url',
        ]);

        // Get business (try business_id first, fallback to id for backward compatibility)
        $business = Business::where(function($query) use ($validated) {
                $query->where('business_id', $validated['business_id'])
                      ->orWhere('id', $validated['business_id']);
            })
            ->where('is_active', true)
            ->firstOrFail();

        // Validate return URL is from approved domain
        if ($business->website && $business->website_approved && !$this->isUrlFromApprovedDomain($validated['return_url'], $business->website)) {
            return back()->withErrors(['return_url' => 'Return URL must be from your approved website domain.'])->withInput();
        }

        try {
            // Normalize return_url to prevent double slashes
            $returnUrl = preg_replace('#([^:])//+#', '$1/', $validated['return_url']);
            
            // Create payment request
            $paymentData = [
                'amount' => $validated['amount'],
                'payer_name' => $validated['payer_name'],
                'service' => $validated['service'] ?? null,
                'webhook_url' => $returnUrl, // Use return_url as webhook_url for redirect-based flow
            ];

            $payment = $this->paymentService->createPayment($paymentData, $business, $request);

            // Store return_url in email_data for hosted checkout
            $emailData = $payment->email_data ?? [];
            $emailData['return_url'] = $validated['return_url'];
            $emailData['cancel_url'] = $validated['cancel_url'] ?? $validated['return_url'];
            $emailData['hosted_checkout'] = true;
            $payment->update(['email_data' => $emailData]);

            // Load account number details
            $payment->load('accountNumberDetails');

            // Redirect to payment details page
            return redirect()->route('checkout.payment', [
                'transactionId' => $payment->transaction_id,
                'return_url' => $validated['return_url'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating payment in checkout', [
                'error' => $e->getMessage(),
                'business_id' => $validated['business_id'],
            ]);

            return back()->withErrors(['error' => 'Failed to create payment. Please try again.'])->withInput();
        }
    }

    /**
     * Show payment details page
     */
    public function payment(Request $request, string $transactionId)
    {
        $payment = Payment::with(['accountNumberDetails', 'business'])
            ->where('transaction_id', $transactionId)
            ->firstOrFail();

        $returnUrl = $request->query('return_url');

        // Get return_url from email_data
        $emailData = $payment->email_data ?? [];
        $paymentReturnUrl = $returnUrl ?? $emailData['return_url'] ?? null;

        // Check if payment is already approved/rejected
        if ($payment->status === Payment::STATUS_APPROVED) {
            // Redirect to return URL with success status
            return $this->redirectToReturnUrl($paymentReturnUrl, [
                'status' => 'success',
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
            ]);
        }

        if ($payment->status === Payment::STATUS_REJECTED) {
            // Redirect to return URL with failure status
            return $this->redirectToReturnUrl($paymentReturnUrl, [
                'status' => 'failed',
                'transaction_id' => $payment->transaction_id,
                'reason' => 'Payment was rejected',
            ]);
        }

        $emailData = $payment->email_data ?? [];
        $paymentReturnUrl = $returnUrl ?? $emailData['return_url'] ?? null;

        return view('checkout.payment', [
            'payment' => $payment,
            'returnUrl' => $paymentReturnUrl,
        ]);
    }

    /**
     * Check payment status (for polling)
     */
    public function checkStatus(string $transactionId)
    {
        $payment = Payment::with(['accountNumberDetails', 'business'])
            ->where('transaction_id', $transactionId)
            ->firstOrFail();

        $shouldRedirect = false;
        $redirectUrl = null;
        $redirectParams = [];

        $emailData = $payment->email_data ?? [];
        $paymentReturnUrl = $emailData['return_url'] ?? null;

        if ($payment->status === Payment::STATUS_APPROVED) {
            $shouldRedirect = true;
            $redirectParams = [
                'status' => 'success',
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
            ];
        } elseif ($payment->status === Payment::STATUS_REJECTED) {
            $shouldRedirect = true;
            $redirectParams = [
                'status' => 'failed',
                'transaction_id' => $payment->transaction_id,
                'reason' => 'Payment was rejected',
            ];
        }

        return response()->json([
            'success' => true,
            'status' => $payment->status,
            'should_redirect' => $shouldRedirect,
            'redirect_url' => $shouldRedirect && $paymentReturnUrl ? $this->buildReturnUrl($paymentReturnUrl, $redirectParams) : null,
            'payment' => [
                'transaction_id' => $payment->transaction_id,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
            ],
        ]);
    }

    /**
     * Check if URL is from approved domain
     */
    protected function isUrlFromApprovedDomain(string $url, string $approvedDomain): bool
    {
        $parsedUrl = parse_url($url);
        $parsedDomain = parse_url($approvedDomain);

        $urlHost = $parsedUrl['host'] ?? null;
        $approvedHost = $parsedDomain['host'] ?? $approvedDomain;

        if (!$urlHost || !$approvedHost) {
            return false;
        }

        // Remove www. prefix for comparison
        $urlHost = preg_replace('/^www\./', '', $urlHost);
        $approvedHost = preg_replace('/^www\./', '', $approvedHost);

        return $urlHost === $approvedHost;
    }

    /**
     * Redirect to return URL with parameters
     */
    protected function redirectToReturnUrl(?string $returnUrl, array $params = []): \Illuminate\Http\RedirectResponse
    {
        if (!$returnUrl) {
            return redirect()->route('checkout.error')->with('error', 'No return URL specified');
        }

        $url = $this->buildReturnUrl($returnUrl, $params);
        return redirect($url);
    }

    /**
     * Build return URL with query parameters
     */
    protected function buildReturnUrl(string $baseUrl, array $params = []): string
    {
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }
}
