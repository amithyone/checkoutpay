<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\BusinessWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountNumberController extends Controller
{
    /**
     * Display a listing of account numbers
     */
    public function index(Request $request)
    {
        $query = AccountNumber::with(['business', 'website']);

        // Filter by type (an account can be in multiple pools)
        if ($request->type === 'pool') {
            $query->where('is_pool', true);
        } elseif ($request->type === 'invoice_pool') {
            $query->where('is_invoice_pool', true);
        } elseif ($request->type === 'membership_pool') {
            $query->where('is_membership_pool', true);
        } elseif ($request->type === 'tickets_pool') {
            $query->where('is_tickets_pool', true);
        } elseif ($request->type === 'business') {
            $query->where('is_pool', false)->where('is_invoice_pool', false)->where('is_membership_pool', false)->where('is_tickets_pool', false);
        } elseif ($request->type === 'external') {
            $query->where('is_external', true);
        }

        // Filter by status
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        // Add payment statistics (if Payment model exists)
        try {
            $accountNumbers = $query->withCount([
                'payments as payments_received_count' => function ($q) {
                    if (class_exists(\App\Models\Payment::class)) {
                        $q->where('status', \App\Models\Payment::STATUS_APPROVED);
                    }
                }
            ])->withSum([
                'payments as payments_received_amount' => function ($q) {
                    if (class_exists(\App\Models\Payment::class)) {
                        $q->where('status', \App\Models\Payment::STATUS_APPROVED)
                          ->selectRaw('COALESCE(received_amount, amount)');
                    }
                }
            ], 'amount')
            ->latest()
            ->paginate(15);
        } catch (\Exception $e) {
            // Fallback if Payment model doesn't exist
            $accountNumbers = $query->latest()->paginate(15);
            foreach ($accountNumbers as $account) {
                $account->payments_received_count = 0;
                $account->payments_received_amount = 0;
            }
        }

        return view('admin.account-numbers.index', compact('accountNumbers'));
    }

    /**
     * Show the form for creating a new account number
     */
    public function create()
    {
        $businesses = Business::where('is_active', true)->orderBy('name')->get();
        $websites = BusinessWebsite::with('business')
            ->where('is_approved', true)
            ->orderBy('website_url')
            ->get();
        return view('admin.account-numbers.create', compact('businesses', 'websites'));
    }

    /**
     * Store a newly created account number
     */
    public function store(Request $request)
    {
        $isExternal = $request->boolean('is_external');
        $validated = $request->validate([
            'account_number' => ($isExternal ? 'nullable' : 'required') . '|string|size:10|unique:account_numbers,account_number',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:10',
            'business_id' => 'nullable|exists:businesses,id',
            'business_website_id' => 'nullable|exists:business_websites,id',
            'is_active' => 'boolean',
            'is_external' => 'boolean',
            'external_provider' => 'nullable|string|in:mevonpay,sla,mavonpay',
        ]);

        // Pool flags from checkboxes (multiple can be checked)
        $validated['is_pool'] = $request->boolean('is_pool');
        $validated['is_invoice_pool'] = $request->boolean('is_invoice_pool');
        $validated['is_membership_pool'] = $request->boolean('is_membership_pool');
        $validated['is_tickets_pool'] = $request->boolean('is_tickets_pool');
        $validated['is_external'] = $request->boolean('is_external');
        $validated['external_provider'] = $validated['is_external'] ? ($request->input('external_provider') ?: 'mevonpay') : null;
        $validated['account_number'] = $validated['is_external'] && empty($validated['account_number'])
            ? null
            : ($validated['account_number'] ?? null);

        if (!empty($validated['business_website_id'])) {
            $website = BusinessWebsite::find($validated['business_website_id']);
            if ($website) {
                $validated['business_id'] = $website->business_id;
            }
        }

        if ($validated['is_pool'] || $validated['is_invoice_pool'] || $validated['is_membership_pool'] || $validated['is_tickets_pool']) {
            $validated['business_id'] = null;
            $validated['business_website_id'] = null;
        } else {
            if (empty($validated['business_id'])) {
                return back()->withInput()->with('error', 'Select at least one pool (Pool, Invoice, Membership, or Tickets) or assign to a Business.');
            }
        }

        // Remove bank_code (not stored in DB)
        unset($validated['bank_code']);

        // Handle is_active checkbox
        $validated['is_active'] = $request->has('is_active') ? (bool)$request->input('is_active') : true;

        try {
            AccountNumber::create($validated);
            
            // Invalidate caches
            app(\App\Services\AccountNumberService::class)->invalidateAllCaches();
            return redirect()->route('admin.account-numbers.index')
                ->with('success', 'Account number created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating account number', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Failed to create account number: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing an account number
     */
    public function edit(AccountNumber $accountNumber)
    {
        $businesses = Business::where('is_active', true)->orderBy('name')->get();
        $websites = BusinessWebsite::with('business')
            ->where('is_approved', true)
            ->orderBy('website_url')
            ->get();
        return view('admin.account-numbers.edit', compact('accountNumber', 'businesses', 'websites'));
    }

    /**
     * Update the specified account number
     */
    public function update(Request $request, AccountNumber $accountNumber)
    {
        $isExternal = $request->boolean('is_external');
        $validated = $request->validate([
            'account_number' => ($isExternal ? 'nullable' : 'required') . '|string|size:10|unique:account_numbers,account_number,' . $accountNumber->id,
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:10',
            'business_id' => 'nullable|exists:businesses,id',
            'business_website_id' => 'nullable|exists:business_websites,id',
            'is_external' => 'boolean',
            'external_provider' => 'nullable|string|in:mevonpay,sla,mavonpay',
        ]);

        // Set business_id to null for any pool account
        if ($accountNumber->is_pool || $accountNumber->is_invoice_pool || $accountNumber->is_membership_pool || ($accountNumber->is_tickets_pool ?? false)) {
            $validated['business_id'] = null;
            $validated['business_website_id'] = null;
        }

        // Remove bank_code if not needed (we store bank_name)
        unset($validated['bank_code']);

        // Handle is_active checkbox - checkboxes only send value when checked
        $validated['is_active'] = $request->has('is_active') && ($request->input('is_active') == '1' || $request->input('is_active') === true || $request->input('is_active') === 'true');

        // Pool / Invoice / Membership / Tickets flags (checkboxes; multiple can be checked)
        $validated['is_pool'] = $request->has('is_pool') && ($request->input('is_pool') == '1' || $request->input('is_pool') === true);
        $validated['is_invoice_pool'] = $request->has('is_invoice_pool') && ($request->input('is_invoice_pool') == '1' || $request->input('is_invoice_pool') === true);
        $validated['is_membership_pool'] = $request->has('is_membership_pool') && ($request->input('is_membership_pool') == '1' || $request->input('is_membership_pool') === true);
        $validated['is_tickets_pool'] = $request->has('is_tickets_pool') && ($request->input('is_tickets_pool') == '1' || $request->input('is_tickets_pool') === true);
        $validated['is_external'] = $request->has('is_external') && ($request->input('is_external') == '1' || $request->input('is_external') === true);
        $validated['external_provider'] = $validated['is_external'] ? ($request->input('external_provider') ?: 'mevonpay') : null;
        $validated['account_number'] = $validated['is_external'] && empty($validated['account_number'])
            ? null
            : ($validated['account_number'] ?? null);

        if (!empty($validated['business_website_id'])) {
            $website = BusinessWebsite::find($validated['business_website_id']);
            if ($website) {
                $validated['business_id'] = $website->business_id;
            }
        }

        // If any pool is checked, clear business_id
        if ($validated['is_pool'] || $validated['is_invoice_pool'] || $validated['is_membership_pool'] || $validated['is_tickets_pool']) {
            $validated['business_id'] = null;
            $validated['business_website_id'] = null;
        }

        try {
            $accountNumber->update($validated);
            
            // Invalidate caches
            app(\App\Services\AccountNumberService::class)->invalidateAllCaches();
            
            return redirect()->route('admin.account-numbers.index')
                ->with('success', 'Account number updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating account number', [
                'error' => $e->getMessage(),
                'account_number_id' => $accountNumber->id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Failed to update account number: ' . $e->getMessage());
        }
    }

    /**
     * Update only pool flags and active status (for inline edit on index)
     */
    public function updateFlags(Request $request, AccountNumber $accountNumber)
    {
        $accountNumber->update([
            'is_pool' => $request->boolean('is_pool'),
            'is_invoice_pool' => $request->boolean('is_invoice_pool'),
            'is_membership_pool' => $request->boolean('is_membership_pool'),
            'is_tickets_pool' => $request->boolean('is_tickets_pool'),
            'is_active' => $request->boolean('is_active'),
        ]);

        // If any pool is checked, clear business_id
        if ($accountNumber->is_pool || $accountNumber->is_invoice_pool || $accountNumber->is_membership_pool || $accountNumber->is_tickets_pool) {
            $accountNumber->update(['business_id' => null, 'business_website_id' => null]);
        }

        app(\App\Services\AccountNumberService::class)->invalidateAllCaches();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Account flags updated.');
    }

    /**
     * Remove the specified account number
     */
    public function destroy(AccountNumber $accountNumber)
    {
        try {
            // Check if account number is being used by payments
            if ($accountNumber->payments()->count() > 0) {
                return back()->with('error', 'Cannot delete account number that has associated payments.');
            }

            $accountNumber->delete();
            return redirect()->route('admin.account-numbers.index')
                ->with('success', 'Account number deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting account number', [
                'error' => $e->getMessage(),
                'account_number_id' => $accountNumber->id,
            ]);
            return back()->with('error', 'Failed to delete account number: ' . $e->getMessage());
        }
    }

    /**
     * Validate account number using NUBAN API
     */
    public function validateAccount(Request $request)
    {
        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'bank_code' => 'nullable|string',
        ]);

        try {
            $nubanService = app(\App\Services\NubanValidationService::class);
            $mevonBankService = app(\App\Services\MevonPayBankService::class);

            $result = null;

            // Prefer MevonPay name enquiry first when bank code is present (single round-trip;
            // avoids long NUBAN chains when DNS to app.nuban.com.ng is slow).
            if (! empty($validated['bank_code']) && $mevonBankService->isConfigured()) {
                $mevon = $mevonBankService->nameEnquiry(
                    (string) $validated['bank_code'],
                    (string) $validated['account_number']
                );
                if ($mevon && ! empty($mevon['account_name'])) {
                    $result = [
                        'account_number' => (string) $validated['account_number'],
                        'account_name' => (string) $mevon['account_name'],
                        'bank_name' => $mevon['bank_name'] ?? null,
                        'bank_code' => (string) ($mevon['bank_code'] ?? $validated['bank_code']),
                        'valid' => true,
                    ];
                }
            }

            if (! $result || empty($result['account_name'])) {
                $result = $nubanService->validate(
                    $validated['account_number'],
                    $validated['bank_code'] ?? null
                );
            }

            if ($result && isset($result['account_name']) && !empty($result['account_name'])) {
                return response()->json([
                    'success' => true,
                    'valid' => true,
                    'account_name' => $result['account_name'],
                    'bank_name' => $result['bank_name'] ?? null,
                    'bank_code' => $result['bank_code'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Could not validate account number. Please verify the account number and bank are correct.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating account number', [
                'error' => $e->getMessage(),
                'account_number' => $validated['account_number'],
                'bank_code' => $validated['bank_code'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Error validating account number: ' . $e->getMessage(),
            ], 500);
        }
    }
}
