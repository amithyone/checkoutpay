<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
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
            'account_number' => 'nullable|string|max:255',
            'bank_address' => 'nullable|string|max:1000',
            'bvn' => 'nullable|string|max:11|min:11',
            'nin' => 'nullable|string|max:11|min:11',
        ]);

        // Handle text-based verifications (account_number, bank_address, BVN, NIN)
        $textBasedTypes = [
            BusinessVerification::TYPE_ACCOUNT_NUMBER,
            BusinessVerification::TYPE_BANK_ADDRESS,
            BusinessVerification::TYPE_BVN,
            BusinessVerification::TYPE_NIN,
        ];

        if (in_array($validated['verification_type'], $textBasedTypes)) {
            // Validate required fields for text-based types
            if ($validated['verification_type'] === BusinessVerification::TYPE_ACCOUNT_NUMBER && empty($validated['account_number'])) {
                return back()->withErrors(['account_number' => 'Account number is required.']);
            }
            if ($validated['verification_type'] === BusinessVerification::TYPE_BANK_ADDRESS && empty($validated['bank_address'])) {
                return back()->withErrors(['bank_address' => 'Bank address is required.']);
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

            // For text-based verifications, store in business model
            if ($validated['verification_type'] === BusinessVerification::TYPE_ACCOUNT_NUMBER && isset($validated['account_number'])) {
                $business->update(['account_number' => $validated['account_number']]);
            } elseif ($validated['verification_type'] === BusinessVerification::TYPE_BANK_ADDRESS && isset($validated['bank_address'])) {
                $business->update(['bank_address' => $validated['bank_address']]);
            }

            // Create verification record with text data
            $documentType = match($validated['verification_type']) {
                BusinessVerification::TYPE_ACCOUNT_NUMBER => 'Account Number: ' . ($validated['account_number'] ?? ''),
                BusinessVerification::TYPE_BANK_ADDRESS => 'Bank Address: ' . ($validated['bank_address'] ?? ''),
                BusinessVerification::TYPE_BVN => 'BVN: ' . ($validated['bvn'] ?? ''),
                BusinessVerification::TYPE_NIN => 'NIN: ' . ($validated['nin'] ?? ''),
                default => $validated['document_type'] ?? '',
            };
            $path = null;
        } else {
            // For file-based verifications
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
