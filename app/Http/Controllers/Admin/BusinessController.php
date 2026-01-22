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
        if ($website->business_id !== $business->id) {
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
            BusinessVerification::TYPE_BANK_ADDRESS,
            BusinessVerification::TYPE_BVN,
            BusinessVerification::TYPE_NIN,
        ];

        if (in_array($verification->verification_type, $textBasedTypes)) {
            $data = match($verification->verification_type) {
                BusinessVerification::TYPE_ACCOUNT_NUMBER => ['Account Number' => $business->account_number],
                BusinessVerification::TYPE_BANK_ADDRESS => ['Bank Address' => $business->bank_address],
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
}
