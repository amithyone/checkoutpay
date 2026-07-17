<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\RentalsAdminAppSession;
use App\Services\Rentals\RentalsAdminAppSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class RentalsAdminAuthController extends Controller
{
    public function login(Request $request, RentalsAdminAppSessionService $sessions): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'client_context' => 'nullable|array',
            'client_context.platform' => 'nullable|string|max:16',
            'client_context.app_version' => 'nullable|string|max:64',
            'client_context.device_label' => 'nullable|string|max:160',
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $admin->is_active || $admin->isTaxAdmin()) {
            return response()->json(['message' => 'Account is not authorized for rentals admin.'], 403);
        }

        $sessions->endAllActiveForAdmin($admin, $request, 'replaced_by_new_login');
        $admin->tokens()->where('name', $sessions->tokenName())->delete();

        $accessToken = $sessions->createAccessToken($admin);
        $appSessionId = $sessions->afterTokenIssued(
            $admin,
            RentalsAdminAppSession::LOGIN_PASSWORD,
            $request,
            $accessToken,
        );

        return response()->json([
            'token' => $accessToken->plainTextToken,
            'app_session_id' => $appSessionId,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    public function logout(Request $request, RentalsAdminAppSessionService $sessions): JsonResponse
    {
        return $this->endAppSession($request, $sessions);
    }

    public function endAppSession(Request $request, RentalsAdminAppSessionService $sessions): JsonResponse
    {
        $admin = $request->user();
        if ($admin instanceof Admin) {
            $sessions->endSession($request, $admin);

            $token = $admin->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Signed out.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }
}
