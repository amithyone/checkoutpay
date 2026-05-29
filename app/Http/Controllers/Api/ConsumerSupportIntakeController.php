<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Services\Support\SupportCountryOptionsService;
use App\Services\Support\SupportIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerSupportIntakeController extends Controller
{
    public function __construct(
        private SupportIntakeService $intake,
        private SupportCountryOptionsService $countries,
    ) {}

    public function start(Request $request): JsonResponse
    {
        /** @var ConsumerWalletApiAccount $account */
        $account = $request->user();

        $result = $this->intake->start(
            SupportTicket::CHANNEL_CHECKOUTNOW_APP,
            (int) $account->id
        );

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not start intake.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['payload'],
        ]);
    }

    public function advance(Request $request, string $token): JsonResponse
    {
        $session = $this->authorizeSession($request, $token);
        if ($session instanceof JsonResponse) {
            return $session;
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
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not advance intake.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['payload'],
        ]);
    }

    public function receipt(Request $request, string $token): JsonResponse
    {
        $session = $this->authorizeSession($request, $token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $validated = $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:8192',
        ]);

        $result = $this->intake->storeReceipt($session, $validated['receipt']);

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
        $session = $this->authorizeSession($request, $token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

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
        $session = $this->authorizeSession($request, $token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        $payload = $this->intake->sessionPayload($session);
        $payload['countries'] = $this->countries->supportedCountries();

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    private function authorizeSession(Request $request, string $token): \App\Models\SupportIntakeSession|JsonResponse
    {
        $session = $this->intake->findByToken($token);
        if (! $session || $session->channel !== SupportTicket::CHANNEL_CHECKOUTNOW_APP) {
            return response()->json(['success' => false, 'message' => 'Intake session not found.'], 404);
        }

        /** @var ConsumerWalletApiAccount $account */
        $account = $request->user();
        if ($session->consumer_wallet_api_account_id
            && (int) $session->consumer_wallet_api_account_id !== (int) $account->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return $session;
    }
}
