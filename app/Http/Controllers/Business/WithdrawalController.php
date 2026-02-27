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
        $savedAccounts = $business->withdrawalAccounts()
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('business.withdrawals.create', compact('business', 'savedAccounts'));
    }

    /**
     * Step 1: Store selected/validated account in session and redirect to step 2.
     */
    public function storeAccountStep(Request $request)
    {
        $business = Auth::guard('business')->user();
        $savedAccountId = $request->input('saved_account_id');
        if ($savedAccountId) {
            $saved = $business->withdrawalAccounts()->where('id', $savedAccountId)->where('is_active', true)->first();
            if (!$saved) {
                return redirect()->route('business.withdrawals.create')->withErrors(['saved_account_id' => 'Invalid saved account.']);
            }
            $request->session()->put('withdrawal_account', [
                'saved_account_id' => $saved->id,
                'account_number' => $saved->account_number,
                'bank_code' => $saved->bank_code,
                'bank_name' => $saved->bank_name,
                'account_name' => $saved->account_name,
            ]);
            return redirect()->route('business.withdrawals.create.confirm');
        }
        $request->validate([
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string|max:20',
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
        ], [
            'account_number.required' => 'Please enter and verify your account number.',
            'account_name.required' => 'Please verify your account number (we will fetch the account name).',
        ]);
        $nubanService = app(NubanValidationService::class);
        $result = $nubanService->validate($request->account_number, $request->bank_code);
        if (!$result || !($result['valid'] ?? false)) {
            return redirect()->route('business.withdrawals.create')
                ->withErrors(['account_number' => 'Invalid or inactive account. Please verify and try again.'])
                ->withInput();
        }
        $request->session()->put('withdrawal_account', [
            'saved_account_id' => null,
            'account_number' => $request->account_number,
            'bank_code' => $result['bank_code'] ?? $request->bank_code,
            'bank_name' => $result['bank_name'] ?? $request->bank_name,
            'account_name' => $result['account_name'] ?? $request->account_name,
        ]);
        return redirect()->route('business.withdrawals.create.confirm');
    }

    /**
     * Step 2: Confirm account (show name), amount and password.
     */
    public function createConfirm(Request $request)
    {
        $account = $request->session()->get('withdrawal_account');
        if (!$account) {
            return redirect()->route('business.withdrawals.create')->with('info', 'Please select or enter your account details first.');
        }
        $business = Auth::guard('business')->user();
        return view('business.withdrawals.create-confirm', compact('business', 'account'));
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

        // Check if business has account number set (legacy KYC account)
        $hasAccountNumber = $business->hasAccountNumber();
        $accountDetails = $hasAccountNumber ? $business->primaryAccountNumber() : null;

        // Validate password
        if (!Hash::check($request->password, $business->password)) {
            return back()->withErrors(['password' => 'Invalid password.'])->withInput();
        }

        $maxWithdraw = $business->getAvailableBalance();
        if ($maxWithdraw < 1) {
            return back()->withErrors(['amount' => 'Insufficient balance. Available: ₦' . number_format($maxWithdraw, 2)])->withInput();
        }

        $rules = [
            'amount' => 'required|numeric|min:1|max:' . max(0, $maxWithdraw),
            'password' => 'required',
            'notes' => 'nullable|string|max:1000',
            'save_account' => 'boolean',
            'is_default' => 'boolean',
        ];

        // Account from step 2 session (step-by-step flow)
        $sessionAccount = $request->session()->get('withdrawal_account');
        if ($sessionAccount) {
            $bankName = $sessionAccount['bank_name'];
            $accountNumber = $sessionAccount['account_number'];
            $accountName = $sessionAccount['account_name'];
            $bankCode = $sessionAccount['bank_code'] ?? null;
            $savedAccountId = $sessionAccount['saved_account_id'] ?? null;
            $savedAccount = $savedAccountId ? $business->withdrawalAccounts()->where('id', $savedAccountId)->where('is_active', true)->first() : null;
            $validated = $request->validate($rules);
            $request->session()->forget('withdrawal_account');
        } else {
            // Legacy single-page flow
            $rules['saved_account_id'] = 'nullable|exists:business_withdrawal_accounts,id';
            $savedAccountId = $request->input('saved_account_id');
            $savedAccount = null;
            if ($savedAccountId) {
                $savedAccount = $business->withdrawalAccounts()->where('id', $savedAccountId)->where('is_active', true)->first();
            }
            if (!$hasAccountNumber && !$savedAccount) {
                $rules['bank_code'] = 'required|string';
                $rules['bank_name'] = 'required|string|max:255';
                $rules['account_number'] = 'required|string|max:255';
                $rules['account_name'] = 'required|string|max:255';
            }
            $validated = $request->validate($rules);

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
                $bankCode = $validated['bank_code'] ?? null;
                $nubanService = app(NubanValidationService::class);
                $validationResult = $nubanService->validate($accountNumber, $bankCode);
                if (!$validationResult || !$validationResult['valid']) {
                    return back()->withErrors(['account_number' => 'Invalid or inactive account number.'])->withInput();
                }
                if (!empty($validationResult['account_name'])) {
                    $accountName = $validationResult['account_name'];
                }
                if (!empty($validationResult['bank_name'])) {
                    $bankName = $validationResult['bank_name'];
                }
                if ($request->input('save_account')) {
                    $existing = $business->withdrawalAccounts()->where('account_number', $accountNumber)->where('bank_code', $bankCode ?? $validated['bank_code'])->first();
                    if ($existing) {
                        $existing->update(['account_name' => $accountName, 'bank_name' => $bankName, 'is_active' => true]);
                        if ($request->input('is_default')) {
                            $existing->setAsDefault();
                        }
                    } else {
                        $newAccount = $business->withdrawalAccounts()->create([
                            'account_number' => $accountNumber,
                            'account_name' => $accountName,
                            'bank_name' => $bankName,
                            'bank_code' => $bankCode ?? $validated['bank_code'],
                            'is_default' => $request->input('is_default', false),
                            'is_active' => true,
                        ]);
                        if ($newAccount->is_default) {
                            $newAccount->setAsDefault();
                        }
                    }
                }
            }
        }

        if ($sessionAccount && ($validated['save_account'] ?? false) && empty($savedAccount)) {
            $existing = $business->withdrawalAccounts()->where('account_number', $accountNumber)->where('bank_code', $bankCode)->first();
            if ($existing) {
                $existing->update(['account_name' => $accountName, 'bank_name' => $bankName, 'is_active' => true]);
                if ($validated['is_default'] ?? false) {
                    $existing->setAsDefault();
                }
            } else {
                $newAccount = $business->withdrawalAccounts()->create([
                    'account_number' => $accountNumber,
                    'account_name' => $accountName,
                    'bank_name' => $bankName,
                    'bank_code' => $bankCode,
                    'is_default' => $validated['is_default'] ?? false,
                    'is_active' => true,
                ]);
                if ($newAccount->is_default) {
                    $newAccount->setAsDefault();
                }
            }
        }

        if ($savedAccount) {
            $savedAccount->markAsUsed();
        }

        $withdrawal = $business->withdrawalRequests()->create([
            'amount' => $validated['amount'],
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        $business->notify(new \App\Notifications\WithdrawalRequestedNotification($withdrawal));
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
