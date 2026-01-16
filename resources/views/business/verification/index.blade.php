@extends('layouts.business')

@section('title', 'Verification (KYC)')
@section('page-title', 'Verification (KYC)')

@section('content')
<div class="space-y-6">
    @php
        $business = auth('business')->user();
        $requiredTypes = \App\Models\BusinessVerification::getRequiredTypes();
        $missingDocs = $business->getMissingKycDocuments();
        $allSubmitted = $business->hasAllRequiredKycDocuments();
        $allApproved = $business->hasAllKycDocumentsApproved();
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
                <h3 class="text-sm font-medium text-blue-800">Documents Under Review</h3>
                <p class="mt-2 text-sm text-blue-700">All required documents have been submitted and are being reviewed by our team.</p>
            </div>
        </div>
    </div>
    @else
    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-green-800">KYC Verification Complete</h3>
                <p class="mt-2 text-sm text-green-700">All required documents have been submitted and approved.</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Verification Status Overview -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Verification Status</h3>
        <p class="text-sm text-gray-600 mb-6">Complete all required KYC documents to unlock full payment gateway features.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($requiredTypes as $type)
                @php
                    $verification = $verifications->where('verification_type', $type)->first();
                    $status = $verification ? $verification->status : 'pending';
                    $label = \App\Models\BusinessVerification::getTypeLabel($type);
                    $icons = [
                        'bvn' => 'fa-id-badge',
                        'nin' => 'fa-id-card',
                        'cac_certificate' => 'fa-file-certificate',
                        'cac_application' => 'fa-file-alt',
                        'account_number' => 'fa-hashtag',
                        'bank_address' => 'fa-map-marker-alt',
                        'utility_bill' => 'fa-file-invoice',
                    ];
                    $icon = $icons[$type] ?? 'fa-file';
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

    <!-- Submit Verification -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Submit KYC Document</h3>

        <form action="{{ route('business.verification.store') }}" method="POST" enctype="multipart/form-data" id="verification-form">
            @csrf

            <div class="space-y-6">
                <div>
                    <label for="verification_type" class="block text-sm font-medium text-gray-700 mb-1">Document Type <span class="text-red-500">*</span></label>
                    <select name="verification_type" id="verification_type" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">Select document type</option>
                        <option value="bvn">BVN (Bank Verification Number)</option>
                        <option value="nin">NIN (National Identification Number)</option>
                        <option value="cac_certificate">CAC Certificate</option>
                        <option value="cac_application">CAC Application</option>
                        <option value="account_number">Account Number</option>
                        <option value="bank_address">Bank Address</option>
                        <option value="utility_bill">Utility Bill</option>
                    </select>
                    @error('verification_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Text-based fields (BVN, NIN, Account Number, Bank Address) -->
                <div id="text-fields" class="hidden space-y-4">
                    <div id="bvn-field" class="hidden">
                        <label for="bvn" class="block text-sm font-medium text-gray-700 mb-1">BVN <span class="text-red-500">*</span></label>
                        <input type="text" name="bvn" id="bvn" maxlength="11" minlength="11"
                            placeholder="Enter your 11-digit BVN"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('bvn')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="nin-field" class="hidden">
                        <label for="nin" class="block text-sm font-medium text-gray-700 mb-1">NIN <span class="text-red-500">*</span></label>
                        <input type="text" name="nin" id="nin" maxlength="11" minlength="11"
                            placeholder="Enter your 11-digit NIN"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('nin')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="account-number-field" class="hidden">
                        <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-red-500">*</span></label>
                        <input type="text" name="account_number" id="account_number"
                            placeholder="Enter your bank account number"
                            value="{{ old('account_number', $business->account_number) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('account_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="bank-address-field" class="hidden">
                        <label for="bank_address" class="block text-sm font-medium text-gray-700 mb-1">Bank Address <span class="text-red-500">*</span></label>
                        <textarea name="bank_address" id="bank_address" rows="3"
                            placeholder="Enter your bank's full address"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">{{ old('bank_address', $business->bank_address) }}</textarea>
                        @error('bank_address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- File-based fields (CAC Certificate, CAC Application, Utility Bill) -->
                <div id="file-fields" class="hidden">
                    <div>
                        <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document Description</label>
                        <input type="text" name="document_type" id="document_type"
                            placeholder="e.g., CAC Certificate, Utility Bill"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        @error('document_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <label for="document" class="block text-sm font-medium text-gray-700 mb-1">Upload Document <span class="text-red-500">*</span></label>
                        <input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <p class="mt-1 text-xs text-gray-500">Accepted formats: PDF, JPG, PNG (Max: 5MB)</p>
                        @error('document')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-upload mr-2"></i> Submit Verification
                </button>
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
document.getElementById('verification_type').addEventListener('change', function() {
    const type = this.value;
    const textFields = document.getElementById('text-fields');
    const fileFields = document.getElementById('file-fields');
    
    // Hide all fields
    document.getElementById('bvn-field').classList.add('hidden');
    document.getElementById('nin-field').classList.add('hidden');
    document.getElementById('account-number-field').classList.add('hidden');
    document.getElementById('bank-address-field').classList.add('hidden');
    textFields.classList.add('hidden');
    fileFields.classList.add('hidden');
    
    // Show relevant fields
    if (['bvn', 'nin', 'account_number', 'bank_address'].includes(type)) {
        textFields.classList.remove('hidden');
        if (type === 'bvn') {
            document.getElementById('bvn-field').classList.remove('hidden');
            document.getElementById('bvn').required = true;
        } else {
            document.getElementById('bvn').required = false;
        }
        if (type === 'nin') {
            document.getElementById('nin-field').classList.remove('hidden');
            document.getElementById('nin').required = true;
        } else {
            document.getElementById('nin').required = false;
        }
        if (type === 'account_number') {
            document.getElementById('account-number-field').classList.remove('hidden');
            document.getElementById('account_number').required = true;
        } else {
            document.getElementById('account_number').required = false;
        }
        if (type === 'bank_address') {
            document.getElementById('bank-address-field').classList.remove('hidden');
            document.getElementById('bank_address').required = true;
        } else {
            document.getElementById('bank_address').required = false;
        }
        document.getElementById('document').required = false;
    } else if (['cac_certificate', 'cac_application', 'utility_bill'].includes(type)) {
        fileFields.classList.remove('hidden');
        document.getElementById('document').required = true;
    }
});
</script>
@endpush
@endsection
