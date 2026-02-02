<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\LoginNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('business.auth.login');
    }

    public function login(Request $request): RedirectResponse|View
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Try business login first
        if (Auth::guard('business')->attempt($credentials, $request->filled('remember'))) {
            $business = Auth::guard('business')->user();

            // Check if email is verified
            if (!$business->hasVerifiedEmail()) {
                Auth::guard('business')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('business.login')
                    ->withErrors([
                        'email' => 'Please verify your email address before logging in. Check your inbox for the verification link.',
                    ])
                    ->with('unverified_email', $business->email)
                    ->onlyInput('email');
            }

            // Check if 2FA is enabled
            if ($business->two_factor_enabled && $business->two_factor_secret) {
                // Store business ID in session for 2FA verification
                $request->session()->put('business_2fa_id', $business->id);
                Auth::guard('business')->logout();

                return redirect()->route('business.2fa.verify')
                    ->with('message', 'Please enter your 2FA code to continue');
            }

            // Send login notification (only if 2FA is not enabled, as 2FA login will send notification after verification)
            $business->notify(new LoginNotification(
                $request->ip(),
                $request->userAgent() ?? 'Unknown'
            ));

            $request->session()->regenerate();

            return redirect()->intended(route('business.dashboard'));
        }

        // Try renter login if business login failed
        if (Auth::guard('renter')->attempt($credentials, $request->filled('remember'))) {
            $renter = Auth::guard('renter')->user();

            // Check if email is verified
            if (!$renter->hasVerifiedEmail()) {
                Auth::guard('renter')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('business.login')
                    ->withErrors([
                        'email' => 'Please verify your email address before logging in.',
                    ])
                    ->with('unverified_email', $renter->email)
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('renter.dashboard'))
                ->with('success', 'Welcome back!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('business')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('business.login');
    }
}
