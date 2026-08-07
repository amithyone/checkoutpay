<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CreditFacilityRequest;
use App\Services\Credit\CreditFacilityApprovalService;
use App\Services\Credit\OverdraftFundingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditFacilityApplicationsController extends Controller
{
    public function __construct(
        private CreditFacilityApprovalService $approvals,
        private OverdraftFundingService $funding,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = CreditFacilityRequest::query()
            ->with(['wallet', 'business', 'funder'])
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $applications = $query->paginate(25)->withQueryString();
        $masters = $this->funding->masterLoanAccounts();

        // Also show legacy pending overdrafts that never created a credit_facility_requests row.
        $legacyPending = collect();
        if ($status === 'pending') {
            $linkedIds = CreditFacilityRequest::query()
                ->where('kind', CreditFacilityRequest::KIND_OVERDRAFT)
                ->where('status', CreditFacilityRequest::STATUS_PENDING)
                ->whereNotNull('business_id')
                ->pluck('business_id');
            $legacyPending = Business::query()
                ->where('overdraft_status', 'pending')
                ->when($linkedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $linkedIds))
                ->orderByDesc('overdraft_requested_at')
                ->limit(50)
                ->get();
        }

        return view('admin.credit-facility-applications.index', [
            'applications' => $applications,
            'masters' => $masters,
            'status' => $status,
            'legacyPending' => $legacyPending,
        ]);
    }

    public function approve(Request $request, CreditFacilityRequest $creditFacilityRequest): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->isSuperAdmin()) {
            abort(403, 'Only super admins can approve credit facility requests.');
        }

        $validated = $request->validate([
            'funder_business_id' => ['required', 'integer', 'exists:businesses,id'],
            'approved_amount' => ['required', 'numeric', 'min:1'],
            'overdraft_limit' => ['nullable', 'numeric', 'min:1'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $funder = Business::query()->findOrFail((int) $validated['funder_business_id']);
        if (! $funder->is_master_loan_account && strcasecmp((string) $funder->email, $this->funding->capitalReserveEmail()) !== 0) {
            return back()->with('error', 'Select a master loan account (mark a business as master loan account, or use the capital reserve business).');
        }

        $result = $this->approvals->approve(
            $creditFacilityRequest,
            $funder,
            (float) $validated['approved_amount'],
            (int) $admin->id,
            $validated['admin_notes'] ?? null,
            isset($validated['overdraft_limit']) ? (float) $validated['overdraft_limit'] : null,
        );

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $request, CreditFacilityRequest $creditFacilityRequest): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->isSuperAdmin()) {
            abort(403, 'Only super admins can reject credit facility requests.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->approvals->reject(
            $creditFacilityRequest,
            (int) $admin->id,
            $validated['admin_notes'] ?? null,
        );

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
