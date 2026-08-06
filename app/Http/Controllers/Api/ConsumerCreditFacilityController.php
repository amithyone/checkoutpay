<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Credit\CreditFacilityRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerCreditFacilityController extends Controller
{
    public function __construct(
        private CreditFacilityRequestService $requests,
    ) {}

    public function request(Request $request): JsonResponse
    {
        $account = $request->user();
        $wallet = $account?->wallet;
        if (! $wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found.'], 404);
        }

        $payload = $this->normalizePayload($request);

        $validated = validator($payload, [
            'kind' => ['required', 'string', 'in:overdraft,loan'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $result = $this->requests->submit($wallet, $validated);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not submit request.',
            ], (int) ($result['status'] ?? 422));
        }

        $row = $result['request'];
        $payload = $row->toApiArray();

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => array_merge($payload, [
                // Clients may read either flat data.* or data.request.*
                'request' => $payload,
            ]),
        ]);
    }

    /**
     * Accept top-level fields, { request: {...} }, or { data: { request: {...} } }.
     *
     * @return array<string, mixed>
     */
    private function normalizePayload(Request $request): array
    {
        $nested = $request->input('data.request');
        if (is_array($nested)) {
            return $nested;
        }

        $requestWrap = $request->input('request');
        if (is_array($requestWrap)) {
            return $requestWrap;
        }

        return $request->only(['kind', 'amount', 'note']);
    }
}
