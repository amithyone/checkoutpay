<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountNumber;
use App\Models\Business;
use App\Services\AccountNumberService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountNumberController extends Controller
{
    public function __construct(
        protected AccountNumberService $accountNumberService
    ) {}

    public function index(Request $request): View
    {
        $query = AccountNumber::with('business')->latest();

        if ($request->has('type')) {
            if ($request->type === 'pool') {
                $query->pool();
            } elseif ($request->type === 'business') {
                $query->businessSpecific();
            }
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $accountNumbers = $query->paginate(20);

        return view('admin.account-numbers.index', compact('accountNumbers'));
    }

    public function create(): View
    {
        $businesses = Business::where('is_active', true)->get();
        return view('admin.account-numbers.create', compact('businesses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_number' => 'required|string|unique:account_numbers,account_number',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'business_id' => 'nullable|exists:businesses,id',
            'is_pool' => 'boolean',
        ]);

        if ($validated['is_pool'] ?? false) {
            $this->accountNumberService->createPoolAccount($validated);
        } else {
            $business = Business::findOrFail($validated['business_id']);
            $this->accountNumberService->createBusinessAccount($business, $validated);
        }

        return redirect()->route('admin.account-numbers.index')
            ->with('success', 'Account number created successfully');
    }

    public function edit(AccountNumber $accountNumber): View
    {
        $businesses = Business::where('is_active', true)->get();
        return view('admin.account-numbers.edit', compact('accountNumber', 'businesses'));
    }

    public function update(Request $request, AccountNumber $accountNumber): RedirectResponse
    {
        $validated = $request->validate([
            'account_number' => 'required|string|unique:account_numbers,account_number,' . $accountNumber->id,
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'business_id' => 'nullable|exists:businesses,id',
            'is_active' => 'boolean',
        ]);

        $accountNumber->update($validated);

        return redirect()->route('admin.account-numbers.index')
            ->with('success', 'Account number updated successfully');
    }

    public function destroy(AccountNumber $accountNumber): RedirectResponse
    {
        $accountNumber->delete();

        return redirect()->route('admin.account-numbers.index')
            ->with('success', 'Account number deleted successfully');
    }
}
