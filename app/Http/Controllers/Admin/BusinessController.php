<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\BusinessWebsite;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $query = Business::withCount(['payments', 'withdrawalRequests', 'verifications'])
            ->with(['verifications' => function($q) {
                $q->latest()->limit(1);
            }])
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by website approval status
        if ($request->has('website_status')) {
            if ($request->website_status === 'approved') {
                $query->where('website_approved', true);
            } elseif ($request->website_status === 'pending') {
                $query->where('website_approved', false)->whereNotNull('website');
            } elseif ($request->website_status === 'none') {
                $query->whereNull('website');
            }
        }

        // Filter by KYC status
        if ($request->has('kyc_status')) {
            if ($request->kyc_status === 'verified') {
                $query->whereHas('verifications', function($q) {
                    $q->where('status', BusinessVerification::STATUS_APPROVED);
                });
            } elseif ($request->kyc_status === 'pending') {
                $query->whereHas('verifications', function($q) {
                    $q->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW]);
                });
            } elseif ($request->kyc_status === 'not_submitted') {
                $query->whereDoesntHave('verifications');
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('website', 'like', "%{$search}%");
            });
        }

        $businesses = $query->paginate(20)->withQueryString();

        return view('admin.businesses.index', compact('businesses'));
    }

    public function create(): View
    {
        $emailAccounts = EmailAccount::where('is_active', true)->get();
        return view('admin.businesses.create', compact('emailAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:500',
            'webhook_url' => 'nullable|url',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'is_active' => 'boolean',
            'website_approved' => 'boolean',
        ]);

        Business::create($validated);

        return redirect()->route('admin.businesses.index')
            ->with('success', 'Business created successfully');
    }

    public function show(Business $business): View
    {
        $business->load([
            'payments' => function($q) { $q->latest()->limit(10); },
            'withdrawalRequests' => function($q) { $q->latest()->limit(10); },
            'accountNumbers',
            'verifications' => function($q) { $q->latest(); },
            'activityLogs' => function($q) { $q->latest()->limit(10); },
            'websites'
        ]);
        
        return view('admin.businesses.show', compact('business'));
    }

    public function edit(Business $business): View
    {
        $emailAccounts = EmailAccount::where('is_active', true)->get();
        return view('admin.businesses.edit', compact('business', 'emailAccounts'));
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email,' . $business->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:500',
            'webhook_url' => 'nullable|url',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'is_active' => 'boolean',
            'website_approved' => 'boolean',
            'balance' => 'nullable|numeric|min:0',
        ]);

        $business->update($validated);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Business updated successfully');
    }

    public function regenerateApiKey(Business $business): RedirectResponse
    {
        $business->api_key = Business::generateApiKey();
        $business->save();

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'API key regenerated successfully');
    }

    public function approveWebsite(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'website_id' => 'required|exists:business_websites,id',
            'notes' => 'nullable|string|max:1000',
            'bypass_kyc' => 'nullable|boolean', // Allow bypassing KYC check
        ]);

        $website = BusinessWebsite::where('business_id', $business->id)
            ->findOrFail($request->website_id);

        // Check KYC status (warning but allow bypass)
        $missingDocs = $business->getMissingKycDocuments();
        $allApproved = $business->hasAllKycDocumentsApproved();
        
        if (!$request->bypass_kyc && (!$business->hasAllRequiredKycDocuments() || !$allApproved)) {
            return redirect()->route('admin.businesses.show', $business)
                ->with('warning', 'KYC verification is incomplete. Please verify all required documents before approving website, or use bypass option.');
        }

        $website->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => auth('admin')->id(),
            'notes' => $request->notes,
        ]);

        // Send notification to business
        $business->notify(new \App\Notifications\WebsiteApprovedNotification($website));

        $message = 'Website approved successfully.';
        if ($request->bypass_kyc) {
            $message .= ' (KYC bypassed)';
        }

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', $message);
    }

    public function rejectWebsite(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'website_id' => 'required|exists:business_websites,id',
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $website = BusinessWebsite::where('business_id', $business->id)
            ->findOrFail($request->website_id);

        $website->update([
            'is_approved' => false,
            'notes' => $request->rejection_reason,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Website approval revoked. Reason: ' . $request->rejection_reason);
    }

    public function addWebsite(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'website_url' => 'required|url|max:500',
            'webhook_url' => 'nullable|url|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $website = BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => $validated['website_url'],
            'webhook_url' => $validated['webhook_url'] ?? null,
            'is_approved' => false,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Send notification to business
        $business->notify(new \App\Notifications\WebsiteAddedNotification($website));

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Website added successfully. It requires approval.');
    }

    public function updateWebsite(Request $request, Business $business, BusinessWebsite $website): RedirectResponse
    {
        // Log for debugging
        \Log::info('Admin updateWebsite called', [
            'website_id' => $website->id,
            'website_business_id' => $website->business_id,
            'website_business_id_type' => gettype($website->business_id),
            'business_id' => $business->id,
            'business_id_type' => gettype($business->id),
        ]);

        // Use explicit type casting for comparison
        $websiteBusinessId = (int)$website->business_id;
        $businessId = (int)$business->id;

        \Log::info('Admin comparing business IDs', [
            'website_business_id' => $websiteBusinessId,
            'business_id' => $businessId,
            'match' => $websiteBusinessId === $businessId,
        ]);

        if ($websiteBusinessId !== $businessId) {
            \Log::error('Admin: Website ownership mismatch', [
                'website_id' => $website->id,
                'website_business_id' => $website->business_id,
                'website_business_id_int' => $websiteBusinessId,
                'business_id' => $business->id,
                'business_id_int' => $businessId,
            ]);
            abort(403, 'Website does not belong to this business.');
        }

        $validated = $request->validate([
            'website_url' => 'required|url|max:500',
            'webhook_url' => 'nullable|url|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $website->update([
            'website_url' => $validated['website_url'],
            'webhook_url' => $validated['webhook_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Website updated successfully.');
    }

    public function deleteWebsite(Request $request, Business $business, BusinessWebsite $website): RedirectResponse
    {
        if ($website->business_id !== $business->id) {
            abort(403, 'Website does not belong to this business.');
        }

        $website->delete();

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Website deleted successfully.');
    }

    public function toggleStatus(Business $business): RedirectResponse
    {
        $business->update([
            'is_active' => !$business->is_active,
        ]);

        $status = $business->is_active ? 'activated' : 'deactivated';
        return redirect()->route('admin.businesses.show', $business)
            ->with('success', "Business {$status} successfully");
    }

    public function updateBalance(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        
        if (!$admin->canUpdateBusinessBalance()) {
            abort(403, 'Only super admins can update business balances.');
        }

        $request->validate([
            'balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldBalance = $business->balance;
        $business->update(['balance' => $request->balance]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', "Balance updated from ₦" . number_format($oldBalance, 2) . " to ₦" . number_format($request->balance, 2));
    }

    /** Overdraft limit tiers (in Naira). */
    public const OVERDRAFT_LIMITS = [200000, 500000, 1000000];

    public function approveOverdraft(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin->isSuperAdmin()) {
            abort(403, 'Only super admins can approve overdraft.');
        }
        $request->validate([
            'overdraft_limit' => 'required|numeric|in:' . implode(',', self::OVERDRAFT_LIMITS),
        ]);
        $limit = (float) $request->overdraft_limit;
        $business->update([
            'overdraft_limit' => $limit,
            'overdraft_approved_at' => now(),
            'overdraft_approved_by' => $admin->id,
            'overdraft_status' => 'approved',
        ]);
        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Overdraft approved with limit ₦' . number_format($limit, 2));
    }

    public function rejectOverdraft(Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin->isSuperAdmin()) {
            abort(403, 'Only super admins can reject overdraft.');
        }
        $business->update([
            'overdraft_status' => 'rejected',
            'overdraft_requested_at' => $business->overdraft_requested_at, // keep for history
        ]);
        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Overdraft application rejected.');
    }

    /**
     * Preview transactions for transfer (Super Admin only)
     */
    public function previewTransactions(Request $request, Business $business, BusinessWebsite $website = null): \Illuminate\Http\JsonResponse
    {
        $admin = auth('admin')->user();
        
        if (!$admin->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if transfer feature is enabled
        $enabled = \App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true));
        if (!$enabled) {
            return response()->json(['error' => 'Transaction transfer feature is disabled'], 403);
        }

        $query = \App\Models\Payment::where('business_id', $business->id);
        
        if ($website) {
            $query->where('business_website_id', $website->id);
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get(['id', 'transaction_id', 'amount', 'business_receives', 'status', 'created_at']);

        // If target_amount is specified, find transactions that sum to that amount
        if ($request->filled('target_amount')) {
            $targetAmount = (float)$request->target_amount;
            $selectedTransactions = $this->findTransactionsForAmount($transactions, $targetAmount);
            $transactions = $selectedTransactions;
        } else {
            // Apply limit if specified
            $limit = $request->filled('limit') ? (int)$request->limit : 100;
            $transactions = $transactions->take($limit);
        }

        // Calculate total amount
        $totalAmount = $transactions->sum(function($payment) {
            return $payment->business_receives ?? $payment->amount;
        });

        return response()->json([
            'transactions' => $transactions,
            'count' => $transactions->count(),
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Find transactions that sum to target amount (or close to it)
     * Uses an optimized greedy algorithm with multiple passes
     */
    private function findTransactionsForAmount($transactions, float $targetAmount): \Illuminate\Support\Collection
    {
        if ($transactions->isEmpty()) {
            return collect();
        }

        // Convert to array with amounts
        $items = $transactions->map(function($payment) {
            return [
                'payment' => $payment,
                'amount' => (float)($payment->business_receives ?? $payment->amount),
            ];
        })->values()->all();

        // Sort by amount descending
        usort($items, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        $bestSelection = [];
        $bestSum = 0;
        $bestDiff = PHP_FLOAT_MAX;

        // Try multiple strategies
        // Strategy 1: Greedy from largest
        $this->tryGreedyStrategy($items, $targetAmount, $bestSelection, $bestSum, $bestDiff);
        
        // Strategy 2: Greedy from smallest (for cases with many small transactions)
        $itemsReversed = array_reverse($items);
        $this->tryGreedyStrategy($itemsReversed, $targetAmount, $bestSelection, $bestSum, $bestDiff);

        // Strategy 3: Try to get as close as possible without exceeding
        $this->tryClosestUnderStrategy($items, $targetAmount, $bestSelection, $bestSum, $bestDiff);

        return collect($bestSelection);
    }

    /**
     * Greedy strategy: add transactions until close to target
     */
    private function tryGreedyStrategy($items, $targetAmount, &$bestSelection, &$bestSum, &$bestDiff): void
    {
        $selection = [];
        $sum = 0;

        foreach ($items as $item) {
            $newSum = $sum + $item['amount'];
            $newDiff = abs($targetAmount - $newSum);

            // If adding gets us closer or we're still under target
            if ($newDiff < abs($targetAmount - $sum) || $newSum <= $targetAmount * 1.15) {
                $selection[] = $item['payment'];
                $sum = $newSum;

                if ($newDiff < $bestDiff) {
                    $bestDiff = $newDiff;
                    $bestSum = $sum;
                    $bestSelection = $selection;
                }

                // Stop if we're very close
                if ($newDiff < 0.01) {
                    break;
                }
            }
        }
    }

    /**
     * Strategy: Get as close as possible without exceeding target by much
     */
    private function tryClosestUnderStrategy($items, $targetAmount, &$bestSelection, &$bestSum, &$bestDiff): void
    {
        $selection = [];
        $sum = 0;
        $maxOver = $targetAmount * 0.1; // Allow 10% over

        foreach ($items as $item) {
            $newSum = $sum + $item['amount'];
            
            // Only add if we don't exceed too much
            if ($newSum <= $targetAmount + $maxOver) {
                $selection[] = $item['payment'];
                $sum = $newSum;
                $diff = abs($targetAmount - $sum);

                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestSum = $sum;
                    $bestSelection = $selection;
                }

                // Perfect match
                if ($diff < 0.01) {
                    break;
                }
            }
        }
    }

    /**
     * Transfer transactions from business/website to super admin business (Super Admin only)
     */
    public function transferTransactions(Request $request, Business $business, BusinessWebsite $website = null): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $admin = auth('admin')->user();
        
        if (!$admin->isSuperAdmin()) {
            abort(403, 'Only super admins can transfer transactions.');
        }

        // Check if transfer feature is enabled
        $enabled = \App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true));
        if (!$enabled) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Transaction transfer feature is disabled'], 403);
            }
            abort(403, 'Transaction transfer feature is disabled');
        }

        // Handle JSON payment_ids from AJAX
        $paymentIds = $request->payment_ids;
        if (is_string($paymentIds)) {
            $paymentIds = json_decode($paymentIds, true);
        }

        if (empty($paymentIds) || !is_array($paymentIds)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'No transactions selected'], 400);
            }
            return redirect()->route('admin.businesses.show', $business)
                ->with('error', 'No transactions selected');
        }

        $superAdminBusiness = \App\Services\SuperAdminBusinessService::getOrCreateSuperAdminBusiness();
        $superAdminWebsite = \App\Services\SuperAdminBusinessService::getSuperAdminWebsite();

        $transferred = 0;
        $totalAmount = 0;

        foreach ($paymentIds as $paymentId) {
            $payment = \App\Models\Payment::find($paymentId);
            
            // Verify payment belongs to this business/website
            if ($payment && $payment->business_id == $business->id) {
                if ($website && $payment->business_website_id != $website->id) {
                    continue; // Skip if website filter is set and doesn't match
                }

                $originalBusinessId = $payment->business_id;
                $originalWebsiteId = $payment->business_website_id;
                $amount = $payment->business_receives ?? $payment->amount;

                // Transfer to super admin business
                $payment->business_id = $superAdminBusiness->id;
                $payment->business_website_id = $superAdminWebsite ? $superAdminWebsite->id : null;
                $payment->save();

                // Update balances
                if ($payment->status === \App\Models\Payment::STATUS_APPROVED) {
                    // Remove from original business balance
                    $originalBusiness = \App\Models\Business::find($originalBusinessId);
                    if ($originalBusiness) {
                        $originalBusiness->decrement('balance', $amount);
                    }

                    // Add to super admin business balance
                    $superAdminBusiness->increment('balance', $amount);
                }

                $transferred++;
                $totalAmount += $amount;

                \Illuminate\Support\Facades\Log::info('Transaction transferred to super admin', [
                    'payment_id' => $payment->id,
                    'original_business_id' => $originalBusinessId,
                    'original_website_id' => $originalWebsiteId,
                    'super_admin_business_id' => $superAdminBusiness->id,
                    'amount' => $amount,
                    'admin_id' => $admin->id,
                ]);
            }
        }

        // Recalculate revenue for both businesses
        $revenueService = app(\App\Services\RevenueService::class);
        $revenueService->recalculateBusinessRevenueFromWebsites($business);
        $revenueService->recalculateBusinessRevenueFromWebsites($superAdminBusiness);

        $message = "{$transferred} transaction(s) transferred to super admin business. Total amount: ₦" . number_format($totalAmount, 2);
        
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', $message);
    }

    /**
     * Toggle charges enabled/disabled for website (Super Admin only)
     */
    public function toggleWebsiteCharges(Business $business, BusinessWebsite $website): RedirectResponse
    {
        $admin = auth('admin')->user();
        
        if (!$admin->isSuperAdmin()) {
            abort(403, 'Only super admins can toggle website charges.');
        }

        $website->update([
            'charges_enabled' => !($website->charges_enabled ?? true),
        ]);

        $status = $website->charges_enabled ? 'enabled' : 'disabled';
        
        \Illuminate\Support\Facades\Log::info('Website charges toggled by admin', [
            'website_id' => $website->id,
            'charges_enabled' => $website->charges_enabled,
            'admin_id' => $admin->id,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', "Charges {$status} for website successfully");
    }

    // KYC Management Methods
    public function approveVerification(Request $request, Business $business, BusinessVerification $verification): RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $verification->update([
            'status' => BusinessVerification::STATUS_APPROVED,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
            'admin_notes' => $request->admin_notes,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Verification approved successfully');
    }

    public function rejectVerification(Request $request, Business $business, BusinessVerification $verification): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $verification->update([
            'status' => BusinessVerification::STATUS_REJECTED,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Verification rejected');
    }

    public function downloadVerificationDocument(Business $business, BusinessVerification $verification)
    {
        // Check if verification belongs to this business
        if ($verification->business_id !== $business->id) {
            abort(403, 'Verification does not belong to this business.');
        }

        // For text-based verifications, return business data
        $textBasedTypes = [
            BusinessVerification::TYPE_ACCOUNT_NUMBER,
            BusinessVerification::TYPE_BVN,
            BusinessVerification::TYPE_NIN,
        ];

        if (in_array($verification->verification_type, $textBasedTypes)) {
            $data = match($verification->verification_type) {
                BusinessVerification::TYPE_ACCOUNT_NUMBER => [
                    'Account Number' => $business->account_number,
                    'Bank' => $business->bank_name ?? $business->bank_code,
                ],
                default => ['Details' => $verification->document_type],
            };
            return response()->json($data);
        }

        // For file-based verifications
        if (!$verification->document_path || !Storage::disk('public')->exists($verification->document_path)) {
            abort(404, 'Document not found');
        }

        return Storage::disk('public')->download($verification->document_path);
    }

    public function updateCharges(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        
        if (!$admin->canUpdateBusinessBalance()) {
            abort(403, 'Only super admins can update business charges.');
        }

        $request->validate([
            'charge_percentage' => 'nullable|numeric|min:0|max:100',
            'charge_fixed' => 'nullable|numeric|min:0',
            'charge_exempt' => 'nullable|boolean',
            'charges_paid_by_customer' => 'nullable|boolean',
        ]);

        $business->update([
            'charge_percentage' => $request->filled('charge_percentage') ? $request->charge_percentage : null,
            'charge_fixed' => $request->filled('charge_fixed') ? $request->charge_fixed : null,
            'charge_exempt' => $request->has('charge_exempt') ? (bool) $request->charge_exempt : false,
            'charges_paid_by_customer' => $request->has('charges_paid_by_customer') ? (bool) $request->charges_paid_by_customer : false,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', "Charge settings updated successfully");
    }

    /**
     * Login as business (impersonation) - Super Admin only
     */
    public function loginAsBusiness(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        
        if (!$admin || !$admin->isSuperAdmin()) {
            abort(403, 'Only super admins can impersonate businesses.');
        }

        // Store impersonation data in session
        $request->session()->put('admin_impersonating_business_id', $business->id);
        $request->session()->put('admin_impersonating_admin_id', $admin->id);
        
        // Actually log in as the business
        Auth::guard('business')->login($business);
        
        // Save session immediately to ensure it's persisted
        $request->session()->save();

        // Send admin login notification to business
        $business->notify(new \App\Notifications\AdminLoginNotification(
            $admin->name,
            $admin->email,
            $request->ip(),
            $request->userAgent() ?? 'Unknown'
        ));

        // Log the impersonation
        \Illuminate\Support\Facades\Log::info('Admin impersonating business', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'business_id' => $business->id,
            'business_email' => $business->email,
        ]);

        return redirect()->route('business.dashboard')
            ->with('success', "You are now viewing as {$business->name}");
    }

    /**
     * Exit business impersonation
     */
    public function exitImpersonation(Request $request): RedirectResponse
    {
        $businessId = $request->session()->get('admin_impersonating_business_id');
        
        // Clear impersonation session
        $request->session()->forget(['admin_impersonating_business_id', 'admin_impersonating_admin_id']);
        
        // Logout from business guard
        Auth::guard('business')->logout();
        
        // Log the exit
        if ($businessId) {
            \Illuminate\Support\Facades\Log::info('Admin exited business impersonation', [
                'admin_id' => auth('admin')->id(),
                'business_id' => $businessId,
            ]);
        }

        return redirect()->route('admin.businesses.index')
            ->with('success', 'Exited business view successfully');
    }
}
