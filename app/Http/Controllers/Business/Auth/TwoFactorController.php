<?php

namespace App\Http\Controllers\Business\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function showVerifyForm()
    {
        if (!session()->has('business_2fa_id')) {
            return redirect()->route('business.login')
                ->withErrors(['email' => 'Please log in first.']);
        }

        return view('business.auth.verify-2fa');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $businessId = session()->get('business_2fa_id');
        
        if (!$businessId) {
            return redirect()->route('business.login')
                ->withErrors(['email' => 'Session expired. Please log in again.']);
        }

        $business = Business::findOrFail($businessId);

        if (!$business->verifyTwoFactorCode($request->code)) {
            return back()->withErrors([
                'code' => 'Invalid verification code. Please try again.',
            ]);
        }

        // Clear 2FA session
        session()->forget('business_2fa_id');

        // Log the business in
        Auth::guard('business')->login($business, $request->filled('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('business.dashboard'))
            ->with('success', 'Login successful!');
    }
}
