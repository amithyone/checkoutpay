<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Services\PaymentService;
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
        } catch (\Exception $e) {
            Log::error('Error creating payment via API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'business_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment request. Please try again.',
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
