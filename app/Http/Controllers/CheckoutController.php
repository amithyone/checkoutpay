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
            ->with('approvedWebsites')
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

        // Skip return URL validation for demo (business ID 1) or if return URL is from same site
        $isDemo = $business->id == 1;
        $isSameSite = $this->isUrlFromSameSite($returnUrl);
        
        if (!$isDemo && !$isSameSite) {
            // Validate return URL is from approved website
            if (!$this->isUrlFromApprovedWebsites($returnUrl, $business)) {
                return view('checkout.error', [
                    'error' => 'Return URL must be from your approved website domain.',
                ]);
            }
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
            'return_url' => ['required', 'string', function ($attribute, $value, $fail) {
                // Allow relative URLs and full URLs
                if (!filter_var($value, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\s]*$/', $value)) {
                    // If it's not a full URL, try prepending the app URL
                    $fullUrl = config('app.url') . '/' . ltrim($value, '/');
                    if (!filter_var($fullUrl, FILTER_VALIDATE_URL)) {
                        $fail('The return url field must be a valid URL.');
                    }
                }
            }],
            'cancel_url' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (empty($value)) {
                    return; // Allow null/empty
                }
                // Allow relative URLs and full URLs
                if (!filter_var($value, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\s]*$/', $value)) {
                    // If it's not a full URL, try prepending the app URL
                    $fullUrl = config('app.url') . '/' . ltrim($value, '/');
                    if (!filter_var($fullUrl, FILTER_VALIDATE_URL)) {
                        $fail('The cancel url field must be a valid URL.');
                    }
                }
            }],
        ]);

        // Get business (try business_id first, fallback to id for backward compatibility)
        $business = Business::where(function($query) use ($validated) {
                $query->where('business_id', $validated['business_id'])
                      ->orWhere('id', $validated['business_id']);
            })
            ->where('is_active', true)
            ->with('approvedWebsites')
            ->firstOrFail();

        // Skip return URL validation for demo (business ID 1) or if return URL is from same site
        $isDemo = $business->id == 1;
        $isSameSite = $this->isUrlFromSameSite($validated['return_url']);
        
        if (!$isDemo && !$isSameSite) {
            // Validate return URL is from approved domain
            if (!$this->isUrlFromApprovedWebsites($validated['return_url'], $business)) {
                return back()->withErrors(['return_url' => 'Return URL must be from your approved website domain.'])->withInput();
            }
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
                'return_url' => $returnUrl, // Also pass as return_url for website identification
            ];

            $payment = $this->paymentService->createPayment($paymentData, $business, $request);

            // Ensure payment has an account number
            if (!$payment->account_number) {
                Log::error('Payment created without account number', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'business_id' => $business->id,
                ]);
                return back()->withErrors(['error' => 'Unable to assign account number. Please contact support.'])->withInput();
            }

            // Store return_url in email_data for hosted checkout
            $emailData = $payment->email_data ?? [];
            $emailData['return_url'] = $returnUrl;
            $emailData['cancel_url'] = $cancelUrl ?? $returnUrl;
            $emailData['hosted_checkout'] = true;
            $payment->update(['email_data' => $emailData]);

            // Load account number details
            $payment->load('accountNumberDetails');

            // Redirect to payment details page
            return redirect()->route('checkout.payment', [
                'transactionId' => $payment->transaction_id,
                'return_url' => $returnUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating payment in checkout', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'business_id' => $validated['business_id'],
            ]);

            return back()->withErrors(['error' => 'Failed to create payment: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show payment details page
     */
    public function payment(Request $request, string $transactionId)
    {
        try {
            $payment = Payment::with(['accountNumberDetails', 'business'])
                ->where('transaction_id', $transactionId)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Payment not found for checkout', [
                'transaction_id' => $transactionId,
            ]);
            return view('checkout.error', [
                'error' => 'Payment not found. Please check your transaction ID.',
            ]);
        }

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

        // If payment is pending, trigger global match in background to check for matching emails
        if ($payment->status === Payment::STATUS_PENDING) {
            try {
                // Use dispatch to run in background (non-blocking)
                \Illuminate\Support\Facades\Http::timeout(1)->get(url('/cron/global-match'))->throw();
            } catch (\Exception $e) {
                // Silently fail - don't block the response if global match fails
                \Illuminate\Support\Facades\Log::debug('Global match trigger failed (non-critical)', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
     * Check if URL is from any approved website domain
     */
    protected function isUrlFromApprovedWebsites(string $url, Business $business): bool
    {
        // Load approved websites if not already loaded
        if (!$business->relationLoaded('approvedWebsites')) {
            $business->load('approvedWebsites');
        }
        
        // If business has no approved websites, allow the URL (backward compatibility)
        $approvedWebsites = $business->approvedWebsites;
        
        if ($approvedWebsites->isEmpty()) {
            // Backward compatibility: check old website field if no websites in new table
            if ($business->website && $business->website_approved) {
                return $this->isUrlFromApprovedDomain($url, $business->website);
            }
            // If no websites at all, allow (for backward compatibility)
            return true;
        }

        // Check against all approved websites
        foreach ($approvedWebsites as $website) {
            if ($this->isUrlFromApprovedDomain($url, $website->website_url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL is from the same site (current app domain)
     */
    protected function isUrlFromSameSite(string $url): bool
    {
        $parsedUrl = parse_url($url);
        $appUrl = parse_url(config('app.url'));

        $urlHost = $parsedUrl['host'] ?? null;
        $appHost = $appUrl['host'] ?? null;

        if (!$urlHost || !$appHost) {
            return false;
        }

        // Remove www. prefix for comparison
        $urlHost = preg_replace('/^www\./', '', $urlHost);
        $appHost = preg_replace('/^www\./', '', $appHost);

        return $urlHost === $appHost;
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
