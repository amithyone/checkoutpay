<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerDeviceStepupSession;
use App\Services\Consumer\StepupFaceBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Namecheap-only: Contabo calls this after CheckFace liveness to mint stepup_token.
 */
class StepupFaceBridgeController extends Controller
{
    public function complete(Request $request, StepupFaceBridge $bridge): JsonResponse
    {
        $validated = $request->validate([
            'stepup_session' => 'required|string|max:64',
            'face_proof' => 'required|string|max:4000',
        ]);

        $proof = $bridge->verifyProofToken(
            (string) $validated['face_proof'],
            (string) $validated['stepup_session'],
        );
        if ($proof === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired face proof.',
            ], 422);
        }

        $session = ConsumerDeviceStepupSession::query()
            ->where('session_token', (string) $validated['stepup_session'])
            ->first();

        if ($session === null || $session->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Step-up session not found or expired.',
            ], 410);
        }

        $session->bvn_verified_at = $session->bvn_verified_at ?? now();
        $session->otp_verified_at = now();
        if (empty($session->stepup_token) || ! $session->isStepupTokenValid((string) $session->stepup_token)) {
            $session->stepup_token = 'bind_'.Str::random(48);
            $session->stepup_token_expires_at = now()->addMinutes(15);
        }
        $session->save();

        return response()->json([
            'success' => true,
            'message' => 'Face step-up finalized.',
            'data' => [
                'stepup_mode' => $session->stepup_mode ?: 'device_mismatch',
                'stepup_token' => $session->stepup_token,
                'pin_reset_required' => true,
                'next_step' => 'bind',
                'score' => $proof['score'] ?? null,
                'liveness_score' => $proof['liveness_score'] ?? null,
            ],
        ]);
    }
}
