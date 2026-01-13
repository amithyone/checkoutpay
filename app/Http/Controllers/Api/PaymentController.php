<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Store a new payment request
     */
    public function store(PaymentRequest $request): JsonResponse
    {
        $business = $request->user(); // Get business from API key middleware
        
        $payment = $this->paymentService->createPayment(
            $request->validated(),
            $business,
            $request
        );

        // Load account number details for response
        $payment->load('accountNumberDetails');

        return response()->json([
            'success' => true,
            'message' => 'Payment request received and monitoring started',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    /**
     * Get a specific payment by transaction ID
     */
    public function show(Request $request, string $transactionId): JsonResponse
    {
        $business = $request->user(); // Get business from API key middleware
        
        $payment = Payment::with('accountNumberDetails')
            ->where('transaction_id', $transactionId)
            ->firstOrFail();

        // Log the status check request (only if payment is still pending)
        if ($payment->status === Payment::STATUS_PENDING) {
            \App\Models\PaymentStatusCheck::create([
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'business_id' => $business->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payment_status' => $payment->status,
            ]);
            
            // Trigger global match in background to check for matching emails
            // This ensures that when users check their transaction, we also check for matches
            try {
                // Use dispatch to run in background (non-blocking)
                \Illuminate\Support\Facades\Http::timeout(1)->get(url('/cron/global-match'))->throw();
            } catch (\Exception $e) {
                // Silently fail - don't block the API response if global match fails
                \Illuminate\Support\Facades\Log::debug('Global match trigger failed (non-critical)', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => new PaymentResource($payment),
        ]);
    }

    /**
     * Get all payments
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $payments = $query->paginate($request->get('per_page', 15));

        return PaymentResource::collection($payments);
    }
}
