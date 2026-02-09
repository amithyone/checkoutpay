<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Renter;
use App\Services\RecaptchaService;
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

    public function register(Request $request, RecaptchaService $recaptcha): RedirectResponse|View
    {
        if ($recaptcha->isEnabled()) {
            $request->validate([
                'g-recaptcha-response' => 'required',
            ], [
                'g-recaptcha-response.required' => 'Please complete the reCAPTCHA verification.',
            ]);

            if (!$recaptcha->verify($request->input('g-recaptcha-response'), $request->ip())) {
                return back()->withErrors([
                    'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
                ])->withInput($request->except('password', 'password_confirmation'));
            }
        }

        // Check if email exists as renter
        $renter = Renter::where('email', $request->email)->first();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'website' => 'required|url|max:500',
        ]);

        // If renter exists, verify password matches
        if ($renter && !Hash::check($validated['password'], $renter->password)) {
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
            'is_active' => true,
            'email_verified_at' => null, // Email verification required
        ]);

        // Create website entry (requires admin approval)
        BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => $validated['website'],
            'is_approved' => false,
        ]);

        // Send email verification notification
        $business->sendEmailVerificationNotification();

        // Redirect to verify email page with email in session
        $message = $renter 
            ? 'Business account created! You can now access both your renter and business dashboards. Please check your email to verify your business account.'
            : 'Registration successful! Please check your email to verify your account.';

        return redirect()->route('business.verification.notice')
            ->with('registered_email', $business->email)
            ->with('success', $message);
    }
}
