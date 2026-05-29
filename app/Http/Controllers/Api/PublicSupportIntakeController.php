<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportCountryOptionsService;
use App\Services\Support\SupportIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSupportIntakeController extends Controller
{
    public function __construct(
        private SupportIntakeService $intake,
        private SupportCountryOptionsService $countries,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'nullable|string|in:'.implode(',', SupportTicket::publicChannels()),
        ]);

        $channel = $validated['channel'] ?? SupportTicket::CHANNEL_CHECKOUT_WEB;
        $result = $this->intake->start($channel, null, $request);

        if (! $result['ok']) {
            $status = isset($result['locked_until']) ? 429 : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not start intake.',
                'locked_until' => $result['locked_until'] ?? null,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $result['payload'],
        ]);
    }

    public function advance(Request $request, string $token): JsonResponse
    {
        $session = $this->intake->findByToken($token);
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Intake session not found.'], 404);
        }

        $validated = $request->validate([
            'step' => 'required|string|max:64',
            'value' => 'nullable',
        ]);

        $result = $this->intake->advance(
            $session,
            $validated['step'],
            $validated['value'] ?? null,
            $request
        );

        if (! $result['ok']) {
            $status = isset($result['locked_until']) ? 429 : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not advance intake.',
                'locked_until' => $result['locked_until'] ?? null,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $result['payload'],
        ]);
    }

    public function receipt(Request $request, string $token): JsonResponse
    {
        $session = $this->intake->findByToken($token);
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Intake session not found.'], 404);
        }

        $validated = $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ]);

        $result = $this->intake->storeReceipt($session, $validated['receipt'], $request);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not upload receipt.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['payload'],
        ]);
    }

    public function complete(Request $request, string $token): JsonResponse
    {
        $session = $this->intake->findByToken($token);
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Intake session not found.'], 404);
        }

        $request->validate([
            'consent_accepted' => 'sometimes|accepted',
            'wallet_consent_accepted' => 'sometimes|accepted',
        ]);

        $result = $this->intake->complete($session, $request);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not complete intake.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['payload'],
        ]);
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $session = $this->intake->findByToken($token);
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Intake session not found.'], 404);
        }

        $payload = $this->intake->sessionPayload($session, $request);
        $payload['countries'] = $this->countries->supportedCountries();

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
