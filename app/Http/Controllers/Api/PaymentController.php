<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Services\PaymentService;
use App\Services\ChargeService;
use App\Jobs\CheckPaymentEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a payment request
     */
    public function store(PaymentRequest $request): JsonResponse
    {
        // Increase execution time for account assignment (Cloudflare timeout is 100s, PHP default might be 30s)
        set_time_limit(60); // 60 seconds should be enough with optimizations
        
        try {
            $business = $request->user();

            // Validate webhook URL is from approved website
            $webhookUrl = $request->webhook_url;
            if (!$this->isUrlFromApprovedWebsites($webhookUrl, $business)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook URL must be from your approved website domain.',
                ], 400);
            }

            // Create payment request
            $paymentData = [
                'amount' => $request->amount,
                'payer_name' => $request->payer_name ?? $request->name,
                'bank' => $request->bank,
                'webhook_url' => $webhookUrl,
                'service' => $request->service,
                'transaction_id' => $request->transaction_id,
                'business_website_id' => $request->business_website_id,
                'website_url' => $request->website_url,
            ];

            $payment = $this->paymentService->createPayment($paymentData, $business, $request);

            // Calculate charges (for API response - actual charges applied when payment is approved)
            // Use website-based charges, fallback to business
            $chargeService = app(\App\Services\ChargeService::class);
            $website = $payment->website;
            $charges = $chargeService->calculateCharges($payment->amount, $website, $business);

            // Load account number details
            $payment->load('accountNumberDetails', 'website');

            // Ensure account number exists
            if (!$payment->account_number) {
                Log::error('Payment created without account number via API', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'business_id' => $business->id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to assign account number. Please contact support.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment request created successfully',
                'data' => [
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (float) $payment->amount,
                    'payer_name' => $payment->payer_name,
                    'account_number' => $payment->account_number,
                    'account_name' => $payment->accountNumberDetails->account_name ?? null,
                    'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                    'status' => $payment->status,
                    'expires_at' => $payment->expires_at?->toISOString(),
                    'created_at' => $payment->created_at->toISOString(),
                    'charges' => [
                        'percentage' => $charges['charge_percentage'],
                        'fixed' => $charges['charge_fixed'],
                        'total' => $charges['total_charges'],
                        'paid_by_customer' => $charges['paid_by_customer'],
                        'amount_to_pay' => $charges['amount_to_pay'],
                        'business_receives' => $charges['business_receives'],
                    ],
                    'website' => $payment->website ? [
                        'id' => $payment->website->id,
                        'url' => $payment->website->website_url,
                    ] : null,
                ],
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error creating payment via API', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'business_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database error. Please try again later.',
            ], 500);
        } catch (\Exception $e) {
            // Check if it's a timeout error
            $isTimeout = strpos($e->getMessage(), 'timeout') !== false 
                      || strpos($e->getMessage(), 'timed out') !== false
                      || strpos($e->getMessage(), 'Maximum execution time') !== false;
            
            Log::error('Error creating payment via API', [
                'error' => $e->getMessage(),
                'is_timeout' => $isTimeout,
                'trace' => $e->getTraceAsString(),
                'business_id' => $request->user()->id ?? null,
            ]);

            $message = $isTimeout 
                ? 'Payment service temporarily unavailable. Please try again later.'
                : 'Failed to create payment request. Please try again.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * Get a payment by transaction ID
     */
    public function show(Request $request, string $transactionId): JsonResponse
    {
        $business = $request->user();

        $payment = Payment::with(['accountNumberDetails', 'website'])
            ->where('transaction_id', $transactionId)
            ->where('business_id', $business->id)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $payment->transaction_id,
                'amount' => (float) $payment->amount,
                'payer_name' => $payment->payer_name,
                'bank' => $payment->bank,
                'account_number' => $payment->account_number,
                'account_name' => $payment->accountNumberDetails->account_name ?? null,
                'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                'status' => $payment->status,
                'webhook_url' => $payment->webhook_url,
                'expires_at' => $payment->expires_at?->toISOString(),
                'matched_at' => $payment->matched_at?->toISOString(),
                'approved_at' => $payment->approved_at?->toISOString(),
                'created_at' => $payment->created_at->toISOString(),
                'updated_at' => $payment->updated_at->toISOString(),
                'charges' => [
                    'percentage' => (float) ($payment->charge_percentage ?? 0),
                    'fixed' => (float) ($payment->charge_fixed ?? 0),
                    'total' => (float) ($payment->total_charges ?? 0),
                    'paid_by_customer' => (bool) ($payment->charges_paid_by_customer ?? false),
                    'business_receives' => (float) ($payment->business_receives ?? $payment->amount),
                ],
                'website' => $payment->website ? [
                    'id' => $payment->website->id,
                    'url' => $payment->website->website_url,
                ] : null,
            ],
        ]);
    }

    /**
     * Get payments for authenticated business
     */
    public function index(Request $request): JsonResponse
    {
        $business = $request->user();

        $query = Payment::with(['accountNumberDetails', 'website'])
            ->where('business_id', $business->id)
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        // Filter by website
        if ($request->has('website_id')) {
            $query->where('business_website_id', $request->website_id);
        }

        $payments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments->map(function ($payment) {
                return [
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (float) $payment->amount,
                    'payer_name' => $payment->payer_name,
                    'bank' => $payment->bank,
                    'account_number' => $payment->account_number,
                    'account_name' => $payment->accountNumberDetails->account_name ?? null,
                    'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                    'status' => $payment->status,
                    'expires_at' => $payment->expires_at?->toISOString(),
                    'matched_at' => $payment->matched_at?->toISOString(),
                    'approved_at' => $payment->approved_at?->toISOString(),
                    'created_at' => $payment->created_at->toISOString(),
                    'website' => $payment->website ? [
                        'id' => $payment->website->id,
                        'url' => $payment->website->website_url,
                    ] : null,
                ];
            }),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Update a payment's amount and trigger a manual matcher
     * When an email later matches the new amount, the payment is approved and the same
     * webhook (payment.approved) is sent—payload is unchanged. Do not modify SendWebhookNotification.
     */
    public function updateAmount(Request $request, string $transactionId): JsonResponse
    {
        $request->validate([
            'new_amount' => 'required|numeric|min:1',
        ]);

        $business = $request->user();

        $payment = Payment::with(['website'])
            ->where('transaction_id', $transactionId)
            ->where('business_id', $business->id)
            ->first();

        // 1. Transaction must exist
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found or does not belong to you',
            ], 404);
        }

        // 2. Transaction must be pending to be updated
        if ($payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending payments can have their amounts updated. Current status: ' . $payment->status,
            ], 400);
        }

        // 3. Prevent expired payments from being updated
        if ($payment->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This payment has expired and cannot be updated.',
            ], 400);
        }

        try {
            $oldAmount = $payment->amount;
            $newAmount = $request->input('new_amount');

            // 4. Update the amount on the record
            $payment->amount = $newAmount;

            // Track that this amount was changed via API (so admin UI can display it)
            $emailData = is_array($payment->email_data) ? $payment->email_data : [];
            $emailData['api_amount_update'] = [
                'old_amount' => (float) $oldAmount,
                'new_amount' => (float) $newAmount,
                'updated_at' => now()->toISOString(),
                'updated_by_business_id' => $business->id,
            ];
            $payment->email_data = $emailData;
            
            // 5. Recalculate charges based on the new amount
            $chargeService = app(ChargeService::class);
            $website = $payment->website;
            $charges = $chargeService->calculateCharges($payment->amount, $website, $business);

            // Apply calculated charges to maintain payload structure
            $payment->charge_percentage = $charges['charge_percentage'];
            $payment->charge_fixed = $charges['charge_fixed'];
            $payment->total_charges = $charges['total_charges'];
            $payment->business_receives = $charges['business_receives'];
            $payment->save();

            Log::info('Merchant requested transaction amount update', [
                'business_id' => $business->id,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
            ]);

            // 6. Instantly push to the CheckPaymentEmails job so the engine scans for the new amount
            CheckPaymentEmails::dispatch($payment);

            // Return full payment resource (same shape as show()) so client gets updated status in one call
            $payment->load(['accountNumberDetails', 'website']);
            return response()->json([
                'success' => true,
                'message' => 'Transaction amount successfully updated. Recalculated charges and matching initiated.',
                'data' => [
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (float) $payment->amount,
                    'payer_name' => $payment->payer_name,
                    'bank' => $payment->bank,
                    'account_number' => $payment->account_number,
                    'account_name' => $payment->accountNumberDetails->account_name ?? null,
                    'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                    'status' => $payment->status,
                    'webhook_url' => $payment->webhook_url,
                    'expires_at' => $payment->expires_at?->toISOString(),
                    'matched_at' => $payment->matched_at?->toISOString(),
                    'approved_at' => $payment->approved_at?->toISOString(),
                    'created_at' => $payment->created_at->toISOString(),
                    'updated_at' => $payment->updated_at->toISOString(),
                    'charges' => [
                        'percentage' => (float) ($payment->charge_percentage ?? 0),
                        'fixed' => (float) ($payment->charge_fixed ?? 0),
                        'total' => (float) ($payment->total_charges ?? 0),
                        'paid_by_customer' => (bool) ($payment->charges_paid_by_customer ?? false),
                        'business_receives' => (float) ($payment->business_receives ?? $payment->amount),
                    ],
                    'website' => $payment->website ? [
                        'id' => $payment->website->id,
                        'url' => $payment->website->website_url,
                    ] : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating transaction amount via API', [
                'error' => $e->getMessage(),
                'business_id' => $business->id,
                'payment_id' => $payment->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while trying to update the amount. Please try again.',
            ], 500);
        }
    }

    /**
     * Check if URL is from approved websites
     */
    protected function isUrlFromApprovedWebsites(string $url, $business): bool
    {
        $parsedUrl = parse_url($url);
        $urlHost = $parsedUrl['host'] ?? null;
        
        if (!$urlHost) {
            return false;
        }

        // Normalize host: remove www. prefix and convert to lowercase
        $urlHost = strtolower(preg_replace('/^www\./', '', $urlHost));

        // Check against all approved websites
        $approvedWebsites = $business->approvedWebsites;
        
        foreach ($approvedWebsites as $website) {
            $websiteUrl = $website->website_url;
            $websiteHost = parse_url($websiteUrl, PHP_URL_HOST);
            
            if (!$websiteHost) {
                // If no host in website_url, try to extract it
                $websiteHost = preg_replace('#^https?://#', '', $websiteUrl);
                $websiteHost = preg_replace('#/.*$#', '', $websiteHost);
            }
            
            if ($websiteHost) {
                // Normalize website host
                $websiteHost = strtolower(preg_replace('/^www\./', '', $websiteHost));
                
                // Exact match
                if ($urlHost === $websiteHost) {
                    return true;
                }
                
                // Subdomain match (e.g., api.example.com matches example.com)
                $urlParts = explode('.', $urlHost);
                $websiteParts = explode('.', $websiteHost);
                
                // Check if URL host ends with website host (for subdomain matching)
                if (count($urlParts) >= count($websiteParts)) {
                    $urlSuffix = implode('.', array_slice($urlParts, -count($websiteParts)));
                    if ($urlSuffix === $websiteHost) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
