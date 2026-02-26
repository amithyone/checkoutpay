<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Services\NubanValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    /**
     * Show verification page
     */
    public function index()
    {
        $business = auth('business')->user();
        $verifications = $business->verifications()->latest()->get();

        return view('business.verification.index', compact('verifications'));
    }

    /**
     * Submit verification document
     */
    public function store(Request $request)
    {
        $business = auth('business')->user();

        $validated = $request->validate([
            'verification_type' => ['required', Rule::in([
                BusinessVerification::TYPE_BASIC,
                BusinessVerification::TYPE_BUSINESS_REGISTRATION,
                BusinessVerification::TYPE_BANK_ACCOUNT,
                BusinessVerification::TYPE_IDENTITY,
                BusinessVerification::TYPE_ADDRESS,
                BusinessVerification::TYPE_BVN,
                BusinessVerification::TYPE_NIN,
                BusinessVerification::TYPE_CAC_CERTIFICATE,
                BusinessVerification::TYPE_CAC_APPLICATION,
                BusinessVerification::TYPE_ACCOUNT_NUMBER,
                BusinessVerification::TYPE_BANK_ADDRESS,
                BusinessVerification::TYPE_UTILITY_BILL,
            ])],
            'document_type' => 'nullable|string|max:255',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            'account_number' => 'nullable|string|max:15',
            'bank_code' => 'nullable|string|max:20',
            'bvn' => 'nullable|string|max:11|min:11',
            'nin' => 'nullable|string|max:11|min:11',
        ]);

        // Handle text-based verifications (account_number + bank, BVN, NIN)
        $textBasedTypes = [
            BusinessVerification::TYPE_ACCOUNT_NUMBER,
            BusinessVerification::TYPE_BVN,
            BusinessVerification::TYPE_NIN,
        ];

        if (in_array($validated['verification_type'], $textBasedTypes)) {
            // Validate required fields for text-based types
            if ($validated['verification_type'] === BusinessVerification::TYPE_ACCOUNT_NUMBER) {
                if (empty($validated['account_number']) || empty($validated['bank_code'])) {
                    return back()->withErrors(['account_number' => 'Account number and bank are required.']);
                }
                $accountNumber = preg_replace('/[^0-9]/', '', $validated['account_number']);
                if (strlen($accountNumber) !== 10) {
                    return back()->withErrors(['account_number' => 'Account number must be 10 digits.']);
                }
                $nubanService = app(NubanValidationService::class);
                $nubanResult = $nubanService->validate($accountNumber, $validated['bank_code']);
                if (!$nubanResult || !($nubanResult['valid'] ?? false)) {
                    return back()->withErrors(['account_number' => 'Invalid account number or bank. We could not verify this account. Please check and try again.']);
                }
            }
            if ($validated['verification_type'] === BusinessVerification::TYPE_BVN && empty($validated['bvn'])) {
                return back()->withErrors(['bvn' => 'BVN is required.']);
            }
            if ($validated['verification_type'] === BusinessVerification::TYPE_NIN && empty($validated['nin'])) {
                return back()->withErrors(['nin' => 'NIN is required.']);
            }

            // Check if verification already exists for this type
            $existing = $business->verifications()
                ->where('verification_type', $validated['verification_type'])
                ->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW])
                ->first();

            if ($existing) {
                return back()->withErrors(['verification_type' => 'You already have a pending verification for this type.']);
            }

            // For account_number: save to business and set document_type with verified name
            if ($validated['verification_type'] === BusinessVerification::TYPE_ACCOUNT_NUMBER) {
                $accountNumber = preg_replace('/[^0-9]/', '', $validated['account_number']);
                $nubanService = app(NubanValidationService::class);
                $nubanResult = $nubanService->validate($accountNumber, $validated['bank_code']);
                $business->update([
                    'account_number' => $accountNumber,
                    'bank_code' => $nubanResult['bank_code'] ?? $validated['bank_code'],
                    'bank_name' => $nubanResult['bank_name'] ?? null,
                    'name' => $nubanResult['account_name'] ?? $business->name,
                ]);
                $documentType = sprintf(
                    'Account: %s, Bank: %s, Name: %s (NUBAN verified)',
                    $accountNumber,
                    $nubanResult['bank_name'] ?? $validated['bank_code'],
                    $nubanResult['account_name'] ?? ''
                );
            } elseif ($validated['verification_type'] === BusinessVerification::TYPE_BVN) {
                $documentType = 'BVN: ' . ($validated['bvn'] ?? '');
            } elseif ($validated['verification_type'] === BusinessVerification::TYPE_NIN) {
                $documentType = 'NIN: ' . ($validated['nin'] ?? '');
            } else {
                $documentType = $validated['document_type'] ?? '';
            }
            $path = null;
        } else {
            if (!$request->hasFile('document')) {
                return back()->withErrors(['document' => 'Document file is required for this verification type.']);
            }

            // Check if verification already exists for this type
            $existing = $business->verifications()
                ->where('verification_type', $validated['verification_type'])
                ->whereIn('status', [BusinessVerification::STATUS_PENDING, BusinessVerification::STATUS_UNDER_REVIEW])
                ->first();

            if ($existing) {
                return back()->withErrors(['document' => 'You already have a pending verification for this type.']);
            }

            // Store document
            $path = $request->file('document')->store('verifications/' . $business->id, 'public');
            $documentType = $validated['document_type'] ?? BusinessVerification::getTypeLabel($validated['verification_type']);
        }

        // Create verification record
        $verification = BusinessVerification::create([
            'business_id' => $business->id,
            'verification_type' => $validated['verification_type'],
            'document_type' => $documentType,
            'document_path' => $path,
            'status' => BusinessVerification::STATUS_PENDING,
        ]);

        return redirect()->route('business.verification.index')
            ->with('success', 'Verification document submitted successfully. Our team will review it shortly.');
    }

    /**
     * Download verification document
     */
    public function download(BusinessVerification $verification)
    {
        $business = auth('business')->user();

        // Check ownership
        if ($verification->business_id !== $business->id) {
            abort(403);
        }

        if (!$verification->document_path || !Storage::disk('public')->exists($verification->document_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($verification->document_path);
    }
}
