<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class KeysController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        return view('business.keys.index', compact('business'));
    }

    public function requestAccountNumber(Request $request)
    {
        $business = Auth::guard('business')->user();

        // Check if website is approved
        if (!$business->website_approved) {
            return redirect()->route('business.keys.index')
                ->with('error', 'Your website must be approved before you can request an account number.');
        }

        // Check if business already has an account number
        if ($business->hasAccountNumber()) {
            return redirect()->route('business.keys.index')
                ->with('error', 'You already have an active account number.');
        }

        // Here you could create a notification or log for admin to review
        // For now, we'll just show a success message
        // In production, you might want to:
        // 1. Create a notification for admins
        // 2. Send an email to admins
        // 3. Create a pending account number request record

        return redirect()->route('business.keys.index')
            ->with('success', 'Account number request submitted. Our team will review and assign an account number soon.');
    }
}
