<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Services\BusinessRubiesKycAutoVerificationService;
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
     * Save pay-in profile fields and request permanent business pay-in account (independent of KYC document submission).
     */
    public function requestPermanentAccount(Request $request)
    {
        $business = auth('business')->user();

        $validated = $request->validate([
            'cac_registration_number' => 'required|string|max:100',
            'business_phone' => 'required|string|max:30',
            'business_email' => 'required|email|max:255',
            'signatory_dob' => 'required|date_format:Y-m-d|before:today',
            'legal_name' => 'nullable|string|max:255',
        ]);

        try {
            $this->normalizeNigerianPhoneToLocal11((string) $validated['business_phone']);
        } catch (\Throwable) {
            return back()->withErrors([
                'business_phone' => 'Enter a valid Nigerian mobile number (e.g. 08012345678 or +2348012345678).',
            ])->withInput();
        }

        $this->applyKycProfileFields($business, $validated);

        $business->refresh();
        $auto = app(BusinessRubiesKycAutoVerificationService::class)->attemptIndependentPermanentAccount($business);

        if ($auto['verified'] && $auto['attempted']) {
            return redirect()->route('business.verification.index')
                ->with('success', 'Your permanent business pay-in account is ready. Details are shown below.');
        }

        if ($auto['skipped'] && $auto['message'] === '') {
            return redirect()->route('business.verification.index')
                ->with('info', 'Your permanent pay-in account is already set up.');
        }

        if ($auto['skipped'] && $auto['message'] === 'pay_in_unavailable') {
            return redirect()->route('business.verification.index')
                ->with('warning', $this->formatPayInUserMessage('pay_in_unavailable'));
        }

        if ($auto['attempted'] && ! $auto['skipped'] && $auto['message'] !== '') {
            return redirect()->route('business.verification.index')
                ->with('warning', $this->formatPayInUserMessage($auto['message']));
        }

        return redirect()->route('business.verification.index')
            ->with('info', 'Your details were saved. You can try again in a moment.');
    }

    /**
     * Submit verification document
     */
    public function store(Request $request)
    {
        $business = auth('business')->user();

        $typeIn = (string) $request->input('verification_type', '');

        $rules = [
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
            'business_phone' => 'nullable|string|max:30',
            'legal_name' => 'nullable|string|max:255',
            'business_email' => 'nullable|email|max:255',
            'cac_registration_number' => 'nullable|string|max:100',
            'signatory_dob' => 'nullable|date_format:Y-m-d|before:today',
        ];

        if (in_array($typeIn, [BusinessVerification::TYPE_BVN, BusinessVerification::TYPE_NIN], true)) {
            $rules['business_phone'] = 'required|string|max:30';
            $rules['legal_name'] = 'required|string|max:255';
        }

        if (in_array($typeIn, [BusinessVerification::TYPE_CAC_CERTIFICATE, BusinessVerification::TYPE_CAC_APPLICATION], true)) {
            $rules['cac_registration_number'] = 'required|string|max:100';
            $rules['signatory_dob'] = 'required|date_format:Y-m-d|before:today';
            $rules['business_phone'] = 'required|string|max:30';
            $rules['business_email'] = 'required|email|max:255';
        }

        $validated = $request->validate($rules);

        if (! empty($validated['business_phone'])) {
            try {
                $this->normalizeNigerianPhoneToLocal11((string) $validated['business_phone']);
            } catch (\Throwable) {
                return back()->withErrors([
                    'business_phone' => 'Enter a valid Nigerian mobile number (e.g. 08012345678 or +2348012345678).',
                ])->withInput();
            }
        }

        $this->applyKycProfileFields($business, $validated);

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
                if (! $nubanResult || ! ($nubanResult['valid'] ?? false)) {
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
                $documentType = 'BVN: '.($validated['bvn'] ?? '');
            } elseif ($validated['verification_type'] === BusinessVerification::TYPE_NIN) {
                $documentType = 'NIN: '.($validated['nin'] ?? '');
            } else {
                $documentType = $validated['document_type'] ?? '';
            }
            $path = null;
        } else {
            if (! $request->hasFile('document')) {
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
            $path = $request->file('document')->store('verifications/'.$business->id, 'public');
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

        $business->refresh();
        $auto = app(BusinessRubiesKycAutoVerificationService::class)->attemptAfterSubmission($business);

        if ($auto['verified'] && $auto['attempted']) {
            return redirect()->route('business.verification.index')
                ->with('success', 'Verification complete. Your permanent business pay-in account is active — details are shown below.');
        }

        if ($auto['attempted'] && ! $auto['skipped'] && $auto['message'] !== '') {
            return redirect()->route('business.verification.index')
                ->with('warning',
                    'All KYC items are submitted, but we could not finish pay-in account setup yet: '
                    .$this->formatPayInUserMessage($auto['message'])
                    .' Your submissions are saved; fix the issue or contact support if it persists.'
                );
        }

        if ($auto['skipped'] && $auto['message'] === 'pay_in_unavailable') {
            return redirect()->route('business.verification.index')
                ->with('warning', $this->formatPayInUserMessage('pay_in_unavailable'));
        }

        $success = 'Verification document submitted successfully.';
        $business->refresh();
        if (! $business->hasAllRequiredKycDocuments()) {
            $success .= ' Submit the remaining required items to finish.';
        }

        return redirect()->route('business.verification.index')->with('success', $success);
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

        if (! $verification->document_path || ! Storage::disk('public')->exists($verification->document_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($verification->document_path);
    }

    /**
     * Persist profile fields used for pay-in account and KYC.
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyKycProfileFields(\App\Models\Business $business, array $validated): void
    {
        $updates = [];

        if (! empty($validated['business_phone'])) {
            $updates['phone'] = $this->normalizeNigerianPhoneToLocal11((string) $validated['business_phone']);
        }

        if (array_key_exists('cac_registration_number', $validated) && trim((string) $validated['cac_registration_number']) !== '') {
            $updates['cac_registration_number'] = strtoupper(trim((string) $validated['cac_registration_number']));
        }

        if (! empty($validated['signatory_dob'])) {
            $updates['rubies_signatory_dob'] = $validated['signatory_dob'];
        }

        if (array_key_exists('legal_name', $validated) && trim((string) $validated['legal_name']) !== '') {
            $updates['name'] = trim((string) $validated['legal_name']);
        }

        if (! empty($validated['business_email'])) {
            $updates['email'] = strtolower(trim((string) $validated['business_email']));
        }

        if ($updates !== []) {
            $business->update($updates);
        }
    }

    private function formatPayInUserMessage(string $message): string
    {
        return match ($message) {
            'pay_in_unavailable' => 'Pay-in account setup is temporarily unavailable. Try again later or contact support.',
            'cac_dob_required' => 'Enter your CAC / RC number and signatory date of birth in the pay-in section.',
            default => $message,
        };
    }

    /**
     * Nigerian mobile normalization consistent with pay-in provisioning.
     */
    private function normalizeNigerianPhoneToLocal11(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if ($d === '') {
            throw new \InvalidArgumentException('Invalid phone number.');
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return $d;
        }
        if (strlen($d) === 13 && str_starts_with($d, '234')) {
            return '0'.substr($d, 3);
        }
        if (strlen($d) === 10 && $d[0] !== '0') {
            return '0'.$d;
        }

        throw new \InvalidArgumentException('Phone must be a valid Nigerian mobile (e.g. 080… or +234…).');
    }
}
