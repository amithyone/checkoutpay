<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessActivityLog;
use App\Models\BusinessVerification;
use App\Models\BusinessWebsite;
use App\Models\EmailAccount;
use App\Notifications\PeerLendingLenderProgramConfiguredNotification;
use App\Services\Credit\OverdraftFundingService;
use App\Services\Credit\OverdraftInstallmentService;
use App\Services\MevonRubiesVirtualAccountService;
use App\Services\TransactionLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $query = Business::withCount(['payments', 'withdrawalRequests', 'verifications'])
            ->with(['verifications' => function ($q) {
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
                $query->whereHas('verifications', function ($q) {
                    $q->where('status', BusinessVerification::STATUS_APPROVED);
                });
            } elseif ($request->kyc_status === 'pending') {
                $query->whereHas('verifications', function ($q) {
                    $q->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW]);
                });
            } elseif ($request->kyc_status === 'not_submitted') {
                $query->whereDoesntHave('verifications');
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
            'uses_external_account_numbers' => 'boolean',
            'whatsapp_wallet_api_enabled' => 'boolean',
        ]);
        $validated['uses_external_account_numbers'] = $request->has('uses_external_account_numbers');
        $validated['whatsapp_wallet_api_enabled'] = $request->has('whatsapp_wallet_api_enabled');

        Business::create($validated);

        return redirect()->route('admin.businesses.index')
            ->with('success', 'Business created successfully');
    }

    public function show(Business $business): View
    {
        $business->load([
            'payments' => function ($q) {
                $q->latest()->limit(10);
            },
            'withdrawalRequests' => function ($q) {
                $q->latest()->limit(10);
            },
            'accountNumbers',
            'verifications' => function ($q) {
                $q->latest();
            },
            'activityLogs' => function ($q) {
                $q->latest()->limit(10);
            },
            'websites',
        ]);

        $transferTargets = collect();
        $admin = auth('admin')->user();
        if ($admin && $admin->canUpdateBusinessBalance()) {
            $transferTargets = Business::query()
                ->where('id', '!=', $business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'balance']);
        }

        return view('admin.businesses.show', compact('business', 'transferTargets'));
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
            'email' => 'required|email|unique:businesses,email,'.$business->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:500',
            'webhook_url' => 'nullable|url',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'is_active' => 'boolean',
            'website_approved' => 'boolean',
            'balance' => 'nullable|numeric|min:0',
            'uses_external_account_numbers' => 'boolean',
            'whatsapp_wallet_api_enabled' => 'boolean',
        ]);
        $validated['uses_external_account_numbers'] = $request->has('uses_external_account_numbers');
        $validated['whatsapp_wallet_api_enabled'] = $request->has('whatsapp_wallet_api_enabled');

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

        if (! $request->bypass_kyc && (! $business->hasAllRequiredKycDocuments() || ! $allApproved)) {
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
            ->with('success', 'Website approval revoked. Reason: '.$request->rejection_reason);
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
        $websiteBusinessId = (int) $website->business_id;
        $businessId = (int) $business->id;

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
            'is_active' => ! $business->is_active,
        ]);

        $status = $business->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', "Business {$status} successfully");
    }

    public function toggleWhatsappWalletApi(Business $business): RedirectResponse
    {
        $business->update([
            'whatsapp_wallet_api_enabled' => ! $business->whatsapp_wallet_api_enabled,
        ]);

        $msg = $business->whatsapp_wallet_api_enabled
            ? 'WhatsApp wallet merchant API is now enabled for this business (X-API-Key: lookup, ensure, pay/start).'
            : 'WhatsApp wallet merchant API is now disabled for this business.';

        return redirect()->route('admin.businesses.show', $business)->with('success', $msg);
    }

    public function updateBalance(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();

        if (! $admin->canUpdateBusinessBalance()) {
            abort(403, 'Only super admins can update business balances.');
        }

        $request->validate([
            'balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldBalance = $business->balance;
        $business->update(['balance' => $request->balance]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Balance updated from ₦'.number_format($oldBalance, 2).' to ₦'.number_format($request->balance, 2));
    }

    public function transferBalance(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->canUpdateBusinessBalance()) {
            abort(403, 'Only super admins can transfer business balances.');
        }

        $validated = $request->validate([
            'target_business_id' => 'required|exists:businesses,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $targetBusinessId = (int) $validated['target_business_id'];
        $amount = round((float) $validated['amount'], 2);
        $notes = isset($validated['notes']) ? trim((string) $validated['notes']) : null;
        if ($notes === '') {
            $notes = null;
        }

        if ($targetBusinessId === (int) $business->id) {
            return back()->withErrors(['target_business_id' => 'Source and destination businesses must be different.']);
        }

        $businessIds = [(int) $business->id, $targetBusinessId];
        sort($businessIds, SORT_NUMERIC);

        $result = DB::transaction(function () use ($business, $targetBusinessId, $amount, $notes, $admin, $request, $businessIds) {
            $locked = [];
            foreach ($businessIds as $id) {
                $row = Business::query()->lockForUpdate()->find($id);
                if (! $row) {
                    throw new \RuntimeException('business_not_found');
                }
                $locked[$id] = $row;
            }

            /** @var Business $source */
            $source = $locked[(int) $business->id];
            /** @var Business $target */
            $target = $locked[$targetBusinessId];

            if ((float) $source->balance < $amount) {
                return ['ok' => false, 'error' => 'Insufficient source balance for this transfer.'];
            }

            $sourceOld = (float) $source->balance;
            $targetOld = (float) $target->balance;
            $sourceNew = round($sourceOld - $amount, 2);
            $targetNew = round($targetOld + $amount, 2);

            $source->balance = $sourceNew;
            $source->save();
            $target->balance = $targetNew;
            $target->save();

            $meta = [
                'amount' => $amount,
                'source_business_id' => $source->id,
                'source_business_name' => $source->name,
                'source_balance_before' => $sourceOld,
                'source_balance_after' => $sourceNew,
                'target_business_id' => $target->id,
                'target_business_name' => $target->name,
                'target_balance_before' => $targetOld,
                'target_balance_after' => $targetNew,
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'notes' => $notes,
            ];

            BusinessActivityLog::query()->create([
                'business_id' => $source->id,
                'action' => 'admin_balance_transfer_out',
                'description' => 'Admin transferred ₦'.number_format($amount, 2).' to '.$target->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $meta,
            ]);
            BusinessActivityLog::query()->create([
                'business_id' => $target->id,
                'action' => 'admin_balance_transfer_in',
                'description' => 'Admin credited ₦'.number_format($amount, 2).' from '.$source->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $meta,
            ]);

            Log::info('admin.business.balance_transfer', $meta);

            return ['ok' => true, 'target' => $target];
        });

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['amount' => $result['error'] ?? 'Transfer failed.']);
        }

        /** @var Business $target */
        $target = $result['target'];

        return redirect()
            ->route('admin.businesses.show', $business)
            ->with('success', 'Transferred ₦'.number_format($amount, 2).' from '.$business->name.' to '.$target->name.'.');
    }

    /** Overdraft limit tiers (in Naira). */
    public const OVERDRAFT_LIMITS = [200000, 500000, 1000000, 2000000, 5000000, 10000000];

    public function approveOverdraft(Request $request, Business $business, TransactionLogService $logService): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403, 'Only super admins can approve overdraft.');
        }
        $request->validate([
            'overdraft_limit' => 'required|numeric|in:'.implode(',', self::OVERDRAFT_LIMITS),
            'overdraft_funding_source' => 'required|string|in:platform,peer_pool,capital_reserve',
            'overdraft_approval_notes' => 'nullable|string|max:2000',
        ]);
        $limit = (float) $request->overdraft_limit;
        $fundingSource = (string) $request->overdraft_funding_source;

        $fundingService = app(OverdraftFundingService::class);
        if ($fundingSource === OverdraftFundingService::FUNDING_CAPITAL_RESERVE) {
            $funder = $fundingService->fundingBusiness(OverdraftFundingService::FUNDING_CAPITAL_RESERVE);
            if (! $funder) {
                return back()
                    ->withErrors(['overdraft_funding_source' => 'Capital reserve business ('.$fundingService->capitalReserveEmail().') was not found.'])
                    ->withInput();
            }
            if ($funder->id === $business->id) {
                return back()
                    ->withErrors(['overdraft_funding_source' => 'Capital reserve business cannot fund its own overdraft.'])
                    ->withInput();
            }
            if ($fundingService->availableCapacity(OverdraftFundingService::FUNDING_CAPITAL_RESERVE) < $limit) {
                return back()
                    ->withErrors(['overdraft_funding_source' => 'Capital reserve has insufficient balance to back this limit.'])
                    ->withInput();
            }
        }

        $business->update([
            'overdraft_limit' => $limit,
            'overdraft_approved_at' => now(),
            'overdraft_approved_by' => $admin->id,
            'overdraft_status' => 'approved',
            'overdraft_funding_source' => $fundingSource,
            'overdraft_approval_notes' => $request->overdraft_approval_notes,
        ]);
        $business->refresh();
        $logService->logOverdraftApproved($business, [
            'overdraft_limit' => $limit,
            'overdraft_funding_source' => $fundingSource,
            'overdraft_repayment_mode' => $business->overdraft_repayment_mode,
            'overdraft_approval_notes' => $request->overdraft_approval_notes,
            'admin_id' => $admin->id,
        ], $request);

        if ((float) $business->balance < 0) {
            if ($business->overdraft_repayment_mode === OverdraftInstallmentService::MODE_SPLIT_30D) {
                app(OverdraftInstallmentService::class)->startCycle($business->fresh());
            }
            if ($fundingSource === OverdraftFundingService::FUNDING_CAPITAL_RESERVE) {
                // Backfill funding for any existing negative balance at approval time.
                $fundingService->syncOnBalanceChange(
                    $business->fresh(),
                    0.0,
                    (float) $business->balance
                );
            }
        }

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Overdraft approved with limit ₦'.number_format($limit, 2));
    }

    public function rejectOverdraft(Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403, 'Only super admins can reject overdraft.');
        }
        $business->update([
            'overdraft_status' => 'rejected',
            'overdraft_requested_at' => $business->overdraft_requested_at, // keep for history
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Overdraft application rejected.');
    }

    public function updateCreditEligibility(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'peer_lending_lender_max_offer_amount' => 'nullable|numeric|min:0',
            'peer_lending_lender_max_interest_percent' => 'nullable|numeric|min:0|max:100',
            'peer_lending_lender_min_term_days' => 'nullable|integer|min:1|max:730',
            'peer_lending_lender_max_term_days' => 'nullable|integer|min:1|max:730',
            'peer_lending_lender_min_balance_reserve' => 'nullable|numeric|min:0',
            'peer_lending_lender_conditions' => 'nullable|string|max:10000',
        ]);

        $minTerm = $validated['peer_lending_lender_min_term_days'] ?? null;
        $maxTerm = $validated['peer_lending_lender_max_term_days'] ?? null;
        if ($minTerm !== null && $maxTerm !== null && $minTerm > $maxTerm) {
            return back()
                ->withErrors(['peer_lending_lender_max_term_days' => 'Max term must be greater than or equal to min term.'])
                ->withInput();
        }

        $wasLendEligible = (bool) $business->peer_lending_lend_eligible;

        $business->update([
            'overdraft_eligible' => $request->has('overdraft_eligible'),
            'peer_lending_lend_eligible' => $request->has('peer_lending_lend_eligible'),
            'peer_lending_borrow_eligible' => $request->has('peer_lending_borrow_eligible'),
            'peer_lending_lender_max_offer_amount' => $request->filled('peer_lending_lender_max_offer_amount')
                ? $validated['peer_lending_lender_max_offer_amount'] : null,
            'peer_lending_lender_max_interest_percent' => $request->filled('peer_lending_lender_max_interest_percent')
                ? $validated['peer_lending_lender_max_interest_percent'] : null,
            'peer_lending_lender_min_term_days' => $request->filled('peer_lending_lender_min_term_days')
                ? $validated['peer_lending_lender_min_term_days'] : null,
            'peer_lending_lender_max_term_days' => $request->filled('peer_lending_lender_max_term_days')
                ? $validated['peer_lending_lender_max_term_days'] : null,
            'peer_lending_lender_min_balance_reserve' => $request->filled('peer_lending_lender_min_balance_reserve')
                ? $validated['peer_lending_lender_min_balance_reserve'] : null,
            'peer_lending_lender_conditions' => $request->filled('peer_lending_lender_conditions')
                ? $validated['peer_lending_lender_conditions'] : null,
        ]);

        $business->refresh();
        $notify = $business->peer_lending_lend_eligible
            && ($request->has('notify_lender_program') || (! $wasLendEligible && $business->peer_lending_lend_eligible));
        if ($notify) {
            try {
                $business->notify(new PeerLendingLenderProgramConfiguredNotification($business));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Peer lending lender program email failed', [
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Credit programs and lender conditions updated.');
    }

    /**
     * Preview transactions for transfer (Super Admin only)
     */
    public function previewTransactions(Request $request, Business $business, ?BusinessWebsite $website = null): \Illuminate\Http\JsonResponse
    {
        $admin = auth('admin')->user();

        if (! $admin->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if transfer feature is enabled
        $enabled = \App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true));
        if (! $enabled) {
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
            $targetAmount = (float) $request->target_amount;
            $selectedTransactions = $this->findTransactionsForAmount($transactions, $targetAmount);
            $transactions = $selectedTransactions;
        } else {
            // Apply limit if specified
            $limit = $request->filled('limit') ? (int) $request->limit : 100;
            $transactions = $transactions->take($limit);
        }

        // Calculate total amount
        $totalAmount = $transactions->sum(function ($payment) {
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
        $items = $transactions->map(function ($payment) {
            return [
                'payment' => $payment,
                'amount' => (float) ($payment->business_receives ?? $payment->amount),
            ];
        })->values()->all();

        // Sort by amount descending
        usort($items, function ($a, $b) {
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
    public function transferTransactions(Request $request, Business $business, ?BusinessWebsite $website = null): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $admin = auth('admin')->user();

        if (! $admin->isSuperAdmin()) {
            abort(403, 'Only super admins can transfer transactions.');
        }

        // Check if transfer feature is enabled
        $enabled = \App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true));
        if (! $enabled) {
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

        if (empty($paymentIds) || ! is_array($paymentIds)) {
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

        $message = "{$transferred} transaction(s) transferred to super admin business. Total amount: ₦".number_format($totalAmount, 2);

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

        if (! $admin->isSuperAdmin()) {
            abort(403, 'Only super admins can toggle website charges.');
        }

        $website->update([
            'charges_enabled' => ! ($website->charges_enabled ?? true),
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
    public function approveVerification(Request $request, Business $business, BusinessVerification $verification, MevonRubiesVirtualAccountService $mevonRubies): RedirectResponse
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

        $business->refresh();

        $success = 'Verification approved successfully.';
        $warning = null;

        if ($business->hasAllKycDocumentsApproved() && empty($business->rubies_business_account_number)) {
            if (! $mevonRubies->isConfigured()) {
                Log::warning('business.rubies_business_va.skipped_not_configured', ['business_id' => $business->id]);
                $warning = 'Full KYC is approved, but Mevon Rubies is not configured — no business pay-in account was created.';
            } elseif (trim((string) $business->cac_registration_number) === '' || $business->rubies_signatory_dob === null) {
                $warning = 'Full KYC is approved, but CAC / RC number or signatory date of birth is missing. The merchant must submit CAC documents again with those fields, or you must set them before a Rubies pay-in account can be created.';
            } else {
                try {
                    $va = $mevonRubies->createRubiesBusinessAccountForBusiness($business);
                    $business->update([
                        'rubies_business_account_number' => $va['account_number'] ?? null,
                        'rubies_business_account_name' => $va['account_name'] ?? null,
                        'rubies_business_bank_name' => $va['bank_name'] ?? null,
                        'rubies_business_bank_code' => $va['bank_code'] ?? null,
                        'rubies_business_reference' => $va['reference'] ?? null,
                        'rubies_business_account_created_at' => now(),
                    ]);
                    $success .= ' Business pay-in bank account (Rubies) was created.';
                } catch (\Throwable $e) {
                    Log::warning('business.rubies_business_va.provision_failed', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage(),
                    ]);
                    $warning = 'Full KYC is approved, but creating the Rubies business pay-in account failed: '.$e->getMessage();
                }
            }
        }

        $redirect = redirect()->route('admin.businesses.show', $business)->with('success', $success);
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
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
            $data = match ($verification->verification_type) {
                BusinessVerification::TYPE_ACCOUNT_NUMBER => [
                    'Account Number' => $business->account_number,
                    'Bank' => $business->bank_name ?? $business->bank_code,
                ],
                default => ['Details' => $verification->document_type],
            };

            return response()->json($data);
        }

        // For file-based verifications
        if (! $verification->document_path || ! Storage::disk('public')->exists($verification->document_path)) {
            abort(404, 'Document not found');
        }

        return Storage::disk('public')->download($verification->document_path);
    }

    public function updateCharges(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();

        if (! $admin->canUpdateBusinessBalance()) {
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
            ->with('success', 'Charge settings updated successfully');
    }

    /**
     * Login as business (impersonation). Allowed for active admins except tax-only role.
     */
    public function loginAsBusiness(Request $request, Business $business): RedirectResponse
    {
        $admin = auth('admin')->user();

        if (! $admin || ! $admin->canImpersonateBusiness()) {
            abort(403, 'You are not allowed to view as this business.');
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
