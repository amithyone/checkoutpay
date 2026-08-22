<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Renter;
use App\Services\RecaptchaService;
use App\Services\Security\RegistrationAbuseGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('business.auth.register');
    }

    public function register(Request $request, RecaptchaService $recaptcha, RegistrationAbuseGuard $abuseGuard): RedirectResponse|View
    {
        // Fail closed: enabled without keys must not skip verification
        if ($recaptcha->isMisconfigured()) {
            \Illuminate\Support\Facades\Log::critical('business_register_blocked_recaptcha_misconfigured');

            return back()->withErrors([
                'g-recaptcha-response' => 'Registration is temporarily unavailable. Please try again later.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        $abuseFields = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'website' => $request->input('website'),
        ];
        if ($reason = $abuseGuard->blockReason((string) $request->ip(), $abuseFields)) {
            $abuseGuard->logBlocked('business_register', (string) $request->ip(), $abuseFields, $reason);

            return back()->withErrors([
                'email' => 'Registration could not be completed. Contact support if you need help.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        if ($recaptcha->isEnabled()) {
            $request->validate([
                'g-recaptcha-response' => 'required',
            ], [
                'g-recaptcha-response.required' => 'Please complete the reCAPTCHA verification.',
            ]);

            if (! $recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
                return back()->withErrors([
                    'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
                ])->withInput($request->except('password', 'password_confirmation'));
            }
        }

        // Check if email exists as renter
        $renter = Renter::where('email', $request->email)->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|unique:businesses,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'website' => 'required|url|max:500',
        ]);

        if ($reason = $abuseGuard->blockReason((string) $request->ip(), [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'website' => $validated['website'],
        ])) {
            $abuseGuard->logBlocked('business_register_post_validate', (string) $request->ip(), $validated, $reason);

            return back()->withErrors([
                'website' => 'Enter a valid business website with working DNS.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        // If renter exists, verify password matches
        if ($renter && ! Hash::check($validated['password'], $renter->password)) {
            return back()->withErrors([
                'password' => 'The password does not match your renter account. Please use the same password or reset it.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        $business = Business::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'website' => $validated['website'] ?? null,
            'registration_ip' => $request->ip(),
            'is_active' => true,
            'uses_external_account_numbers' => true,
            'email_verified_at' => null,
        ]);

        BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => $validated['website'],
            'is_approved' => false,
        ]);

        $business->sendEmailVerificationNotification();

        $message = $renter
            ? 'Business account created! You can now access both your renter and business dashboards. Please check your email to verify your business account.'
            : 'Registration successful! Please check your email to verify your account.';

        return redirect()->route('business.verification.notice')
            ->with('registered_email', $business->email)
            ->with('success', $message);
    }
}
