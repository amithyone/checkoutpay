<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Display the email verification notice.
     */
    public function notice(): View
    {
        return view('business.auth.verify-email');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request): RedirectResponse
    {
        $business = Business::findOrFail($request->route('id'));

        if (!hash_equals((string) $request->route('hash'), sha1($business->getEmailForVerification()))) {
            return redirect()->route('business.login')
                ->withErrors(['email' => 'Invalid verification link.']);
        }

        if ($business->hasVerifiedEmail()) {
            return redirect()->route('business.dashboard')
                ->with('success', 'Your email has already been verified.');
        }

        if ($business->markEmailAsVerified()) {
            event(new Verified($business));
        }

        return redirect()->route('business.login')
            ->with('success', 'Your email has been verified! You can now log in.');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user('business')->hasVerifiedEmail()) {
            return redirect()->route('business.dashboard')
                ->with('info', 'Your email is already verified.');
        }

        $request->user('business')->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent! Please check your email.');
    }

    /**
     * Resend verification email without authentication (for login page).
     */
    public function resendWithoutAuth(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|exists:businesses,email',
        ]);

        $business = Business::where('email', $request->email)->first();

        if (!$business) {
            return back()->withErrors(['email' => 'Email address not found.']);
        }

        if ($business->hasVerifiedEmail()) {
            return redirect()->route('business.login')
                ->with('info', 'Your email is already verified. You can log in now.');
        }

        $business->sendEmailVerificationNotification();

        return redirect()->route('business.login')
            ->with('status', 'Verification link sent! Please check your email inbox.')
            ->withInput(['email' => $request->email]);
    }
}
