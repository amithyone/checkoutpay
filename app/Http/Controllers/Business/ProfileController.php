<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        $accountNumbers = $business->accountNumbers()->where('is_active', true)->get();
        $recentPayments = $business->payments()->latest()->take(5)->get();

        return view('business.profile.index', compact('business', 'accountNumbers', 'recentPayments'));
    }

    public function update(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email,' . $business->id,
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
        ]);

        $business->update($validated);

        return redirect()->route('business.profile.index')
            ->with('success', 'Business profile updated successfully');
    }

    public function updatePassword(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $business->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $business->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('business.profile.index')
            ->with('success', 'Password updated successfully');
    }
}
