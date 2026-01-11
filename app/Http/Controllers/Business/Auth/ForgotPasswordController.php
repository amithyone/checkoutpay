<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ForgotPasswordController extends Controller
{
    /**
     * Show the form for requesting a password reset link
     */
    public function showLinkRequestForm(): View
    {
        return view('business.auth.forgot-password');
    }

    /**
     * Send a reset link to the given user
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        // Check if business exists and is active
        $business = Business::where('email', $request->email)->first();
        if (!$business) {
            return back()->withErrors(['email' => 'We could not find a business account with that email address.']);
        }

        if (!$business->is_active) {
            return back()->withErrors(['email' => 'Your account is inactive. Please contact support.']);
        }

        // Send password reset link
        $status = Password::broker('businesses')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'We have emailed your password reset link!');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
