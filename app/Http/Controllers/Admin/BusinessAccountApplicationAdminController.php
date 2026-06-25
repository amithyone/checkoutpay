<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessAccountApplication;
use App\Services\Consumer\BusinessAccountOnboardingWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessAccountApplicationAdminController extends Controller
{
    public function __construct(
        private BusinessAccountOnboardingWorkflowService $workflow,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        $query = BusinessAccountApplication::query()
            ->with(['wallet:id,phone_e164,sender_name,balance,linked_business_id', 'linkedBusiness:id,name,email'])
            ->orderByDesc('id');

        if ($status === 'pending') {
            $query->pendingReview();
        } elseif ($status !== 'all') {
            $allowed = [
                BusinessAccountApplication::STATUS_SUBMITTED,
                BusinessAccountApplication::STATUS_UNDER_REVIEW,
                BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
                BusinessAccountApplication::STATUS_ACTIVE,
                BusinessAccountApplication::STATUS_REJECTED,
            ];
            if (in_array($status, $allowed, true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('business_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('public_id', 'like', '%'.$search.'%')
                    ->orWhereHas('wallet', function ($w) use ($search): void {
                        $w->where('phone_e164', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('wallet_id') && is_numeric($request->query('wallet_id'))) {
            $query->where('whatsapp_wallet_id', (int) $request->query('wallet_id'));
        }

        return view('admin.business-account-applications.index', [
            'applications' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'pendingCount' => BusinessAccountApplication::countPending(),
        ]);
    }

    public function show(BusinessAccountApplication $application): View
    {
        $application->load(['wallet', 'linkedBusiness', 'feeTransaction']);

        return view('admin.business-account-applications.show', [
            'application' => $application,
        ]);
    }

    public function cacDocument(BusinessAccountApplication $application): StreamedResponse
    {
        $path = trim((string) $application->cac_document_path);
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404, 'CAC document not found.');
        }

        return Storage::disk('local')->response($path);
    }

    public function updateStatus(Request $request, BusinessAccountApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:under_review,approved,awaiting_password,rejected',
            'status_label' => 'nullable|string|max:120',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'rejected_reason' => 'nullable|string|max:500',
        ]);

        if (in_array($validated['status'], ['approved', 'awaiting_password'], true)) {
            $validated['status'] = BusinessAccountApplication::STATUS_AWAITING_PASSWORD;
        }

        if ($validated['status'] === BusinessAccountApplication::STATUS_REJECTED) {
            $request->validate(['rejected_reason' => 'required|string|max:500']);
        }

        $result = $this->workflow->updateStatus(
            $application,
            (string) $validated['status'],
            $validated,
        );

        if (! ($result['ok'] ?? false)) {
            return back()->withInput()->with('error', $result['message'] ?? 'Update failed.');
        }

        return redirect()
            ->route('admin.business-account-applications.show', $application)
            ->with('success', $result['message'] ?? 'Application updated.');
    }
}
