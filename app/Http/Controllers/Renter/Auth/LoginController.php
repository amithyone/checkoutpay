<?php

namespace App\Http\Controllers\Renter\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): RedirectResponse
    {
        // Redirect to unified login page
        return redirect()->route('business.login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('renter')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rentals.index')
            ->with('success', 'You have been logged out.');
    }
}
