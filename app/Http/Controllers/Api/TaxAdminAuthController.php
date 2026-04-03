<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TaxAdminAuthController extends Controller
{
    /**
     * Issue a Sanctum token for tax admin SPA (nigtax.com/admin). Same credentials as checkout admins.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$admin->is_active) {
            return response()->json(['message' => 'Account is disabled.'], 403);
        }

        if (!in_array($admin->role, [Admin::ROLE_TAX, Admin::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'This portal is only for tax administrators.'], 403);
        }

        $admin->tokens()->where('name', 'tax-admin')->delete();
        $token = $admin->createToken('tax-admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof Admin) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
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

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $admin->password = $validated['password'];
        $admin->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
