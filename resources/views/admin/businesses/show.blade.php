@extends('layouts.admin')

@section('title', 'Business Details')
@section('page-title', 'Business Details')

@section('content')
<div class="space-y-6">
    <!-- Business Info Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $business->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">Registered: {{ $business->created_at->format('M d, Y') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <form action="{{ route('admin.businesses.toggle-status', $business) }}" method="POST" class="inline">
                    @csrf
                    @if($business->is_active)
                        <button type="submit" class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm"
                            onclick="return confirm('Are you sure you want to deactivate this business?')">
                            <i class="fas fa-ban mr-2"></i> Deactivate
                        </button>
                    @else
                        <button type="submit" class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-sm">
                            <i class="fas fa-check mr-2"></i> Activate
                        </button>
                    @endif
                </form>
                <a href="{{ route('admin.businesses.edit', $business) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
                @if(auth('admin')->user()->isSuperAdmin())
                    <form action="{{ route('admin.businesses.login-as', $business) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-user-secret mr-2"></i> View as Business
                        </button>
                    </form>
                @endif
                @if($business->is_active)
                    <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                @else
                    <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="text-xs text-gray-600">Email</label>
                <p class="text-sm font-medium text-gray-900 mt-1">{{ $business->email }}</p>
            </div>
            <div>
                <label class="text-xs text-gray-600">Phone</label>
                <p class="text-sm font-medium text-gray-900 mt-1">{{ $business->phone ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-xs text-gray-600">Address</label>
                <p class="text-sm font-medium text-gray-900 mt-1">{{ $business->address ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-xs text-gray-600">Website</label>
                @if($business->website)
                    <p class="text-sm font-medium text-gray-900 mt-1">
                        <a href="{{ $business->website }}" target="_blank" class="text-primary hover:underline">
                            {{ $business->website }} <i class="fas fa-external-link-alt text-xs"></i>
                        </a>
                    </p>
                @else
                    <p class="text-sm text-gray-500 mt-1">Not provided</p>
                @endif
            </div>
            <div>
                <label class="text-xs text-gray-600">Balance</label>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-lg font-bold text-gray-900">₦{{ number_format($business->balance, 2) }}</p>
                    @if(auth('admin')->user()->canUpdateBusinessBalance())
                    <button onclick="showBalanceModal()" class="text-xs text-primary hover:underline">
                        <i class="fas fa-edit mr-1"></i> Update
                    </button>
                    @endif
                </div>
            </div>
            @if(auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->role === 'admin')
            <div>
                <label class="text-xs text-gray-600">Daily Revenue <span class="text-gray-400">(sum of all websites)</span></label>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-sm font-medium text-gray-900">₦{{ number_format($business->daily_revenue ?? 0, 2) }}</p>
                </div>
            </div>
            <div>
                <label class="text-xs text-gray-600">Monthly Revenue <span class="text-gray-400">(sum of all websites)</span></label>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-sm font-medium text-gray-900">₦{{ number_format($business->monthly_revenue ?? 0, 2) }}</p>
                </div>
            </div>
            <div>
                <label class="text-xs text-gray-600">Yearly Revenue <span class="text-gray-400">(sum of all websites)</span></label>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-sm font-medium text-gray-900">₦{{ number_format($business->yearly_revenue ?? 0, 2) }}</p>
                </div>
            </div>
            @endif
            <div>
                <label class="text-xs text-gray-600">Webhook URL</label>
                <p class="text-sm font-medium text-gray-900 mt-1 break-all">{{ $business->webhook_url ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-200">
            <label class="text-sm text-gray-600 mb-2 block">API Key</label>
            <div class="flex items-center space-x-2">
                <input type="text" value="{{ $business->api_key }}" readonly
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-sm font-mono">
                <form action="{{ route('admin.businesses.regenerate-api-key', $business) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 text-sm"
                        onclick="return confirm('Are you sure? This will invalidate the current API key.')">
                        <i class="fas fa-sync-alt mr-2"></i> Regenerate
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Charge Settings Section -->
    @if(auth('admin')->user()->canUpdateBusinessBalance())
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-percent mr-2 text-primary"></i> Charge Settings
            </h3>
        </div>
        
        <form action="{{ route('admin.businesses.update-charges', $business) }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Charge Percentage (%)
                    </label>
                    <input 
                        type="number" 
                        name="charge_percentage" 
                        value="{{ $business->charge_percentage ?? '' }}"
                        step="0.01"
                        min="0"
                        max="100"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="Leave empty to use default"
                    >
                    <p class="text-xs text-gray-500 mt-1">Default: {{ \App\Models\Setting::get('default_charge_percentage', 1) }}%</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fixed Charge (₦)
                    </label>
                    <input 
                        type="number" 
                        name="charge_fixed" 
                        value="{{ $business->charge_fixed ?? '' }}"
                        step="0.01"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="Leave empty to use default"
                    >
                    <p class="text-xs text-gray-500 mt-1">Default: ₦{{ number_format(\App\Models\Setting::get('default_charge_fixed', 100), 2) }}</p>
                </div>
                
                <div class="flex items-center space-x-3">
                    <input 
                        type="checkbox" 
                        id="charge_exempt" 
                        name="charge_exempt" 
                        value="1"
                        {{ $business->charge_exempt ? 'checked' : '' }}
                        class="w-5 h-5 text-primary border-gray-300 rounded"
                    >
                    <label for="charge_exempt" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Exempt from charges
                    </label>
                </div>
                
                <div class="flex items-center space-x-3">
                    <input 
                        type="checkbox" 
                        id="charges_paid_by_customer" 
                        name="charges_paid_by_customer" 
                        value="1"
                        {{ $business->charges_paid_by_customer ? 'checked' : '' }}
                        class="w-5 h-5 text-primary border-gray-300 rounded"
                    >
                    <label for="charges_paid_by_customer" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Customer pays charges (otherwise business pays)
                    </label>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-200">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-save mr-2"></i> Update Charge Settings
                </button>
            </div>
        </form>
        
        @php
            $chargeService = app(\App\Services\ChargeService::class);
            $sampleCharges = $chargeService->calculateCharges(10000, null, $business);
        @endphp
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-xs font-semibold text-blue-900 mb-2">Sample Calculation (₦10,000 payment):</p>
            <div class="text-xs text-blue-800 space-y-1">
                <p>Original Amount: ₦{{ number_format($sampleCharges['original_amount'], 2) }}</p>
                <p>Percentage Charge ({{ $sampleCharges['charge_percentage'] > 0 ? number_format($chargeService->getChargePercentage(null, $business), 2) : '0' }}%): ₦{{ number_format($sampleCharges['charge_percentage'], 2) }}</p>
                <p>Fixed Charge: ₦{{ number_format($sampleCharges['charge_fixed'], 2) }}</p>
                <p>Total Charges: ₦{{ number_format($sampleCharges['total_charges'], 2) }}</p>
                @if($sampleCharges['paid_by_customer'])
                    <p><strong>Customer Pays: ₦{{ number_format($sampleCharges['amount_to_pay'], 2) }}</strong></p>
                    <p>Business Receives: ₦{{ number_format($sampleCharges['business_receives'], 2) }}</p>
                @else
                    <p><strong>Customer Pays: ₦{{ number_format($sampleCharges['amount_to_pay'], 2) }}</strong></p>
                    <p>Business Receives: ₦{{ number_format($sampleCharges['business_receives'], 2) }}</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Websites Management Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-globe mr-2 text-primary"></i> Websites Portfolio
            </h3>
            <button onclick="showAddWebsiteModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                <i class="fas fa-plus mr-2"></i> Add Website
            </button>
        </div>
        
        @if($business->websites->count() > 0)
            <div class="space-y-3">
                @foreach($business->websites as $website)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <a href="{{ $website->website_url }}" target="_blank" 
                                        class="text-primary hover:underline font-medium">
                                        {{ $website->website_url }}
                                        <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                    </a>
                                    @if($website->is_approved)
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <i class="fas fa-check-circle mr-1"></i> Approved
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            <i class="fas fa-clock mr-1"></i> Pending
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">
                                    Added {{ $website->created_at->format('M d, Y') }}
                                    @if($website->approved_at)
                                        • Approved {{ $website->approved_at->format('M d, Y') }}
                                        @if($website->approver)
                                            by {{ $website->approver->name }}
                                        @endif
                                    @endif
                                </div>
                                @if($website->notes)
                                    <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded">
                                        <strong>Note:</strong> {{ $website->notes }}
                                    </div>
                                @endif
                                @if($website->is_approved && $website->webhook_url)
                                    <div class="mt-2 text-xs text-gray-600">
                                        <strong>Webhook URL:</strong> <span class="font-mono text-gray-800">{{ $website->webhook_url }}</span>
                                    </div>
                                @elseif($website->is_approved)
                                    <div class="mt-2 text-xs text-yellow-600">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> No webhook URL configured
                                    </div>
                                @endif
                                @if(auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->role === 'admin')
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <div class="grid grid-cols-3 gap-3 text-xs">
                                        <div>
                                            <label class="text-gray-600">Daily Revenue</label>
                                            <div class="mt-1">
                                                <p class="font-medium text-gray-900">
                                                    ₦{{ number_format($website->getDailyRevenueForDate(today())?->revenue ?? 0, 2) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="text-gray-600">Monthly Revenue</label>
                                            <div class="mt-1">
                                                <p class="font-medium text-gray-900">
                                                    ₦{{ number_format($website->monthly_revenue ?? 0, 2) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="text-gray-600">Yearly Revenue</label>
                                            <div class="mt-1">
                                                <p class="font-medium text-gray-900">
                                                    ₦{{ number_format($website->yearly_revenue ?? 0, 2) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    @if(auth('admin')->user()->isSuperAdmin() && (\App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true))))
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <button onclick="showTransferTransactionsModal({{ $website->id }})" 
                                            class="w-full text-xs px-3 py-2 bg-orange-100 text-orange-700 rounded hover:bg-orange-200">
                                            <i class="fas fa-exchange-alt mr-1"></i> Transfer Transactions
                                        </button>
                                    </div>
                                    @endif
                                    @if(auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->role === 'admin')
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <label class="text-xs text-gray-600">Charges Enabled</label>
                                                <p class="text-xs font-medium text-gray-900 mt-1">
                                                    @if($website->charges_enabled ?? true)
                                                        <span class="text-green-600">✓ Enabled</span>
                                                    @else
                                                        <span class="text-red-600">✗ Disabled</span>
                                                    @endif
                                                </p>
                                            </div>
                                            @if(auth('admin')->user()->isSuperAdmin())
                                            <form action="{{ route('admin.businesses.websites.toggle-charges', [$business, $website]) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs px-3 py-1 rounded {{ ($website->charges_enabled ?? true) ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                                                    {{ ($website->charges_enabled ?? true) ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                        <div class="mt-2">
                                            <label class="text-xs text-gray-600">Total Charges Collected</label>
                                            <p class="text-xs font-bold text-gray-900">₦{{ number_format($website->total_charges_collected ?? 0, 2) }}</p>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @endif
                                @php
                                    $websitePayments = $website->payments()->where('status', 'approved')->get();
                                    $totalRevenue = $websitePayments->sum('amount');
                                    $totalPayments = $websitePayments->count();
                                @endphp
                                <div class="mt-2 flex items-center gap-4 text-xs text-gray-600">
                                    <span><strong>{{ $totalPayments }}</strong> payments</span>
                                    <span><strong>₦{{ number_format($totalRevenue, 2) }}</strong> revenue</span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 ml-4">
                                <button onclick="showEditWebsiteModal({{ $website->id }}, '{{ addslashes($website->website_url) }}', '{{ addslashes($website->webhook_url ?? '') }}', '{{ addslashes($website->notes ?? '') }}')" 
                                    class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                @if(!$website->is_approved)
                                    <button onclick="showApproveWebsiteModal({{ $website->id }})" 
                                        class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                @else
                                    <button onclick="showRejectWebsiteModal({{ $website->id }})" 
                                        class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                        <i class="fas fa-times mr-1"></i> Revoke
                                    </button>
                                @endif
                                <form method="POST" action="{{ route('admin.businesses.delete-website', [$business, $website]) }}" 
                                    onsubmit="return confirm('Are you sure you want to delete this website?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 text-xs text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Business needs at least one approved website to request account numbers.
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-globe text-4xl mb-3 text-gray-300"></i>
                <p>No websites added yet. Add a website using the button above.</p>
            </div>
        @endif
    </div>

    <!-- KYC Verification Section -->
    @php
        $requiredTypes = \App\Models\BusinessVerification::getRequiredTypes();
        $missingDocs = $business->getMissingKycDocuments();
        $allSubmitted = $business->hasAllRequiredKycDocuments();
        $allApproved = $business->hasAllKycDocumentsApproved();
        $kycVerifications = $business->verifications()->whereIn('verification_type', $requiredTypes)->get()->keyBy('verification_type');
    @endphp

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-id-card mr-2 text-primary"></i> KYC Verification
            </h3>
            <div>
                @if($allApproved)
                    <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                        <i class="fas fa-check-circle mr-1"></i> Complete
                    </span>
                @elseif($allSubmitted)
                    <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                        <i class="fas fa-clock mr-1"></i> Under Review
                    </span>
                @else
                    <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">
                        <i class="fas fa-exclamation-circle mr-1"></i> Incomplete ({{ count($missingDocs) }} missing)
                    </span>
                @endif
            </div>
        </div>

        @if(!$allSubmitted)
        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Warning:</strong> The following required documents are missing:
            </p>
            <ul class="list-disc list-inside mt-2 text-sm text-yellow-700">
                @foreach($missingDocs as $type)
                    <li>{{ \App\Models\BusinessVerification::getTypeLabel($type) }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="space-y-3">
            @foreach($requiredTypes as $type)
                @php
                    $verification = $kycVerifications->get($type);
                    $label = \App\Models\BusinessVerification::getTypeLabel($type);
                    $status = $verification ? $verification->status : 'pending';
                @endphp
                <div class="border border-gray-200 rounded-lg p-4 {{ $status === 'approved' ? 'bg-green-50' : ($status === 'rejected' ? 'bg-red-50' : 'bg-gray-50') }}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <h4 class="text-sm font-semibold text-gray-900">{{ $label }}</h4>
                                @if($status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($status === 'rejected')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @elseif($status === 'under_review')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Under Review</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Not Submitted</span>
                                @endif
                            </div>
                            @if($verification)
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div><span class="font-medium">Details:</span> {{ $verification->document_type }}</div>
                                    <div><span class="font-medium">Submitted:</span> {{ $verification->created_at->format('M d, Y H:i') }}</div>
                                    @if($verification->reviewed_at)
                                        <div><span class="font-medium">Reviewed:</span> {{ $verification->reviewed_at->format('M d, Y H:i') }} 
                                            @if($verification->reviewer)
                                                by {{ $verification->reviewer->name }}
                                            @endif
                                        </div>
                                    @endif
                                    @if($verification->admin_notes)
                                        <div class="mt-2 p-2 bg-white rounded border border-gray-200">
                                            <p><strong>Admin Notes:</strong> {{ $verification->admin_notes }}</p>
                                        </div>
                                    @endif
                                    @if($verification->rejection_reason)
                                        <div class="mt-2 p-2 bg-red-100 rounded border border-red-200">
                                            <p class="text-red-800"><strong>Rejection Reason:</strong> {{ $verification->rejection_reason }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="ml-4 flex flex-col space-y-2">
                            @if($verification)
                                @if($verification->document_path)
                                    <a href="{{ route('admin.businesses.verification.download', [$business, $verification]) }}" 
                                       class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs text-center">
                                        <i class="fas fa-download mr-1"></i> View
                                    </a>
                                @else
                                    <span class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs text-center">
                                        Text Data
                                    </span>
                                @endif
                                @if(in_array($status, ['pending', 'under_review']))
                                    <button onclick="showApproveKYCModal({{ $verification->id }})" 
                                            class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-xs">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                    <button onclick="showRejectKYCModal({{ $verification->id }})" 
                                            class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-xs">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                @endif
                            @else
                                <span class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs text-center">
                                    Not Submitted
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Account Numbers -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-credit-card mr-2 text-primary"></i> Account Numbers
        </h3>
        @if($business->accountNumbers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account Number</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usage</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($business->accountNumbers as $account)
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $account->account_number }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $account->account_name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $account->bank_name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $account->usage_count }}</td>
                            <td class="px-4 py-2">
                                @if($account->is_active)
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No account numbers assigned. System will use pool accounts.</p>
        @endif
    </div>

    <!-- Recent Payments -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-exchange-alt mr-2 text-primary"></i> Recent Payments
            </h3>
            <a href="{{ route('admin.payments.index', ['business_id' => $business->id]) }}" class="text-sm text-primary hover:underline">
                View All
            </a>
        </div>
        @if($business->payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Website</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($business->payments()->with('website')->latest()->take(10)->get() as $payment)
                        <tr>
                            <td class="px-4 py-2 text-sm">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-primary hover:underline">
                                    {{ $payment->transaction_id }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                @if($payment->website)
                                    <span class="text-xs" title="{{ $payment->website->website_url }}">
                                        {{ parse_url($payment->website->website_url, PHP_URL_HOST) }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">₦{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-2">
                                @if($payment->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($payment->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $payment->created_at->format('M d, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No payments yet.</p>
        @endif
    </div>

    <!-- Recent Withdrawals -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-hand-holding-usd mr-2 text-primary"></i> Recent Withdrawals
            </h3>
            <a href="{{ route('admin.withdrawals.index', ['business_id' => $business->id]) }}" class="text-sm text-primary hover:underline">
                View All
            </a>
        </div>
        @if($business->withdrawalRequests->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($business->withdrawalRequests->take(10) as $withdrawal)
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">₦{{ number_format($withdrawal->amount, 2) }}</td>
                            <td class="px-4 py-2">
                                @if($withdrawal->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($withdrawal->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                                @elseif($withdrawal->status === 'rejected')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Processed</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $withdrawal->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.withdrawals.show', $withdrawal) }}" class="text-sm text-primary hover:underline">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No withdrawals yet.</p>
        @endif
    </div>
</div>

<!-- Add Website Modal -->
<div id="addWebsiteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Website</h3>
        <form action="{{ route('admin.businesses.add-website', $business) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Website URL <span class="text-red-500">*</span></label>
                <input type="url" name="website_url" required 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="https://example.com">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL (Optional)</label>
                <input type="url" name="webhook_url" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="https://example.com/webhook">
                <p class="text-xs text-gray-500 mt-1">Payment notifications will be sent to this URL for this website.</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add any notes..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeAddWebsiteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Add Website
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Approve Website Modal -->
<div id="approveWebsiteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Approve Website</h3>
        
        @php
            $missingDocs = $business->getMissingKycDocuments();
            $allApproved = $business->hasAllKycDocumentsApproved();
        @endphp
        
        @if(!$business->hasAllRequiredKycDocuments() || !$allApproved)
        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5 mr-2"></i>
                <div class="flex-1">
                    <p class="text-sm font-medium text-yellow-800 mb-1">KYC Verification Incomplete</p>
                    @if(!$business->hasAllRequiredKycDocuments())
                        <p class="text-xs text-yellow-700 mb-2">Missing documents: {{ count($missingDocs) }}</p>
                        <ul class="list-disc list-inside text-xs text-yellow-700">
                            @foreach($missingDocs as $type)
                                <li>{{ \App\Models\BusinessVerification::getTypeLabel($type) }}</li>
                            @endforeach
                        </ul>
                    @elseif(!$allApproved)
                        <p class="text-xs text-yellow-700">Some documents are still under review or rejected.</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
        
        <form id="approveWebsiteForm" method="POST">
            @csrf
            <input type="hidden" name="website_id" id="approve_website_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Optional)</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add any notes about this approval..."></textarea>
            </div>
            
            @if(!$business->hasAllRequiredKycDocuments() || !$allApproved)
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="bypass_kyc" value="1" class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="ml-2 text-sm text-gray-700">Bypass KYC verification requirement</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-6">Check this to approve website even if KYC is incomplete</p>
            </div>
            @endif
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeApproveWebsiteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Approve Website
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Website Modal -->
<div id="rejectWebsiteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Revoke Website Approval</h3>
        <form id="rejectWebsiteForm" method="POST">
            @csrf
            <input type="hidden" name="website_id" id="reject_website_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Please provide a reason for revoking approval..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRejectWebsiteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Revoke Approval
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Website Modal -->
<div id="editWebsiteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Website</h3>
        <form id="editWebsiteForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="website_id" id="edit_website_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Website URL <span class="text-red-500">*</span></label>
                <input type="url" name="website_url" id="edit_website_url" required 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="https://example.com">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL (Optional)</label>
                <input type="url" name="webhook_url" id="edit_webhook_url" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="https://example.com/webhook">
                <p class="text-xs text-gray-500 mt-1">Payment notifications will be sent to this URL for this website.</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                <textarea name="notes" id="edit_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add any notes..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditWebsiteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Update Website
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Approve KYC Modal -->
<div id="approveKYCModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Approve KYC Verification</h3>
        <form id="approveKYCForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Optional)</label>
                <textarea name="admin_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add any notes about this approval..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeApproveKYCModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Approve Verification
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject KYC Modal -->
<div id="rejectKYCModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reject KYC Verification</h3>
        <form id="rejectKYCForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Please provide a reason for rejection..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRejectKYCModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Reject Verification
                </button>
            </div>
        </form>
    </div>
</div>

@if(auth('admin')->user()->canUpdateBusinessBalance())
<!-- Update Balance Modal -->
<div id="balanceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Update Balance</h3>
        <form action="{{ route('admin.businesses.update-balance', $business) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Balance</label>
                <p class="text-lg font-bold text-gray-900">₦{{ number_format($business->balance, 2) }}</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">New Balance <span class="text-red-500">*</span></label>
                <input type="number" name="balance" step="0.01" min="0" required value="{{ $business->balance }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Reason for balance update..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeBalanceModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    Update Balance
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@if(auth('admin')->user()->isSuperAdmin() && (\App\Models\Setting::get('transaction_transfer_enabled', config('transaction_transfer.enabled', true))))
<!-- Transfer Transactions Modal -->
<div id="transferTransactionsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transfer Transactions to Super Admin</h3>
            <form id="transferTransactionsForm" method="POST">
                @csrf
                <div class="mb-4 space-y-3">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                        <p class="text-xs text-blue-800 mb-2"><strong>Option 1:</strong> Filter by criteria (max amount, limit, dates)</p>
                        <p class="text-xs text-blue-800"><strong>Option 2:</strong> Specify target amount - system will find transactions that sum to that amount</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Amount (per transaction)</label>
                            <input type="number" id="max_amount" name="max_amount" step="0.01" min="0" placeholder="e.g., 5000"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Limit (number of transactions)</label>
                            <input type="number" id="limit" name="limit" min="1" placeholder="e.g., 20"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-bullseye text-orange-600 mr-1"></i> Target Amount to Transfer (Optional)
                        </label>
                        <input type="number" id="target_amount" name="target_amount" step="0.01" min="0" placeholder="e.g., 50000 - System will find transactions totaling this amount"
                            class="w-full px-3 py-2 border-2 border-orange-300 rounded-lg focus:ring-orange-500 focus:border-orange-500 bg-orange-50">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to use filters above. If set, system will find transactions that sum to this amount.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" id="from_date" name="from_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" id="to_date" name="to_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="approved">Approved Only</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                            <option value="">All Status</option>
                        </select>
                    </div>
                </div>
                <div id="transactionsPreview" class="mb-4 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3 hidden">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200">
                        <p class="text-sm font-medium text-gray-700">Preview (select transactions to transfer):</p>
                        <div class="text-right">
                            <p class="text-xs text-gray-600">Total Selected:</p>
                            <p id="totalAmountPreview" class="text-lg font-bold text-green-600">₦0.00</p>
                        </div>
                    </div>
                    <div id="transactionsList" class="space-y-2"></div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeTransferTransactionsModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" onclick="previewTransactions()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Preview
                    </button>
                    <button type="submit" id="transferBtn" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 hidden">
                        Transfer Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
function showAddWebsiteModal() {
    document.getElementById('addWebsiteModal').classList.remove('hidden');
}

function closeAddWebsiteModal() {
    document.getElementById('addWebsiteModal').classList.add('hidden');
}

function showEditWebsiteModal(websiteId, websiteUrl, webhookUrl, notes) {
    document.getElementById('edit_website_id').value = websiteId;
    document.getElementById('edit_website_url').value = websiteUrl;
    document.getElementById('edit_webhook_url').value = webhookUrl || '';
    document.getElementById('edit_notes').value = notes || '';
    document.getElementById('editWebsiteForm').action = '{{ route("admin.businesses.update-website", [$business, ":website"]) }}'.replace(':website', websiteId);
    document.getElementById('editWebsiteModal').classList.remove('hidden');
}

function closeEditWebsiteModal() {
    document.getElementById('editWebsiteModal').classList.add('hidden');
}

function showApproveWebsiteModal(websiteId) {
    document.getElementById('approve_website_id').value = websiteId;
    document.getElementById('approveWebsiteForm').action = '{{ route("admin.businesses.approve-website", $business) }}';
    document.getElementById('approveWebsiteModal').classList.remove('hidden');
}

function closeApproveWebsiteModal() {
    document.getElementById('approveWebsiteModal').classList.add('hidden');
}

function showRejectWebsiteModal(websiteId) {
    document.getElementById('reject_website_id').value = websiteId;
    document.getElementById('rejectWebsiteForm').action = '{{ route("admin.businesses.reject-website", $business) }}';
    document.getElementById('rejectWebsiteModal').classList.remove('hidden');
}

function closeRejectWebsiteModal() {
    document.getElementById('rejectWebsiteModal').classList.add('hidden');
}

function showApproveKYCModal(verificationId) {
    const form = document.getElementById('approveKYCForm');
    form.action = '{{ route("admin.businesses.verification.approve", [$business, ":id"]) }}'.replace(':id', verificationId);
    document.getElementById('approveKYCModal').classList.remove('hidden');
}

function closeApproveKYCModal() {
    document.getElementById('approveKYCModal').classList.add('hidden');
}

function showRejectKYCModal(verificationId) {
    const form = document.getElementById('rejectKYCForm');
    form.action = '{{ route("admin.businesses.verification.reject", [$business, ":id"]) }}'.replace(':id', verificationId);
    document.getElementById('rejectKYCModal').classList.remove('hidden');
}

function closeRejectKYCModal() {
    document.getElementById('rejectKYCModal').classList.add('hidden');
}

function showBalanceModal() {
    document.getElementById('balanceModal').classList.remove('hidden');
}

function closeBalanceModal() {
    document.getElementById('balanceModal').classList.add('hidden');
}

let currentWebsiteId = null;
let selectedPaymentIds = [];

function showTransferTransactionsModal(websiteId) {
    currentWebsiteId = websiteId;
    document.getElementById('transferTransactionsModal').classList.remove('hidden');
}

function closeTransferTransactionsModal() {
    document.getElementById('transferTransactionsModal').classList.add('hidden');
    selectedPaymentIds = [];
    document.getElementById('transactionsPreview').classList.add('hidden');
    document.getElementById('transferBtn').classList.add('hidden');
}

function previewTransactions() {
    const maxAmount = document.getElementById('max_amount').value;
    const limit = document.getElementById('limit').value;
    const targetAmount = document.getElementById('target_amount').value;
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date').value;
    const status = document.getElementById('status').value;
    
    let url = `/admin/businesses/{{ $business->id }}/websites/${currentWebsiteId}/transactions/preview?`;
    if (targetAmount) {
        url += `target_amount=${targetAmount}&`;
    } else {
        url += `max_amount=${maxAmount || ''}&limit=${limit || ''}&`;
    }
    url += `from_date=${fromDate || ''}&to_date=${toDate || ''}&status=${status || ''}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            const listDiv = document.getElementById('transactionsList');
            listDiv.innerHTML = '';
            selectedPaymentIds = [];
            
            if (data.transactions && data.transactions.length > 0) {
                let totalAmount = 0;
                data.transactions.forEach(payment => {
                    const amount = parseFloat(payment.business_receives || payment.amount);
                    totalAmount += amount;
                    
                    const div = document.createElement('div');
                    div.className = 'flex items-center justify-between p-2 border border-gray-200 rounded hover:bg-gray-50';
                    div.innerHTML = `
                        <div class="flex items-center space-x-3 flex-1">
                            <input type="checkbox" value="${payment.id}" class="payment-checkbox" checked>
                            <div class="flex-1">
                                <p class="text-sm font-medium">${payment.transaction_id}</p>
                                <p class="text-xs text-gray-500">₦${amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} - ${payment.status} - ${new Date(payment.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                    `;
                    listDiv.appendChild(div);
                    selectedPaymentIds.push(payment.id);
                });
                
                // Update total amount display
                document.getElementById('totalAmountPreview').textContent = '₦' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                // Show target amount comparison if target was specified
                if (targetAmount && data.total_amount) {
                    const target = parseFloat(targetAmount);
                    const actual = parseFloat(data.total_amount);
                    const diff = Math.abs(target - actual);
                    const diffPercent = target > 0 ? ((diff / target) * 100).toFixed(1) : 0;
                    
                    let diffText = '';
                    if (diff < 0.01) {
                        diffText = '<span class="text-green-600">✓ Exact match!</span>';
                    } else if (diffPercent < 5) {
                        diffText = `<span class="text-green-600">Very close (${diffPercent}% difference)</span>`;
                    } else {
                        diffText = `<span class="text-orange-600">${diffPercent}% difference from target</span>`;
                    }
                    
                    const targetInfo = document.createElement('div');
                    targetInfo.className = 'mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-xs';
                    targetInfo.innerHTML = `
                        <p><strong>Target:</strong> ₦${target.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Actual:</strong> ₦${actual.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p>${diffText}</p>
                    `;
                    listDiv.insertBefore(targetInfo, listDiv.firstChild);
                }
                
                document.getElementById('transactionsPreview').classList.remove('hidden');
                document.getElementById('transferBtn').classList.remove('hidden');
            } else {
                alert('No transactions found matching the criteria.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading transactions preview.');
        });
}

function updateTotalAmount() {
    // Recalculate total when checkboxes change
    const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
    let total = 0;
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('div.flex');
        if (row) {
            const amountText = row.querySelector('.text-xs')?.textContent;
            if (amountText) {
                const match = amountText.match(/₦([\d,]+\.?\d*)/);
                if (match) {
                    total += parseFloat(match[1].replace(/,/g, ''));
                }
            }
        }
    });
    
    document.getElementById('totalAmountPreview').textContent = '₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('payment-checkbox')) {
        const paymentId = parseInt(e.target.value);
        if (e.target.checked) {
            if (!selectedPaymentIds.includes(paymentId)) {
                selectedPaymentIds.push(paymentId);
            }
        } else {
            selectedPaymentIds = selectedPaymentIds.filter(id => id !== paymentId);
        }
        updateTotalAmount();
    }
});

document.getElementById('transferTransactionsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (selectedPaymentIds.length === 0) {
        alert('Please select at least one transaction to transfer.');
        return;
    }
    
    const route = currentWebsiteId 
        ? '{{ route("admin.businesses.websites.transfer-transactions", [$business, ":website"]) }}'.replace(':website', currentWebsiteId)
        : '{{ route("admin.businesses.transfer-transactions", $business) }}';
    
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    formData.append('payment_ids', JSON.stringify(selectedPaymentIds));
    
    fetch(route, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Transactions transferred successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error transferring transactions.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error transferring transactions.');
    });
});

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('bg-black')) {
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.classList.add('hidden');
        });
    }
});
</script>
@endpush
@endsection
