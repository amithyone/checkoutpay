<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Auth\Events\Verified;
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
     * Mark the business email as verified (signed URL only — no auto-login).
     */
    public function verify(Request $request): RedirectResponse
    {
        $business = Business::findOrFail($request->route('id'));

        if (! hash_equals((string) $request->route('hash'), sha1($business->getEmailForVerification()))) {
            return redirect()->route('business.login')
                ->withErrors(['email' => 'Invalid verification link.']);
        }

        if ($business->hasVerifiedEmail()) {
            return redirect()->route('business.login')
                ->with('info', 'Your email is already verified. Please log in.')
                ->withInput(['email' => $business->email]);
        }

        if ($business->markEmailAsVerified()) {
            event(new Verified($business));
        }

        return redirect()->route('business.login')
            ->with('success', 'Your email has been verified! Please log in to continue.')
            ->withInput(['email' => $business->email]);
    }

    /**
     * Verify email using PIN code (no auto-login).
     */
    public function verifyPin(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|exists:businesses,email',
            'pin' => 'required|digits:6',
        ]);

        $business = Business::where('email', $request->email)->first();

        if (! $business) {
            return redirect()->route('business.verification.notice')
                ->withErrors(['email' => 'Email address not found.']);
        }

        if ($business->hasVerifiedEmail()) {
            return redirect()->route('business.login')
                ->with('info', 'Your email is already verified. Please log in.')
                ->withInput(['email' => $business->email]);
        }

        $cachedPin = \Illuminate\Support\Facades\Cache::get('email_verification_pin_'.$business->id);

        if (! $cachedPin || $cachedPin !== $request->pin) {
            return redirect()->route('business.verification.notice')
                ->withErrors(['pin' => 'Invalid or expired verification PIN.'])
                ->withInput(['email' => $request->email]);
        }

        if ($business->markEmailAsVerified()) {
            event(new Verified($business));
        }

        \Illuminate\Support\Facades\Cache::forget('email_verification_pin_'.$business->id);

        return redirect()->route('business.login')
            ->with('success', 'Your email has been verified! Please log in to continue.')
            ->withInput(['email' => $business->email]);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        $email = $request->input('email');

        if ($email) {
            $business = Business::where('email', $email)->first();
            if ($business) {
                if ($business->hasVerifiedEmail()) {
                    return redirect()->route('business.verification.notice')
                        ->with('info', 'Your email is already verified.')
                        ->with('registered_email', $email);
                }
                $business->sendEmailVerificationNotification();

                return redirect()->route('business.verification.notice')
                    ->with('status', 'Verification email sent! Please check your inbox.')
                    ->with('registered_email', $email);
            }
            $renter = \App\Models\Renter::where('email', $email)->first();
            if ($renter) {
                if ($renter->hasVerifiedEmail()) {
                    return redirect()->route('business.verification.notice')
                        ->with('info', 'Your email is already verified.')
                        ->with('registered_email', $email);
                }
                $renter->sendEmailVerificationNotification();

                return redirect()->route('business.verification.notice')
                    ->with('status', 'Verification email sent! Please check your inbox.')
                    ->with('registered_email', $email);
            }

            return redirect()->route('business.verification.notice')
                ->withErrors(['email' => 'No account found with this email address.'])
                ->withInput(['email' => $email]);
        }

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
            'email' => 'required|email',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = $request->email;

        $business = Business::where('email', $email)->first();
        if ($business) {
            if ($business->hasVerifiedEmail()) {
                return redirect()->route('business.login')
                    ->with('info', 'Your email is already verified. You can log in now.')
                    ->withInput(['email' => $email]);
            }
            $business->sendEmailVerificationNotification();

            return redirect()->route('business.login')
                ->with('status', 'Verification link sent! Please check your email inbox.')
                ->withInput(['email' => $email]);
        }

        $renter = \App\Models\Renter::where('email', $email)->first();
        if ($renter) {
            if ($renter->hasVerifiedEmail()) {
                return redirect()->route('business.login')
                    ->with('info', 'Your email is already verified. You can log in now.')
                    ->withInput(['email' => $email]);
            }
            $renter->sendEmailVerificationNotification();

            return redirect()->route('business.login')
                ->with('status', 'Verification link sent! Please check your email inbox.')
                ->withInput(['email' => $email]);
        }

        return redirect()->route('business.login')
            ->withErrors(['email' => 'No account found with this email address.'])
            ->withInput(['email' => $email]);
    }
}
