<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerAppSession;
use App\Models\ConsumerAppSessionEvent;
use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerAppSessionService;
use App\Services\Consumer\ConsumerDeviceStepupPushService;
use App\Services\Consumer\ConsumerDeviceStepupService;
use App\Services\Consumer\ConsumerDeviceTrustService;
use App\Services\Consumer\ConsumerWebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerDeviceAuthController extends Controller
{
    public function passkeyRegisterOptions(Request $request, ConsumerWebAuthnService $webauthn): JsonResponse
    {
        $request->validate([
            'device_name' => 'nullable|string|max:120',
        ]);

        $account = $this->accountFor($request);
        $result = $webauthn->registerOptions($account, $request->input('device_name'));

        if (! $result['ok']) {
            return $this->webauthnFailureResponse($result);
        }

        return response()->json([
            'success' => true,
            'data' => $result['options'],
        ]);
    }

    public function passkeyRegisterVerify(Request $request, ConsumerWebAuthnService $webauthn, ConsumerAppSessionService $sessions): JsonResponse
    {
        $request->validate([
            'credential' => 'required|array',
            'platform' => 'required|string|max:32',
            'device_name' => 'nullable|string|max:120',
        ]);

        $account = $this->accountFor($request);
        $result = $webauthn->registerVerify(
            $account,
            (array) $request->input('credential'),
            (string) $request->input('platform'),
            $request->input('device_name') ? (string) $request->input('device_name') : null,
        );

        if (! $result['ok']) {
            return $this->webauthnFailureResponse($result);
        }

        $sessions->recordForAccount(
            $account,
            $request,
            ConsumerAppSessionEvent::TYPE_PASSKEY_REGISTER,
            'Passkey registered on this device',
            [
                'device_id' => $result['device_id'] ?? null,
                'credential_id' => $result['credential_id'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'ok' => true,
                'credential_id' => $result['credential_id'],
                'device_id' => $result['device_id'],
            ],
        ]);
    }

    public function passkeyLoginOptions(Request $request, ConsumerWebAuthnService $webauthn): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
        ]);

        $result = $webauthn->loginOptions((string) $request->input('phone'));
        if (! $result['ok']) {
            return $this->webauthnFailureResponse($result);
        }

        return response()->json([
            'success' => true,
            'data' => $result['options'],
        ]);
    }

    public function passkeyLoginVerify(Request $request, ConsumerWebAuthnService $webauthn, ConsumerDeviceTrustService $trust, ConsumerAppSessionService $sessions): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'credential' => 'required|array',
        ]);

        $result = $webauthn->loginVerify(
            (string) $request->input('phone'),
            (array) $request->input('credential'),
        );

        if (! $result['ok'] || ! isset($result['account'])) {
            return $this->webauthnFailureResponse($result);
        }

        $login = $trust->issueLoginToken($result['account'], resetTransferLock: false);
        $appSessionId = $sessions->afterPlainTokenIssued(
            $result['account'],
            ConsumerAppSession::LOGIN_PASSKEY,
            $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Signed in.',
            'data' => [
                'token' => $login['token'],
                'token_type' => 'Bearer',
                'phone_e164' => $login['phone_e164'],
                'wallet_id' => $login['wallet_id'],
                'transfer_lock_until' => $login['transfer_lock_until'],
                'app_session_id' => $appSessionId,
            ],
        ]);
    }

    public function stepupStart(Request $request, ConsumerDeviceStepupService $stepup): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'pin' => ['nullable', 'regex:/^\d{4}$/', 'required_without:otp_code'],
            'otp_code' => ['nullable', 'string', 'max:12', 'required_without:pin'],
        ]);

        $result = $stepup->start(
            (string) $request->input('phone'),
            $request->filled('pin') ? (string) $request->input('pin') : null,
            $request->filled('otp_code') ? (string) $request->input('otp_code') : null,
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not start step-up.',
            ], 422);
        }

        if (! ($result['stepup_required'] ?? false)) {
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'No step-up required.',
                'data' => ['stepup_required' => false],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'stepup_required' => true,
                'stepup_session' => $result['stepup_session'],
                'other_device_label' => $result['other_device_label'] ?? null,
                'channels' => $result['channels'] ?? ['whatsapp'],
            ], [
                'push_approval_available' => (bool) ($result['push_approval_available'] ?? false),
                'push_approval_expires_at' => $result['push_approval_expires_at'] ?? null,
            ]),
        ]);
    }

    public function stepupPushRequest(Request $request, ConsumerDeviceStepupPushService $push): JsonResponse
    {
        $request->validate([
            'stepup_session' => 'required|string|max:64',
        ]);

        $result = $push->requestApproval((string) $request->input('stepup_session'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['ok'] ? [
                'sent' => true,
                'approval_id' => $result['approval_id'] ?? null,
                'expires_at' => $result['expires_at'] ?? null,
                'polling_interval_seconds' => $result['polling_interval_seconds'] ?? 3,
            ] : null,
        ], $result['ok'] ? 200 : 422);
    }

    public function stepupPushStatus(Request $request, ConsumerDeviceStepupPushService $push): JsonResponse
    {
        $request->validate([
            'stepup_session' => 'required|string|max:64',
        ]);

        $status = $push->approvalStatus((string) $request->input('stepup_session'));

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function stepupPushApprove(Request $request, ConsumerDeviceStepupPushService $push): JsonResponse
    {
        $request->validate([
            'approval_id' => 'required|string|max:64',
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $account = $this->accountFor($request);
        $result = $push->approve($account, (string) $request->input('approval_id'), (string) $request->input('pin'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['ok'] ? ['ok' => true] : null,
        ], $result['ok'] ? 200 : 422);
    }

    public function stepupPushDeny(Request $request, ConsumerDeviceStepupPushService $push): JsonResponse
    {
        $request->validate([
            'approval_id' => 'required|string|max:64',
        ]);

        $account = $this->accountFor($request);
        $result = $push->deny($account, (string) $request->input('approval_id'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['ok'] ? ['ok' => true] : null,
        ], $result['ok'] ? 200 : 422);
    }

    public function stepupBvn(Request $request, ConsumerDeviceStepupService $stepup): JsonResponse
    {
        $request->validate([
            'stepup_session' => 'required|string|max:64',
            'bvn' => 'required|string|size:11',
        ]);

        $result = $stepup->verifyBvn(
            (string) $request->input('stepup_session'),
            (string) $request->input('bvn'),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['ok'] ? ['bvn_verified' => true] : null,
        ], $result['ok'] ? 200 : 422);
    }

    public function stepupOtpRequest(Request $request, ConsumerDeviceStepupService $stepup): JsonResponse
    {
        $request->validate([
            'stepup_session' => 'required|string|max:64',
            'channel' => 'required|string|in:whatsapp,email',
        ]);

        $result = $stepup->requestOtp(
            (string) $request->input('stepup_session'),
            (string) $request->input('channel'),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['ok'] ? ['sent' => true] : null,
        ], $result['ok'] ? 200 : 422);
    }

    public function stepupOtpVerify(Request $request, ConsumerDeviceStepupService $stepup): JsonResponse
    {
        $request->validate([
            'stepup_session' => 'required|string|max:64',
            'code' => 'required|string|max:12',
        ]);

        $result = $stepup->verifyOtp(
            (string) $request->input('stepup_session'),
            (string) $request->input('code'),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['ok'] ? ['stepup_token' => $result['stepup_token']] : null,
        ], $result['ok'] ? 200 : 422);
    }

    public function bindOptions(Request $request, ConsumerDeviceStepupService $stepup, ConsumerWebAuthnService $webauthn): JsonResponse
    {
        $request->validate([
            'stepup_token' => 'required|string|max:128',
            'device_name' => 'nullable|string|max:120',
        ]);

        $session = $stepup->findSessionByStepupToken((string) $request->input('stepup_token'));
        if ($session === null || ! $session->isStepupTokenValid((string) $request->input('stepup_token'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired step-up token.',
            ], 422);
        }

        $account = $session->account;
        if ($account === null) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 422);
        }

        $result = $webauthn->registerOptions(
            $account,
            $request->input('device_name') ? (string) $request->input('device_name') : null,
        );

        if (! $result['ok']) {
            return $this->webauthnFailureResponse($result);
        }

        return response()->json([
            'success' => true,
            'data' => $result['options'],
        ]);
    }

    public function bindDevice(Request $request, ConsumerDeviceStepupService $stepup, ConsumerDeviceTrustService $trust, ConsumerAppSessionService $sessions): JsonResponse
    {
        $request->validate([
            'stepup_token' => 'required|string|max:128',
            'revoke_others' => 'required|boolean',
            'credential' => 'required|array',
            'platform' => 'required|string|max:32',
            'device_name' => 'nullable|string|max:120',
        ]);

        $session = $stepup->findSessionByStepupToken((string) $request->input('stepup_token'));
        if ($session === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired step-up token.',
            ], 422);
        }

        $result = $trust->bindDevice(
            $session,
            (string) $request->input('stepup_token'),
            (bool) $request->boolean('revoke_others'),
            (array) $request->input('credential'),
            (string) $request->input('platform'),
            $request->input('device_name') ? (string) $request->input('device_name') : null,
        );

        if (! $result['ok']) {
            if ($result['unavailable'] ?? false) {
                return $this->webauthnFailureResponse($result);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not bind device.',
            ], 422);
        }

        $account = $session->account;
        $appSessionId = null;
        if ($account instanceof ConsumerWalletApiAccount) {
            $appSessionId = $sessions->afterPlainTokenIssued(
                $account,
                ConsumerAppSession::LOGIN_DEVICE_BIND,
                $request,
            );
            $sessions->recordForAccount(
                $account,
                $request,
                ConsumerAppSessionEvent::TYPE_DEVICE_STEPUP,
                'New trusted device bound after step-up',
                [
                    'devices_revoked' => $result['devices_revoked'] ?? 0,
                    'platform' => (string) $request->input('platform'),
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Device bound.',
            'data' => array_filter([
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'phone_e164' => $account?->phone_e164,
                'wallet_id' => $result['wallet_id'],
                'devices_revoked' => $result['devices_revoked'] ?? 0,
                'transfer_lock_until' => $result['transfer_lock_until'] ?? null,
                'app_session_id' => $appSessionId,
            ], fn ($v) => $v !== null),
        ]);
    }

    public function listDevices(Request $request, ConsumerDeviceTrustService $trust): JsonResponse
    {
        $account = $this->accountFor($request);

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $trust->listDevices($account),
            ],
        ]);
    }

    public function revokeDevice(Request $request, int $id, ConsumerDeviceTrustService $trust): JsonResponse
    {
        $account = $this->accountFor($request);
        $ok = $trust->revokeDevice($account, $id);

        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Device revoked.',
        ]);
    }

    /**
     * @param  array{ok: bool, message?: string, unavailable?: bool}  $result
     */
    private function webauthnFailureResponse(array $result): JsonResponse
    {
        $status = ($result['unavailable'] ?? false) ? 503 : 422;

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Passkey request failed.',
        ], $status);
    }

    private function accountFor(Request $request): ConsumerWalletApiAccount
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }

        return $user;
    }
}
