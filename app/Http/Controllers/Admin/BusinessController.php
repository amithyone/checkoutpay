<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
            'activityLogs' => function($q) { $q->latest()->limit(10); }
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
            'notes' => 'nullable|string|max:1000',
        ]);

        $business->update([
            'website_approved' => true,
        ]);

        // Log activity if notes provided
        if ($request->notes) {
            // You can add activity logging here if needed
        }

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Website approved successfully. Business can now request account numbers.');
    }

    public function rejectWebsite(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $business->update([
            'website_approved' => false,
        ]);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Website approval revoked. Reason: ' . $request->rejection_reason);
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
        if (!$verification->document_path || !file_exists(storage_path('app/' . $verification->document_path))) {
            abort(404, 'Document not found');
        }

        return response()->download(storage_path('app/' . $verification->document_path));
    }
}
