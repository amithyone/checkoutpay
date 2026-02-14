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
        $hasSavedAccount = $business->hasSavedWithdrawalAccount();

        return view('business.withdrawals.index', compact('withdrawals', 'business', 'hasSavedAccount'));
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
        
        // Get saved withdrawal accounts
        $savedAccounts = $business->withdrawalAccounts()
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('business.withdrawals.create', compact('business', 'savedAccounts'));
    }

    public function validateAccount(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string|min:10|max:10',
            'bank_code' => 'nullable|string',
        ]);

        $nubanService = app(NubanValidationService::class);
        $validationResult = $nubanService->validate($request->account_number, $request->bank_code);

        if ($validationResult && $validationResult['valid']) {
            // Account is valid and active (NUBAN API validates active accounts)
            return response()->json([
                'success' => true,
                'valid' => true,
                'account_name' => $validationResult['account_name'],
                'bank_name' => $validationResult['bank_name'],
                'bank_code' => $validationResult['bank_code'] ?? $request->bank_code,
                'is_active' => true, // If NUBAN validates it, it's active
            ]);
        }

        return response()->json([
            'success' => false,
            'valid' => false,
            'message' => 'Invalid or inactive account number. Please verify and try again.',
        ], 400);
    }

    /**
     * Save withdrawal account for future use
     */
    public function saveAccount(Request $request)
    {
        $business = Auth::guard('business')->user();
        
        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:10',
            'is_default' => 'boolean',
        ]);

        // Check if account already exists for this business
        $existing = $business->withdrawalAccounts()
            ->where('account_number', $validated['account_number'])
            ->where('bank_code', $validated['bank_code'])
            ->first();

        if ($existing) {
            // Update existing account
            $existing->update([
                'account_name' => $validated['account_name'],
                'bank_name' => $validated['bank_name'],
                'is_active' => true,
            ]);
            
            if ($validated['is_default'] ?? false) {
                $existing->setAsDefault();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'account' => $existing,
            ]);
        }

        // Create new saved account
        $account = $business->withdrawalAccounts()->create([
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'bank_name' => $validated['bank_name'],
            'bank_code' => $validated['bank_code'],
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => true,
        ]);

        // If set as default, unset others
        if ($account->is_default) {
            $account->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Account saved successfully',
            'account' => $account,
        ]);
    }

    /**
     * Delete saved withdrawal account
     */
    public function deleteAccount($id)
    {
        $business = Auth::guard('business')->user();
        
        $account = $business->withdrawalAccounts()->findOrFail($id);
        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
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
            'saved_account_id' => 'nullable|exists:business_withdrawal_accounts,id',
            'save_account' => 'boolean',
            'is_default' => 'boolean',
        ];

        // Check if using saved account first
        $savedAccountId = $request->input('saved_account_id');
        $savedAccount = null;
        
        if ($savedAccountId) {
            $savedAccount = $business->withdrawalAccounts()
                ->where('id', $savedAccountId)
                ->where('is_active', true)
                ->first();
        }

        // Only require account fields if business doesn't have account number set and not using saved account
        if (!$hasAccountNumber && !$savedAccount) {
            $rules['bank_code'] = 'required|string';
            $rules['bank_name'] = 'required|string|max:255';
            $rules['account_number'] = 'required|string|max:255';
            $rules['account_name'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Determine which account details to use (priority: saved account > business account > form input)
        if ($savedAccount) {
            $bankName = $savedAccount->bank_name;
            $accountNumber = $savedAccount->account_number;
            $accountName = $savedAccount->account_name;
        } elseif ($hasAccountNumber) {
            $bankName = $accountDetails->bank_name;
            $accountNumber = $accountDetails->account_number;
            $accountName = $accountDetails->account_name;
        } else {
            $bankName = $validated['bank_name'];
            $accountNumber = $validated['account_number'];
            $accountName = $validated['account_name'];
        }

        // Mark saved account as used if applicable
        if ($savedAccount) {
            $savedAccount->markAsUsed();
        }
        
        // Validate account number using NUBAN API if not using stored account or saved account
        if (!$hasAccountNumber && !$savedAccount) {
            $nubanService = app(NubanValidationService::class);
            $bankCode = $validated['bank_code'] ?? null;
            $validationResult = $nubanService->validate($accountNumber, $bankCode);

            if (!$validationResult || !$validationResult['valid']) {
                return back()->withErrors(['account_number' => 'Invalid or inactive account number. Please verify and try again.'])->withInput();
            }

            // Use validated account name and bank name from NUBAN API
            if (!empty($validationResult['account_name'])) {
                $accountName = $validationResult['account_name'];
            }
            if (!empty($validationResult['bank_name'])) {
                $bankName = $validationResult['bank_name'];
            }
            
            // Save account if requested
            if ($request->input('save_account')) {
                // Check if account already exists
                $existing = $business->withdrawalAccounts()
                    ->where('account_number', $accountNumber)
                    ->where('bank_code', $bankCode ?? $validated['bank_code'])
                    ->first();
                
                if ($existing) {
                    // Update existing
                    $existing->update([
                        'account_name' => $accountName,
                        'bank_name' => $bankName,
                        'is_active' => true,
                    ]);
                    if ($request->input('is_default')) {
                        $existing->setAsDefault();
                    }
                } else {
                    // Create new
                    $savedAccount = $business->withdrawalAccounts()->create([
                        'account_number' => $accountNumber,
                        'account_name' => $accountName,
                        'bank_name' => $bankName,
                        'bank_code' => $bankCode ?? $validated['bank_code'],
                        'is_default' => $request->input('is_default', false),
                        'is_active' => true,
                    ]);
                    
                    if ($savedAccount->is_default) {
                        $savedAccount->setAsDefault();
                    }
                }
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

        // Notify admin (Telegram + email) so they can treat withdrawal ASAP
        app(\App\Services\AdminWithdrawalAlertService::class)->send($withdrawal);

        return redirect()->route('business.withdrawals.show', $withdrawal)
            ->with('success', 'Withdrawal request submitted successfully');
    }

    /**
     * Update auto-withdrawal settings from the withdrawals page.
     * Requires at least one saved withdrawal account to enable.
     */
    public function updateAutoWithdrawSettings(Request $request)
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'auto_withdraw_threshold' => 'nullable|numeric|min:0',
            'auto_withdraw_end_of_day' => 'boolean',
        ]);

        $threshold = isset($validated['auto_withdraw_threshold']) ? (float) $validated['auto_withdraw_threshold'] : null;
        $enableAutoWithdraw = $threshold > 0;

        if ($enableAutoWithdraw && !$business->hasSavedWithdrawalAccount()) {
            return redirect()->route('business.withdrawals.index')
                ->with('error', 'Save a withdrawal account first to enable auto-withdrawal. Request a withdrawal and check "Save this account for future withdrawals", or use a saved account.');
        }

        $business->update([
            'auto_withdraw_threshold' => $enableAutoWithdraw ? $threshold : null,
            'auto_withdraw_end_of_day' => $request->boolean('auto_withdraw_end_of_day'),
        ]);

        $message = $enableAutoWithdraw
            ? 'Auto-withdrawal enabled. Withdrawals will be requested when balance reaches ₦' . number_format($threshold, 2) . ($business->auto_withdraw_end_of_day ? ' (daily at 5pm).' : '.')
            : 'Auto-withdrawal disabled.';

        return redirect()->route('business.withdrawals.index')
            ->with('success', $message);
    }
}
