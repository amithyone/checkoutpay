<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NigtaxCertifiedOrder;
use App\Models\Payment;
use App\Services\NigtaxCertifiedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NigtaxCertifiedPublicController extends Controller
{
    public function __construct(
        protected NigtaxCertifiedReportService $nigtaxCertifiedReportService
    ) {}

    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->nigtaxCertifiedReportService->getSettingsForPublic(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_email' => 'required|email',
            'customer_name' => 'nullable|string|max:255',
            'report_type' => 'required|string|max:32',
            'report_snapshot' => 'nullable|array',
        ]);

        try {
            $result = $this->nigtaxCertifiedReportService->createOrderWithPayment([
                'customer_email' => $validated['customer_email'],
                'customer_name' => $validated['customer_name'] ?? null,
                'report_type' => $validated['report_type'],
                'report_snapshot' => $validated['report_snapshot'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to start payment. Please try again later.',
            ], 503);
        }

        $order = $result['order'];
        $payment = $result['payment'];
        $payment->load('accountNumberDetails');

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $payment->transaction_id,
                'order_id' => $order->id,
                'amount' => (float) $payment->amount,
                'account_number' => $payment->account_number,
                'account_name' => $payment->accountNumberDetails->account_name ?? null,
                'bank_name' => $payment->accountNumberDetails->bank_name ?? null,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function show(string $transactionId): JsonResponse
    {
        $order = NigtaxCertifiedOrder::query()
            ->where('transaction_id', $transactionId)
            ->with(['payment.accountNumberDetails'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $payment = $order->payment;
        $payload = [
            'transaction_id' => $order->transaction_id,
            'order_id' => $order->id,
            'order_status' => $order->status,
            'report_type' => $order->report_type,
            'amount' => (float) $order->amount_paid,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'signed_at' => $order->signed_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
        ];

        if ($payment) {
            $payment->loadMissing('accountNumberDetails');
            $payload['payment_status'] = $payment->status;
            $payload['expires_at'] = $payment->expires_at?->toIso8601String();
            if ($payment->status === Payment::STATUS_PENDING) {
                $payload['account_number'] = $payment->account_number;
                $payload['account_name'] = $payment->accountNumberDetails->account_name ?? null;
                $payload['bank_name'] = $payment->accountNumberDetails->bank_name ?? null;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
