<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class OverdraftController extends Controller
{
    /** Overdraft tiers (Naira). */
    public const TIERS = [
        200000   => '₦200,000',
        500000   => '₦500,000',
        1000000  => '₦1,000,000',
    ];

    public function index(): View
    {
        $business = Auth::guard('business')->user();
        return view('business.overdraft.index', [
            'business' => $business,
            'tiers' => self::TIERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        if ($business->overdraft_status === 'pending') {
            return redirect()->route('business.overdraft.index')
                ->with('info', 'You already have a pending overdraft application.');
        }
        if ($business->hasOverdraftApproved()) {
            return redirect()->route('business.overdraft.index')
                ->with('info', 'Overdraft is already approved.');
        }
        $business->update([
            'overdraft_status' => 'pending',
            'overdraft_requested_at' => now(),
        ]);
        return redirect()->route('business.overdraft.index')
            ->with('success', 'Overdraft application submitted. Admin will review shortly.');
    }
}
