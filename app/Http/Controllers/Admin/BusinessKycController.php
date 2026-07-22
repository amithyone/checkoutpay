<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Services\Admin\BusinessKycMevonVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BusinessKycController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $requiredTypes = BusinessVerification::getRequiredTypes();

        $query = BusinessVerification::query()
            ->with(['business', 'reviewer'])
            ->whereIn('verification_type', $requiredTypes)
            ->orderByDesc('created_at');

        if ($status === 'approved') {
            $query->where('status', BusinessVerification::STATUS_APPROVED);
        } elseif ($status === 'rejected') {
            $query->where('status', BusinessVerification::STATUS_REJECTED);
        } elseif ($status === 'all') {
            // no status filter
        } else {
            $query->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW]);
            $status = 'pending';
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', (int) $request->query('business_id'));
        }

        if ($request->filled('type')) {
            $query->where('verification_type', (string) $request->query('type'));
        }

        $verifications = $query->paginate(24)->withQueryString();

        $verifications->getCollection()->transform(function (BusinessVerification $verification) {
            $verification->document_exists = $this->documentPathExists($verification->document_path);

            return $verification;
        });

        return view('admin.businesses-kyc.index', [
            'verifications' => $verifications,
            'status' => $status,
            'requiredTypes' => $requiredTypes,
            'canDecide' => (bool) auth('admin')->user()?->canDecideBusinessKyc(),
            'mevonVerifyAvailable' => app(BusinessKycMevonVerificationService::class)->isAvailable(),
        ]);
    }

    public function verifyIdentity(
        Request $request,
        BusinessVerification $verification,
        BusinessKycMevonVerificationService $mevonVerify,
    ): RedirectResponse {
        $admin = auth('admin')->user();
        if (! $admin?->canDecideBusinessKyc()) {
            abort(403, 'You cannot verify business KYC.');
        }

        if (! $verification->requiresMevonVerification()) {
            return back()->with('warning', 'This verification type does not require Mevon identity check.');
        }

        $request->validate([
            'signatory_dob' => 'nullable|date_format:Y-m-d|before:today',
            'return_to' => 'nullable|string|max:50',
        ]);

        if ($request->filled('signatory_dob') && $verification->business) {
            $verification->business->update([
                'rubies_signatory_dob' => $request->input('signatory_dob'),
            ]);
            $verification->load('business');
        }

        $result = $mevonVerify->verify(
            $verification,
            $admin,
            $request->input('signatory_dob'),
        );

        $redirect = $request->input('return_to') === 'kyc_queue'
            ? redirect()->route('admin.businesses-kyc.index', ['status' => 'pending'])
            : back();

        if ($result['ok']) {
            return $redirect->with('success', $result['message']);
        }

        return $redirect->with('warning', $result['message']);
    }

    public function document(BusinessVerification $verification): Response
    {
        $requiredTypes = BusinessVerification::getRequiredTypes();
        if (! in_array($verification->verification_type, $requiredTypes, true)) {
            abort(404);
        }

        if ($verification->isTextBased()) {
            abort(404, 'This verification type has no file document.');
        }

        $disk = $this->resolveDocumentDisk($verification->document_path);
        if ($disk === null) {
            abort(404, 'Document not found');
        }

        $path = $verification->document_path;
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $filename = basename($path);

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function documentPathExists(?string $path): bool
    {
        return $this->resolveDocumentDisk($path) !== null;
    }

    private function resolveDocumentDisk(?string $path): ?\Illuminate\Contracts\Filesystem\Filesystem
    {
        if (! $path) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public');
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local');
        }

        return null;
    }
}
