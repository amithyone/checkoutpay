@extends('layouts.business')

@section('title', 'Verification (KYC)')
@section('page-title', 'Verification (KYC)')

@section('content')
<div class="space-y-6">
    @php
        $business = auth('business')->user();
        $requiredTypes = \App\Models\BusinessVerification::getRequiredTypes();
        $personalKycTypes = [
            \App\Models\BusinessVerification::TYPE_BVN,
            \App\Models\BusinessVerification::TYPE_NIN,
            \App\Models\BusinessVerification::TYPE_ACCOUNT_NUMBER,
        ];
        $businessKycTypes = [
            \App\Models\BusinessVerification::TYPE_CAC_CERTIFICATE,
            \App\Models\BusinessVerification::TYPE_CAC_APPLICATION,
            \App\Models\BusinessVerification::TYPE_UTILITY_BILL,
        ];
        $missingDocs = $business->getMissingKycDocuments();
        $allSubmitted = $business->hasAllRequiredKycDocuments();
        $allApproved = $business->hasAllKycDocumentsApproved();
        $openKycTypes = collect($requiredTypes)->filter(fn ($type) => $business->canSubmitKycType($type))->values();
        $lockCacProfile = ! $business->canSubmitKycType(\App\Models\BusinessVerification::TYPE_CAC_CERTIFICATE)
            || ! $business->canSubmitKycType(\App\Models\BusinessVerification::TYPE_CAC_APPLICATION);
        $lockIdentityProfile = ! $business->canSubmitKycType(\App\Models\BusinessVerification::TYPE_BVN)
            || ! $business->canSubmitKycType(\App\Models\BusinessVerification::TYPE_NIN);
    @endphp

    <!-- KYC Status Alert -->
    @if(!$allSubmitted)
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">KYC Documents Required</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p>Please submit all required KYC documents to complete your verification:</p>
                    <ul class="list-disc list-inside mt-2">
                        @foreach($missingDocs as $type)
                            <li>{{ \App\Models\BusinessVerification::getTypeLabel($type) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @elseif(!$allApproved)
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Almost there</h3>
                <p class="mt-2 text-sm text-blue-700">
                    All required documents are submitted. When your details are validated, we complete verification and activate your business pay-in account automatically when possible.
                    If something fails, you’ll see a message with next steps.
                </p>
            </div>
        </div>
    </div>
    @else
    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-green-800">KYC Verification Complete</h3>
                <p class="mt-2 text-sm text-green-700">All required documents have been submitted and approved.</p>
                @if(!empty($business->rubies_business_account_number))
                    <div class="mt-4 p-3 bg-white rounded-lg border border-green-200 text-sm text-gray-800">
                        <p class="font-semibold text-green-900 mb-2">Permanent business pay-in account</p>
                        <p><span class="text-gray-600">Account number:</span> <span class="font-mono font-medium">{{ $business->rubies_business_account_number }}</span></p>
                        @if(!empty($business->rubies_business_bank_name))
                            <p><span class="text-gray-600">Bank:</span> {{ $business->rubies_business_bank_name }}</p>
                        @endif
                        @if(!empty($business->rubies_business_account_name))
                            <p><span class="text-gray-600">Account name:</span> {{ $business->rubies_business_account_name }}</p>
                        @endif
                    </div>
                @elseif(in_array($business->rubies_account_provision_status, ['queued', 'processing'], true))
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200 text-sm text-blue-900">
                        <p class="font-semibold mb-1">Pay-in account provisioning</p>
                        <p>Your permanent account is being created. Refresh this page in a few minutes.</p>
                        @if($business->rubies_account_provision_queued_at)
                            <p class="text-xs mt-2 text-blue-800">Queued {{ $business->rubies_account_provision_queued_at->diffForHumans() }}</p>
                        @endif
                    </div>
                @elseif($business->rubies_account_provision_status === 'failed')
                    <div class="mt-4 p-3 bg-red-50 rounded-lg border border-red-200 text-sm text-red-900">
                        <p class="font-semibold mb-1">Pay-in account setup failed</p>
                        <p>{{ $business->rubies_account_provision_error ?? 'Please contact support or try requesting the account again.' }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Verification Status Overview -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Verification Status</h3>
        <p class="text-sm text-gray-600 mb-6">Complete all required KYC documents to unlock full payment gateway features.</p>

        @php
            $statusIcons = [
                'bvn' => 'fa-id-badge',
                'nin' => 'fa-id-card',
                'cac_certificate' => 'fa-file-certificate',
                'cac_application' => 'fa-file-alt',
                'account_number' => 'fa-university',
                'utility_bill' => 'fa-file-invoice',
            ];
        @endphp
        <div class="space-y-6">
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Personal information</h4>
                <p class="text-xs text-gray-500 mb-3">BVN, NIN, payout bank account, and the phone / name we keep on your profile.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($personalKycTypes as $type)
                        @php
                            $verification = $verifications->where('verification_type', $type)->first();
                            $status = $verification ? $verification->status : 'pending';
                            $label = \App\Models\BusinessVerification::getTypeLabel($type);
                            $icon = $statusIcons[$type] ?? 'fa-file';
                        @endphp
                        <div class="border border-gray-200 rounded-lg p-4 {{ $status === 'approved' ? 'bg-green-50' : ($status === 'rejected' ? 'bg-red-50' : '') }}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas {{ $icon }} text-primary"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 text-sm">{{ $label }}</h4>
                                        <span class="text-xs text-red-600">Required</span>
                                    </div>
                                </div>
                                @if($status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                        <i class="fas fa-check-circle mr-1"></i> Approved
                                    </span>
                                @elseif($status === 'under_review')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                        <i class="fas fa-clock mr-1"></i> Reviewing
                                    </span>
                                @elseif($status === 'rejected')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                        <i class="fas fa-times-circle mr-1"></i> Rejected
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                            </div>
                            @if($verification && $verification->rejection_reason)
                                <p class="text-xs text-red-600 mt-2">{{ $verification->rejection_reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Business verification</h4>
                <p class="text-xs text-gray-500 mb-3">CAC documents and utility bill for your business.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($businessKycTypes as $type)
                        @php
                            $verification = $verifications->where('verification_type', $type)->first();
                            $status = $verification ? $verification->status : 'pending';
                            $label = \App\Models\BusinessVerification::getTypeLabel($type);
                            $icon = $statusIcons[$type] ?? 'fa-file';
                        @endphp
                        <div class="border border-gray-200 rounded-lg p-4 {{ $status === 'approved' ? 'bg-green-50' : ($status === 'rejected' ? 'bg-red-50' : '') }}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas {{ $icon }} text-primary"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 text-sm">{{ $label }}</h4>
                                        <span class="text-xs text-red-600">Required</span>
                                    </div>
                                </div>
                                @if($status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                        <i class="fas fa-check-circle mr-1"></i> Approved
                                    </span>
                                @elseif($status === 'under_review')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                        <i class="fas fa-clock mr-1"></i> Reviewing
                                    </span>
                                @elseif($status === 'rejected')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                        <i class="fas fa-times-circle mr-1"></i> Rejected
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                        <i class="fas fa-clock mr-1"></i> Pending
                                    </span>
                                @endif
                            </div>
                            @if($verification && $verification->rejection_reason)
                                <p class="text-xs text-red-600 mt-2">{{ $verification->rejection_reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Verification -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Verification</h3>
        <p class="text-sm text-gray-600 mb-6">Add KYC while you are still filling it in. After you submit an item it is locked until it is rejected. Approved items cannot be changed.</p>

        <form action="{{ route('business.verification.store') }}" method="POST" enctype="multipart/form-data" id="verification-form">
            @csrf

            <div class="space-y-8">
                <!-- Permanent pay-in account details -->
                <div class="rounded-lg border-2 border-primary/20 bg-primary/5 p-5 space-y-4">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900">Permanent business pay-in account</h4>
                        <p class="text-xs text-gray-600 mt-1">
                            We create your <strong>permanent</strong> pay-in account with your registered business name and CAC RC/BN
                            (so that account title is the company). Checkout payment-request temp account names are controlled by Checkout admin (usually Checkout Now Ltd) — not by this form.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label for="business_registered_name" class="block text-sm font-medium text-gray-700 mb-1">Registered business name <span class="text-red-500">*</span></label>
                            <input type="text" name="business_registered_name" id="business_registered_name" maxlength="255"
                                placeholder="Exact company / business name on CAC"
                                value="{{ old('business_registered_name', $business->name) }}"
                                {{ $lockCacProfile ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary {{ $lockCacProfile ? 'bg-gray-100 text-gray-600' : 'bg-white' }}">
                            @if($lockCacProfile)
                                <p class="mt-1 text-xs text-gray-500">Locked because CAC KYC is already submitted or approved.</p>
                            @endif
                            @error('business_registered_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="cac_registration_number" class="block text-sm font-medium text-gray-700 mb-1">CAC RC or BN number <span id="cac-reg-required" class="text-red-500 hidden">*</span></label>
                            <input type="text" name="cac_registration_number" id="cac_registration_number" maxlength="100"
                                placeholder="e.g. RC1234567 or BN1234567"
                                value="{{ old('cac_registration_number', $business->cac_registration_number) }}"
                                {{ $lockCacProfile ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary {{ $lockCacProfile ? 'bg-gray-100 text-gray-600' : 'bg-white' }}">
                            <p class="mt-1 text-xs text-gray-500">Use RC for limited companies or BN for business names. This is sent as the registration number when creating the pay-in account.</p>
                            @error('cac_registration_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="business_phone" class="block text-sm font-medium text-gray-700 mb-1">Business phone number <span id="business-phone-required" class="text-red-500 hidden">*</span></label>
                            <input type="text" name="business_phone" id="business_phone"
                                placeholder="e.g. 08012345678 or +2348012345678"
                                value="{{ old('business_phone', $business->phone) }}"
                                autocomplete="tel"
                                {{ $lockCacProfile ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary {{ $lockCacProfile ? 'bg-gray-100 text-gray-600' : 'bg-white' }}">
                            @error('business_phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="business_email" class="block text-sm font-medium text-gray-700 mb-1">Business email <span id="business-email-required" class="text-red-500 hidden">*</span></label>
                            <input type="email" name="business_email" id="business_email"
                                placeholder="Business email"
                                value="{{ old('business_email', $business->email) }}"
                                autocomplete="email"
                                {{ $lockCacProfile ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary {{ $lockCacProfile ? 'bg-gray-100 text-gray-600' : 'bg-white' }}">
                            @error('business_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="signatory_dob" class="block text-sm font-medium text-gray-700 mb-1">Signatory date of birth <span id="signatory-dob-required" class="text-red-500 hidden">*</span></label>
                            <input type="date" name="signatory_dob" id="signatory_dob"
                                value="{{ old('signatory_dob', optional($business->rubies_signatory_dob)?->format('Y-m-d')) }}"
                                {{ $lockIdentityProfile ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary {{ $lockIdentityProfile ? 'bg-gray-100 text-gray-600' : 'bg-white' }}">
                            @error('signatory_dob')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit"
                            formaction="{{ route('business.verification.permanent-account') }}"
                            formnovalidate
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                            <i class="fas fa-university mr-2"></i> Request permanent pay-in account
                        </button>
                        <span class="text-xs text-gray-500 self-center">Saves these fields and provisions your account when details are complete.</span>
                    </div>
                </div>

                <!-- Personal information -->
                <div class="rounded-lg border border-gray-200 bg-slate-50/80 p-5 space-y-4">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900">Personal information</h4>
                        <p class="text-xs text-gray-600 mt-1">
                            Signatory legal name as on BVN/NIN (for identity checks only).
                            This is <strong>not</strong> used as the permanent pay-in account title — that uses the registered business name above.
                        </p>
                    </div>
                    <div>
                        <label for="legal_name" class="block text-sm font-medium text-gray-700 mb-1">Signatory name (BVN/NIN) <span id="legal-name-required" class="text-red-500 hidden">*</span></label>
                        <input type="text" name="legal_name" id="legal_name"
                            placeholder="Full name as on BVN or NIN"
                            value="{{ old('legal_name', $business->rubies_signatory_name) }}"
                            {{ $lockIdentityProfile ? 'readonly' : '' }}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary {{ $lockIdentityProfile ? 'bg-gray-100 text-gray-600' : 'bg-white' }} max-w-xl">
                        @error('legal_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="verification_type" class="block text-sm font-medium text-gray-700 mb-1">What are you submitting? <span class="text-red-500">*</span></label>
                        <select name="verification_type" id="verification_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                            <option value="">Select type</option>
                            <optgroup label="Personal verification">
                                @foreach(['bvn' => 'BVN (Bank Verification Number)', 'nin' => 'NIN (National Identification Number)', 'account_number' => 'Business bank account (NUBAN)'] as $type => $label)
                                    @php $lock = $business->kycTypeLockReason($type); @endphp
                                    <option value="{{ $type }}" @disabled($lock) {{ old('verification_type') === $type && ! $lock ? 'selected' : '' }}>
                                        {{ $label }}@if($lock === 'approved') (approved — locked)@elseif($lock === 'submitted') (submitted — locked)@endif
                                    </option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Business documents">
                                @foreach(['cac_certificate' => 'CAC Certificate', 'cac_application' => 'CAC Application', 'utility_bill' => 'Utility Bill'] as $type => $label)
                                    @php $lock = $business->kycTypeLockReason($type); @endphp
                                    <option value="{{ $type }}" @disabled($lock) {{ old('verification_type') === $type && ! $lock ? 'selected' : '' }}>
                                        {{ $label }}@if($lock === 'approved') (approved — locked)@elseif($lock === 'submitted') (submitted — locked)@endif
                                    </option>
                                @endforeach
                            </optgroup>
                        </select>
                        @error('verification_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text-based fields (BVN, NIN, Business Bank Account) -->
                    <div id="text-fields" class="hidden space-y-4">
                        <div id="bvn-field" class="hidden">
                            <label for="bvn" class="block text-sm font-medium text-gray-700 mb-1">BVN <span class="text-red-500">*</span></label>
                            <input type="text" name="bvn" id="bvn" maxlength="11" minlength="11"
                                placeholder="Enter your 11-digit BVN"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                            @error('bvn')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="nin-field" class="hidden">
                            <label for="nin" class="block text-sm font-medium text-gray-700 mb-1">NIN <span class="text-red-500">*</span></label>
                            <input type="text" name="nin" id="nin" maxlength="11" minlength="11"
                                placeholder="Enter your 11-digit NIN"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                            @error('nin')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="identity-dob-field" class="hidden">
                            <label for="identity_dob" class="block text-sm font-medium text-gray-700 mb-1">Date of birth (as on BVN/NIN) <span class="text-red-500">*</span></label>
                            <input type="date" id="identity_dob" max="{{ now()->subDay()->format('Y-m-d') }}"
                                value="{{ old('signatory_dob', optional($business->rubies_signatory_dob)?->format('Y-m-d')) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white max-w-xs">
                            <p class="mt-1 text-xs text-gray-500">Must match your BVN or NIN record. We use this for identity verification.</p>
                            @error('signatory_dob')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="account-number-field" class="hidden space-y-3">
                            <p class="text-xs text-gray-500">We use NUBAN to verify your account and pull the account name automatically.</p>
                            <div>
                                <label for="bank_search_kyc" class="block text-sm font-medium text-gray-700 mb-1">Bank <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="text" id="bank_search_kyc" autocomplete="off"
                                        placeholder="Search bank..."
                                        value="{{ old('bank_search_kyc', $business->bank_name) }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                    <input type="hidden" name="bank_code" id="bank_code_kyc" value="{{ old('bank_code', $business->bank_code) }}">
                                    <div id="bank_dropdown_kyc" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto mt-1"></div>
                                </div>
                                @error('bank_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-red-500">*</span></label>
                                <input type="text" name="account_number" id="account_number" maxlength="10"
                                    placeholder="Enter 10-digit NUBAN account number"
                                    value="{{ old('account_number', $business->account_number) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                @error('account_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business document uploads -->
                <div class="rounded-lg border border-gray-200 p-5 space-y-4">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900">Business document uploads</h4>
                        <p class="text-xs text-gray-600 mt-1">Upload CAC and utility documents when you select those types below. CAC uploads require the pay-in fields above.</p>
                    </div>

                    <!-- File-based fields (CAC Certificate, CAC Application, Utility Bill) -->
                    <div id="file-fields" class="hidden space-y-4">
                        <div>
                            <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document description</label>
                            <input type="text" name="document_type" id="document_type"
                                placeholder="e.g., CAC Certificate, Utility Bill"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            @error('document_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="document" class="block text-sm font-medium text-gray-700 mb-1">Upload document <span class="text-red-500">*</span></label>
                            <input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <p class="mt-1 text-xs text-gray-500">Accepted formats: PDF, JPG, PNG (max 5MB)</p>
                            @error('document')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                @if($openKycTypes->isEmpty())
                    <p class="text-sm text-gray-600">All KYC items are submitted or approved. You can re-upload only after an item is rejected.</p>
                @else
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        <i class="fas fa-upload mr-2"></i> Submit Verification
                    </button>
                @endif
            </div>
        </form>
    </div>

    <!-- Previous Submissions -->
    @if($verifications->count() > 0)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Verification History</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($verifications->whereIn('verification_type', $requiredTypes) as $verification)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ \App\Models\BusinessVerification::getTypeLabel($verification->verification_type) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $verification->document_type }}</td>
                        <td class="px-6 py-4">
                            @if($verification->status === 'approved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                            @elseif($verification->status === 'under_review')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Under Review</span>
                            @elseif($verification->status === 'rejected')
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Pending</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $verification->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            @if($verification->document_path)
                                <a href="{{ route('business.verification.download', $verification) }}" 
                                   class="text-primary hover:underline text-sm">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                            @else
                                <span class="text-xs text-gray-400">Text submission</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
const banksKyc = @json(config('banks', []));

document.getElementById('verification_type').addEventListener('change', function() {
    const type = this.value;
    const textFields = document.getElementById('text-fields');
    const fileFields = document.getElementById('file-fields');
    const businessPhoneReq = document.getElementById('business-phone-required');
    const cacRegReq = document.getElementById('cac-reg-required');
    const signatoryDobReq = document.getElementById('signatory-dob-required');
    const nameReq = document.getElementById('legal-name-required');
    const emailReq = document.getElementById('business-email-required');

    document.getElementById('bvn-field').classList.add('hidden');
    document.getElementById('nin-field').classList.add('hidden');
    document.getElementById('identity-dob-field').classList.add('hidden');
    document.getElementById('account-number-field').classList.add('hidden');
    textFields.classList.add('hidden');
    fileFields.classList.add('hidden');

    document.getElementById('bvn').required = false;
    document.getElementById('nin').required = false;
    const identityDob = document.getElementById('identity_dob');
    if (identityDob) identityDob.required = false;
    document.getElementById('account_number').required = false;
    document.getElementById('bank_code_kyc').removeAttribute('required');
    document.getElementById('document').required = false;
    const cacReg = document.getElementById('cac_registration_number');
    const signDob = document.getElementById('signatory_dob');
    const bizEmail = document.getElementById('business_email');
    const businessPhone = document.getElementById('business_phone');
    const legalName = document.getElementById('legal_name');
    const registeredName = document.getElementById('business_registered_name');
    if (cacReg && signDob && bizEmail && businessPhone) {
        cacReg.required = false;
        signDob.required = false;
        bizEmail.required = false;
        businessPhone.required = false;
    }
    if (registeredName) registeredName.required = false;
    [businessPhoneReq, cacRegReq, signatoryDobReq, nameReq, emailReq].forEach(function(el) {
        if (el) el.classList.add('hidden');
    });
    if (legalName) legalName.required = false;

    if (['bvn', 'nin', 'account_number'].includes(type)) {
        textFields.classList.remove('hidden');
        if (type === 'bvn') {
            document.getElementById('bvn-field').classList.remove('hidden');
            document.getElementById('bvn').required = true;
            document.getElementById('identity-dob-field').classList.remove('hidden');
            if (identityDob) {
                identityDob.required = true;
                if (!identityDob.value && signDob && signDob.value) {
                    identityDob.value = signDob.value;
                }
            }
            if (businessPhoneReq) businessPhoneReq.classList.remove('hidden');
            if (signatoryDobReq) signatoryDobReq.classList.remove('hidden');
            if (nameReq) nameReq.classList.remove('hidden');
            if (businessPhone) businessPhone.required = true;
            if (legalName) legalName.required = true;
            if (signDob) signDob.required = true;
        }
        if (type === 'nin') {
            document.getElementById('nin-field').classList.remove('hidden');
            document.getElementById('nin').required = true;
            document.getElementById('identity-dob-field').classList.remove('hidden');
            if (identityDob) {
                identityDob.required = true;
                if (!identityDob.value && signDob && signDob.value) {
                    identityDob.value = signDob.value;
                }
            }
            if (businessPhoneReq) businessPhoneReq.classList.remove('hidden');
            if (signatoryDobReq) signatoryDobReq.classList.remove('hidden');
            if (nameReq) nameReq.classList.remove('hidden');
            if (businessPhone) businessPhone.required = true;
            if (legalName) legalName.required = true;
            if (signDob) signDob.required = true;
        }
        if (type === 'account_number') {
            document.getElementById('account-number-field').classList.remove('hidden');
            document.getElementById('account_number').required = true;
            document.getElementById('bank_code_kyc').setAttribute('required', 'required');
        }
    } else if (['cac_certificate', 'cac_application', 'utility_bill'].includes(type)) {
        fileFields.classList.remove('hidden');
        document.getElementById('document').required = true;
        if (['cac_certificate', 'cac_application'].includes(type) && cacReg && signDob && bizEmail && businessPhone) {
            cacReg.required = true;
            signDob.required = true;
            bizEmail.required = true;
            businessPhone.required = true;
            if (registeredName) registeredName.required = true;
            if (cacRegReq) cacRegReq.classList.remove('hidden');
            if (signatoryDobReq) signatoryDobReq.classList.remove('hidden');
            if (businessPhoneReq) businessPhoneReq.classList.remove('hidden');
            if (emailReq) emailReq.classList.remove('hidden');
        }
    }
});

// Bank search for KYC
const bankSearchKyc = document.getElementById('bank_search_kyc');
const bankCodeKyc = document.getElementById('bank_code_kyc');
const bankDropdownKyc = document.getElementById('bank_dropdown_kyc');
if (bankSearchKyc && bankCodeKyc && bankDropdownKyc) {
    bankSearchKyc.addEventListener('focus', function() {
        const v = (this.value || '').toLowerCase();
        const filtered = banksKyc.filter(bank => (bank.bank_name || '').toLowerCase().includes(v)).slice(0, 15);
        bankDropdownKyc.innerHTML = filtered.length ? filtered.map(bank =>
            '<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" data-code="' + (bank.code || '') + '" data-name="' + (bank.bank_name || '').replace(/"/g, '&quot;') + '">' + (bank.bank_name || '') + '</div>'
        ).join('') : '<div class="px-4 py-2 text-gray-500 text-sm">No bank found</div>';
        bankDropdownKyc.classList.remove('hidden');
    });
    bankSearchKyc.addEventListener('input', function() {
        const v = (this.value || '').toLowerCase();
        const filtered = banksKyc.filter(bank => (bank.bank_name || '').toLowerCase().includes(v)).slice(0, 15);
        bankDropdownKyc.innerHTML = filtered.length ? filtered.map(bank =>
            '<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" data-code="' + (bank.code || '') + '" data-name="' + (bank.bank_name || '').replace(/"/g, '&quot;') + '">' + (bank.bank_name || '') + '</div>'
        ).join('') : '<div class="px-4 py-2 text-gray-500 text-sm">No bank found</div>';
        bankDropdownKyc.classList.remove('hidden');
    });
    bankDropdownKyc.addEventListener('click', function(e) {
        const el = e.target.closest('[data-code]');
        if (el) {
            bankCodeKyc.value = el.getAttribute('data-code') || '';
            bankSearchKyc.value = el.getAttribute('data-name') || '';
            bankDropdownKyc.classList.add('hidden');
        }
    });
    document.addEventListener('click', function(e) {
        if (!bankSearchKyc.contains(e.target) && !bankDropdownKyc.contains(e.target)) {
            bankDropdownKyc.classList.add('hidden');
        }
    });
}

(function initVerificationTypeUi() {
    const sel = document.getElementById('verification_type');
    if (sel && sel.value) {
        sel.dispatchEvent(new Event('change'));
    }
})();

const signDobPayIn = document.getElementById('signatory_dob');
const identityDobInput = document.getElementById('identity_dob');
const verificationForm = document.getElementById('verification-form');

function syncIdentityDobFields() {
    if (!identityDobInput || !signDobPayIn) return;
    const identityVisible = !document.getElementById('identity-dob-field').classList.contains('hidden');
    if (identityVisible && identityDobInput.value) {
        signDobPayIn.value = identityDobInput.value;
    }
}

if (identityDobInput && signDobPayIn) {
    identityDobInput.addEventListener('change', function () {
        signDobPayIn.value = this.value;
    });
    signDobPayIn.addEventListener('change', function () {
        const identityVisible = !document.getElementById('identity-dob-field').classList.contains('hidden');
        if (identityVisible) {
            identityDobInput.value = this.value;
        }
    });
}

if (verificationForm) {
    verificationForm.addEventListener('submit', syncIdentityDobFields);
}
</script>
@endpush
@endsection
