<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessNameRegistration;
use App\Services\Consumer\BusinessNameRegistrationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessNameRegistrationAdminController extends Controller
{
    public function __construct(
        private BusinessNameRegistrationWorkflowService $workflow,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        $query = BusinessNameRegistration::query()
            ->with(['wallet:id,phone_e164,sender_name,balance,business_pay_in_account_number'])
            ->orderByDesc('id');

        if ($status === 'pending') {
            $query->pendingReview();
        } elseif ($status !== 'all') {
            $allowed = [
                BusinessNameRegistration::STATUS_PAID,
                BusinessNameRegistration::STATUS_PROCESSING,
                BusinessNameRegistration::STATUS_UNDER_REVIEW,
                BusinessNameRegistration::STATUS_APPROVED,
                BusinessNameRegistration::STATUS_REJECTED,
            ];
            if (in_array($status, $allowed, true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('proposed_name', 'like', '%'.$search.'%')
                    ->orWhere('alternate_name', 'like', '%'.$search.'%')
                    ->orWhere('public_id', 'like', '%'.$search.'%')
                    ->orWhere('owner_full_name', 'like', '%'.$search.'%')
                    ->orWhere('owner_email', 'like', '%'.$search.'%')
                    ->orWhereHas('wallet', function ($w) use ($search): void {
                        $w->where('phone_e164', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('wallet_id') && is_numeric($request->query('wallet_id'))) {
            $query->where('whatsapp_wallet_id', (int) $request->query('wallet_id'));
        }

        return view('admin.business-name-registrations.index', [
            'registrations' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'pendingCount' => BusinessNameRegistration::countPending(),
        ]);
    }

    public function show(BusinessNameRegistration $registration): View
    {
        $registration->load(['wallet', 'feeTransaction']);

        return view('admin.business-name-registrations.show', [
            'registration' => $registration,
        ]);
    }

    public function idDocument(BusinessNameRegistration $registration): StreamedResponse
    {
        $path = trim((string) $registration->id_document_path);
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404, 'ID document not found.');
        }

        return Storage::disk('local')->response($path);
    }

    public function updateStatus(Request $request, BusinessNameRegistration $registration): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:paid,processing,under_review,approved,rejected',
            'status_label' => 'nullable|string|max:120',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'rejected_reason' => 'nullable|string|max:500',
            'approved_business_name' => 'nullable|string|max:200',
            'business_account_number' => 'nullable|string|max:32',
            'business_account_name' => 'nullable|string|max:200',
            'business_bank_name' => 'nullable|string|max:120',
            'business_bank_code' => 'nullable|string|max:16',
        ]);

        if ($validated['status'] === BusinessNameRegistration::STATUS_APPROVED) {
            $request->validate([
                'business_account_number' => 'required|string|max:32',
                'approved_business_name' => 'required|string|max:200',
            ]);
        }

        if ($validated['status'] === BusinessNameRegistration::STATUS_REJECTED) {
            $request->validate([
                'rejected_reason' => 'required|string|max:500',
            ]);
        }

        $result = $this->workflow->updateStatus(
            $registration,
            (string) $validated['status'],
            $validated,
        );

        if (! ($result['ok'] ?? false)) {
            return back()->withInput()->with('error', $result['message'] ?? 'Update failed.');
        }

        return redirect()
            ->route('admin.business-name-registrations.show', $registration)
            ->with('success', $result['message'] ?? 'Registration updated.');
    }
}
