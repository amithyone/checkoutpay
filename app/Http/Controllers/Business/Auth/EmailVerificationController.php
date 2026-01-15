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
}
