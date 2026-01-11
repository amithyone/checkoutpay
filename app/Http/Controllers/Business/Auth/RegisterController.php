<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
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
            'website' => $validated['website'],
            'website_approved' => false, // Requires admin approval
            'is_active' => true,
        ]);

        Auth::guard('business')->login($business);

        return redirect()->route('business.dashboard')
            ->with('success', 'Registration successful! Your website is pending approval. You will be notified once approved.');
    }
}
