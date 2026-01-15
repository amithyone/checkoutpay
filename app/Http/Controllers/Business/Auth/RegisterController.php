<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessWebsite;
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

    public function register(Request $request): RedirectResponse|View
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'website' => 'required|url|max:500',
        ]);

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

        // Don't auto-login - require email verification first
        return redirect()->route('business.login')
            ->with('success', 'Registration successful! Please check your email to verify your account before logging in.');
    }
}
