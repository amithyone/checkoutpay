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

    public function create(): View
    {
        $businesses = Business::where('is_active', true)
            ->orderBy('name')
            ->get();
        
        return view('admin.withdrawals.create', compact('businesses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'amount' => 'required|numeric|min:1',
            'account_number' => 'required|string|max:20',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $business = Business::findOrFail($validated['business_id']);

        // Check if business has sufficient balance
        if ($business->balance < $validated['amount']) {
            return back()
                ->withErrors(['amount' => 'Insufficient balance. Available: ₦' . number_format($business->balance, 2)])
                ->withInput();
        }

        $withdrawal = WithdrawalRequest::create([
            'business_id' => $validated['business_id'],
            'amount' => $validated['amount'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'bank_name' => $validated['bank_name'],
            'notes' => $validated['notes'] ?? null,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        // Log withdrawal request
        $this->logService->logWithdrawalRequest($withdrawal, $request);

        // Send notification to business
        $business->notify(new \App\Notifications\WithdrawalRequestedNotification($withdrawal));

        return redirect()->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'Withdrawal request created successfully');
    }

    public function show(WithdrawalRequest $withdrawal): View
    {
        $withdrawal->load('business', 'processor');
        return view('admin.withdrawals.show', compact('withdrawal'));
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        // Check if already processed
        if ($withdrawal->status !== WithdrawalRequest::STATUS_PENDING) {
            return redirect()->route('admin.withdrawals.show', $withdrawal)
                ->with('error', 'Withdrawal request has already been processed');
        }

        $business = $withdrawal->business;

        // Check if business has sufficient balance
        if ($business->balance < $withdrawal->amount) {
            return redirect()->route('admin.withdrawals.show', $withdrawal)
                ->with('error', 'Business has insufficient balance. Available: ₦' . number_format($business->balance, 2));
        }

        $admin = auth('admin')->user();
        
        // Approve withdrawal
        $withdrawal->approve($admin->id);

        // Deduct from business balance
        $business->decrement('balance', $withdrawal->amount);

        // Log withdrawal approved
        $this->logService->logWithdrawalApproved($withdrawal);

        // Send notification to business
        $business->notify(new \App\Notifications\WithdrawalApprovedNotification($withdrawal));

        return redirect()->route('admin.withdrawals.index')
            ->with('success', 'Withdrawal request approved and balance deducted');
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
