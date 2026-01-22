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
            return redirect()->route('business.verification.notice')
                ->withErrors(['email' => 'Invalid verification link.']);
        }

        if ($business->hasVerifiedEmail()) {
            // Auto-login and redirect to dashboard
            \Illuminate\Support\Facades\Auth::guard('business')->login($business);
            return redirect()->route('business.dashboard')
                ->with('success', 'Your email has already been verified.');
        }

        if ($business->markEmailAsVerified()) {
            event(new Verified($business));
        }

        // Auto-login and redirect to dashboard
        \Illuminate\Support\Facades\Auth::guard('business')->login($business);

        return redirect()->route('business.dashboard')
            ->with('success', 'Your email has been verified! Welcome to your dashboard.');
    }

    /**
     * Verify email using PIN code.
     */
    public function verifyPin(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|exists:businesses,email',
            'pin' => 'required|digits:6',
        ]);

        $business = Business::where('email', $request->email)->first();

        if (!$business) {
            return redirect()->route('business.verification.notice')
                ->withErrors(['email' => 'Email address not found.']);
        }

        if ($business->hasVerifiedEmail()) {
            // Auto-login and redirect to dashboard
            \Illuminate\Support\Facades\Auth::guard('business')->login($business);
            return redirect()->route('business.dashboard')
                ->with('success', 'Your email has already been verified.');
        }

        // Verify PIN from cache
        $cachedPin = \Illuminate\Support\Facades\Cache::get('email_verification_pin_' . $business->id);

        if (!$cachedPin || $cachedPin !== $request->pin) {
            return redirect()->route('business.verification.notice')
                ->withErrors(['pin' => 'Invalid or expired verification PIN.'])
                ->withInput(['email' => $request->email]);
        }

        // Mark email as verified
        if ($business->markEmailAsVerified()) {
            event(new Verified($business));
        }

        // Clear PIN from cache
        \Illuminate\Support\Facades\Cache::forget('email_verification_pin_' . $business->id);

        // Auto-login and redirect to dashboard
        \Illuminate\Support\Facades\Auth::guard('business')->login($business);

        return redirect()->route('business.dashboard')
            ->with('success', 'Your email has been verified! Welcome to your dashboard.');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        $email = $request->input('email');
        
        if ($email) {
            // Resend without auth (from verify page)
            $business = Business::where('email', $email)->first();
            if ($business) {
                if ($business->hasVerifiedEmail()) {
                    return redirect()->route('business.verification.notice')
                        ->with('info', 'Your email is already verified.');
                }
                $business->sendEmailVerificationNotification();
                return redirect()->route('business.verification.notice')
                    ->with('status', 'Verification email sent! Please check your inbox.')
                    ->with('registered_email', $email);
            }
        }

        // With auth (from dashboard)
        if ($request->user('business')) {
        if ($request->user('business')->hasVerifiedEmail()) {
            return redirect()->route('business.dashboard')
                ->with('info', 'Your email is already verified.');
        }

        $request->user('business')->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent! Please check your email.');
        }

        return redirect()->route('business.verification.notice')
            ->withErrors(['email' => 'Please provide your email address.']);
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
