<?php

namespace App\Http\Controllers\Account\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\Renter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LoginController extends Controller
{
    private const OTP_TTL_MINUTES = 15;
    private const OTP_CACHE_PREFIX = 'login_otp:';

    public function showLoginForm(): View
    {
        return view('account.login');
    }

    /** Password login: try User (web) first, then Business, then Renter. */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('web')->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('user.dashboard'));
        }
        if (Auth::guard('business')->attempt($credentials, $request->filled('remember'))) {
            $business = Auth::guard('business')->user();
            if (!$business->hasVerifiedEmail()) {
                Auth::guard('business')->logout();
                return redirect()->route('account.login')->withErrors(['email' => 'Please verify your email first.'])->onlyInput('email');
            }
            $request->session()->regenerate();
            return redirect()->intended(route('business.dashboard'));
        }
        if (Auth::guard('renter')->attempt($credentials, $request->filled('remember'))) {
            $renter = Auth::guard('renter')->user();
            if (!$renter->hasVerifiedEmail()) {
                Auth::guard('renter')->logout();
                return redirect()->route('account.login')->withErrors(['email' => 'Please verify your email first.'])->onlyInput('email');
            }
            $request->session()->regenerate();
            return redirect()->intended(route('renter.dashboard'));
        }

        return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
    }

    /** Send one-time code to email; support User, Business, Renter. */
    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower($request->email);

        $user = User::where('email', $email)->first();
        $business = Business::where('email', $email)->first();
        $renter = Renter::where('email', $email)->first();

        if (!$user && !$business && !$renter) {
            return back()->withErrors(['email' => 'No account found with this email.'])->withInput();
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = self::OTP_CACHE_PREFIX . $email;
        Cache::put($key, [
            'code' => $code,
            'guard' => $user ? 'web' : ($business ? 'business' : 'renter'),
            'id' => $user ? $user->id : ($business ? $business->id : $renter->id),
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        Mail::send('emails.login-otp-code', [
            'code' => $code,
            'ttlMinutes' => self::OTP_TTL_MINUTES,
        ], function ($message) use ($email) {
            $message->to($email)->subject('Your login code - ' . \App\Models\Setting::get('site_name', 'CheckoutPay'));
        });

        return redirect()->route('account.login.verify-otp')->with('otp_email', $email);
    }

    public function showVerifyOtp(Request $request): View|RedirectResponse
    {
        $email = session('otp_email');
        if (!$email) {
            return redirect()->route('account.login')->with('error', 'Please request a code first.');
        }
        return view('account.verify-otp', ['email' => $email]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);
        $email = strtolower($request->email);
        $code = $request->code;
        $key = self::OTP_CACHE_PREFIX . $email;
        $data = Cache::get($key);
        if (!$data || $data['code'] !== $code) {
            return back()->with('error', 'Invalid or expired code. Please request a new one.')->withInput();
        }
        Cache::forget($key);
        $guard = $data['guard'];
        $id = $data['id'];
        if ($guard === 'web') {
            Auth::guard('web')->loginUsingId($id, true);
            $request->session()->regenerate();
            return redirect()->intended(route('user.dashboard'));
        }
        if ($guard === 'business') {
            Auth::guard('business')->loginUsingId($id, true);
            $request->session()->regenerate();
            return redirect()->intended(route('business.dashboard'));
        }
        Auth::guard('renter')->loginUsingId($id, true);
        $request->session()->regenerate();
        return redirect()->intended(route('renter.dashboard'));
    }
}
