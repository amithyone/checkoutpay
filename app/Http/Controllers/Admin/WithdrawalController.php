<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\WithdrawalRequest;
use App\Services\TransactionLogService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function __construct(
        protected TransactionLogService $logService
    ) {}

    public function index(Request $request): View
    {
        $query = WithdrawalRequest::with('business')->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        $withdrawals = $query->paginate(20);

        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    public function show(WithdrawalRequest $withdrawal): View
    {
        $withdrawal->load('business', 'processor');
        return view('admin.withdrawals.show', compact('withdrawal'));
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $admin = auth('admin')->user();
        
        $withdrawal->approve($admin->id);

        // Log withdrawal approved
        $this->logService->logWithdrawalApproved($withdrawal);

        // Deduct from business balance
        $business = $withdrawal->business;
        $business->decrement('balance', $withdrawal->amount);

        return redirect()->route('admin.withdrawals.index')
            ->with('success', 'Withdrawal request approved');
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $admin = auth('admin')->user();
        
        $withdrawal->reject($request->rejection_reason, $admin->id);

        // Log withdrawal rejected
        $this->logService->logWithdrawalRejected($withdrawal, $request->rejection_reason);

        return redirect()->route('admin.withdrawals.index')
            ->with('success', 'Withdrawal request rejected');
    }

    public function markProcessed(WithdrawalRequest $withdrawal): RedirectResponse
    {
        $admin = auth('admin')->user();
        
        $withdrawal->markAsProcessed($admin->id);

        // Log withdrawal processed
        $this->logService->logWithdrawalProcessed($withdrawal);

        return redirect()->route('admin.withdrawals.index')
            ->with('success', 'Withdrawal marked as processed');
    }
}
