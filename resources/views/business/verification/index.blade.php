@extends('layouts.business')

@section('title', 'Verification (KYC)')
@section('page-title', 'Verification (KYC)')

@section('content')
<div class="space-y-6">
    <!-- Verification Status Overview -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Verification Status</h3>
        <p class="text-sm text-gray-600 mb-6">Complete your verification to unlock full payment gateway features and increase your transaction limits.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
                $verificationTypes = [
                    'basic' => ['title' => 'Basic Information', 'icon' => 'fa-user', 'required' => true],
                    'business_registration' => ['title' => 'Business Registration', 'icon' => 'fa-building', 'required' => true],
                    'bank_account' => ['title' => 'Bank Account', 'icon' => 'fa-university', 'required' => true],
                    'identity' => ['title' => 'Identity Document', 'icon' => 'fa-id-card', 'required' => false],
                    'address' => ['title' => 'Address Verification', 'icon' => 'fa-map-marker-alt', 'required' => false],
                ];
            @endphp

            @foreach($verificationTypes as $type => $info)
                @php
                    $verification = $verifications->where('verification_type', $type)->first();
                    $status = $verification ? $verification->status : 'pending';
                @endphp
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas {{ $info['icon'] }} text-primary"></i>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900">{{ $info['title'] }}</h4>
                                @if($info['required'])
                                    <span class="text-xs text-red-600">Required</span>
                                @endif
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
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Submit Verification Document</h3>

        <form action="{{ route('business.verification.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="verification_type" class="block text-sm font-medium text-gray-700 mb-1">Verification Type *</label>
                    <select name="verification_type" id="verification_type" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">Select type</option>
                        <option value="basic">Basic Information</option>
                        <option value="business_registration">Business Registration (CAC Certificate)</option>
                        <option value="bank_account">Bank Account Statement</option>
                        <option value="identity">Identity Document (ID Card, Passport)</option>
                        <option value="address">Address Verification (Utility Bill)</option>
                    </select>
                    @error('verification_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document Type *</label>
                    <input type="text" name="document_type" id="document_type" required
                        placeholder="e.g., CAC Certificate, Bank Statement, ID Card"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    @error('document_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="document" class="block text-sm font-medium text-gray-700 mb-1">Upload Document *</label>
                    <input type="file" name="document" id="document" required accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <p class="mt-1 text-xs text-gray-500">Accepted formats: PDF, JPG, PNG (Max: 5MB)</p>
                    @error('document')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($verifications as $verification)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ ucfirst(str_replace('_', ' ', $verification->verification_type)) }}
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
@endsection
