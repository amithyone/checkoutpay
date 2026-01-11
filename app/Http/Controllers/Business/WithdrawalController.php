<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\WithdrawalRequest;

class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();

        $query = $business->withdrawalRequests()->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->paginate(20);

        return view('business.withdrawals.index', compact('withdrawals'));
    }

    public function show($id)
    {
        $business = Auth::guard('business')->user();
        $withdrawal = $business->withdrawalRequests()->findOrFail($id);

        return view('business.withdrawals.show', compact('withdrawal'));
    }

    public function create()
    {
        $business = Auth::guard('business')->user();
        return view('business.withdrawals.create', compact('business'));
    }

    public function store(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $business->balance,
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $withdrawal = $business->withdrawalRequests()->create([
            'amount' => $validated['amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('business.withdrawals.show', $withdrawal)
            ->with('success', 'Withdrawal request submitted successfully');
    }
}
