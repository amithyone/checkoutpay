<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    /**
     * POST /api/v1/rentals/password/email
     *
     * Send a password reset link to a renter's email using the
     * \"renters\" password broker.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::broker('renters')->sendResetLink(
            $validator->validated(),
            function ($user, string $token) {
                $user->notify(new \App\Notifications\RenterResetPasswordNotification($token));
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
        ], 400);
    }
}

