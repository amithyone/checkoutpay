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
        $payment = $this->paymentService->createPayment($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment request received and monitoring started',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    /**
     * Get a specific payment by transaction ID
     */
    public function show(string $transactionId): JsonResponse
    {
        $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

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
