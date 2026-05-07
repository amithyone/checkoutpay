<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\Credit\OverdraftInstallmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class OverdraftController extends Controller
{
    /** Overdraft tiers (Naira). */
    public const TIERS = [
        200000   => '₦200,000',
        500000   => '₦500,000',
        1000000  => '₦1,000,000',
        2000000  => '₦2,000,000',
        5000000  => '₦5,000,000',
        10000000 => '₦10,000,000',
    ];

    public function index(): View
    {
        $business = Auth::guard('business')->user();
        $installments = $business->overdraftInstallments()->orderBy('sequence')->get();

        return view('business.overdraft.index', [
            'business' => $business,
            'tiers' => self::TIERS,
            'installments' => $installments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();
        if (! $business->overdraft_eligible) {
            return redirect()->route('business.overdraft.index')
                ->with('error', 'Your business is not eligible to apply for overdraft yet. Please contact support.');
        }
        if ($business->overdraft_status === 'pending') {
            return redirect()->route('business.overdraft.index')
                ->with('info', 'You already have a pending overdraft application.');
        }
        if ($business->hasOverdraftApproved()) {
            return redirect()->route('business.overdraft.index')
                ->with('info', 'Overdraft is already approved.');
        }
        $validated = $request->validate([
            'overdraft_repayment_mode' => [
                'required',
                Rule::in([OverdraftInstallmentService::MODE_SINGLE, OverdraftInstallmentService::MODE_SPLIT_30D]),
            ],
            'overdraft_application_notes' => 'nullable|string|max:2000',
        ]);
        $business->update([
            'overdraft_status' => 'pending',
            'overdraft_requested_at' => now(),
            'overdraft_repayment_mode' => $validated['overdraft_repayment_mode'],
            'overdraft_application_notes' => $validated['overdraft_application_notes'] ?? null,
        ]);

        return redirect()->route('business.overdraft.index')
            ->with('success', 'Overdraft application submitted. Admin will review shortly.');
    }
}
