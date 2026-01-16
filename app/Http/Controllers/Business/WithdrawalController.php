<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\WithdrawalRequest;
use App\Services\NubanValidationService;

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

    public function validateAccount(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string|min:10|max:10',
        ]);

        $nubanService = app(NubanValidationService::class);
        $validationResult = $nubanService->validate($request->account_number);

        if ($validationResult && $validationResult['valid']) {
            return response()->json([
                'success' => true,
                'valid' => true,
                'account_name' => $validationResult['account_name'],
                'bank_name' => $validationResult['bank_name'],
            ]);
        }

        return response()->json([
            'success' => false,
            'valid' => false,
            'message' => 'Invalid account number. Please verify and try again.',
        ], 400);
    }

    public function store(Request $request)
    {
        $business = Auth::guard('business')->user();

        // Check if business has account number set
        $hasAccountNumber = $business->hasAccountNumber();
        $accountDetails = $hasAccountNumber ? $business->primaryAccountNumber() : null;

        // Validate password
        if (!Hash::check($request->password, $business->password)) {
            return back()->withErrors(['password' => 'Invalid password.'])->withInput();
        }

        // Build validation rules
        $rules = [
            'amount' => 'required|numeric|min:1|max:' . $business->balance,
            'password' => 'required',
            'notes' => 'nullable|string|max:1000',
        ];

        // Only require account fields if business doesn't have account number set
        if (!$hasAccountNumber) {
            $rules['bank_name'] = 'required|string|max:255';
            $rules['account_number'] = 'required|string|max:255';
            $rules['account_name'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Use stored account details if available, otherwise use form input
        $bankName = $hasAccountNumber ? $accountDetails->bank_name : $validated['bank_name'];
        $accountNumber = $hasAccountNumber ? $accountDetails->account_number : $validated['account_number'];
        $accountName = $hasAccountNumber ? $accountDetails->account_name : $validated['account_name'];

        // Validate account number using NUBAN API if not using stored account
        if (!$hasAccountNumber) {
            $nubanService = app(NubanValidationService::class);
            $validationResult = $nubanService->validate($accountNumber);

            if (!$validationResult || !$validationResult['valid']) {
                return back()->withErrors(['account_number' => 'Invalid account number. Please verify the account number and try again.'])->withInput();
            }

            // Use validated account name and bank name from NUBAN API
            if (!empty($validationResult['account_name'])) {
                $accountName = $validationResult['account_name'];
            }
            if (!empty($validationResult['bank_name'])) {
                $bankName = $validationResult['bank_name'];
            }
        }

        $withdrawal = $business->withdrawalRequests()->create([
            'amount' => $validated['amount'],
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        // Send notification to business
        $business->notify(new \App\Notifications\WithdrawalRequestedNotification($withdrawal));

        return redirect()->route('business.withdrawals.show', $withdrawal)
            ->with('success', 'Withdrawal request submitted successfully');
    }
}
