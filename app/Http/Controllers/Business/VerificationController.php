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
            ])],
            'document_type' => 'required|string|max:255',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

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

        // Create verification record
        $verification = BusinessVerification::create([
            'business_id' => $business->id,
            'verification_type' => $validated['verification_type'],
            'document_type' => $validated['document_type'],
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
