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
            $sampleCharges = $chargeService->calculateCharges(10000, $business);
        @endphp
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-xs font-semibold text-blue-900 mb-2">Sample Calculation (₦10,000 payment):</p>
            <div class="text-xs text-blue-800 space-y-1">
                <p>Original Amount: ₦{{ number_format($sampleCharges['original_amount'], 2) }}</p>
                <p>Percentage Charge ({{ $sampleCharges['charge_percentage'] > 0 ? number_format($chargeService->getChargePercentage($business), 2) : '0' }}%): ₦{{ number_format($sampleCharges['charge_percentage'], 2) }}</p>
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
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-id-card mr-2 text-primary"></i> KYC Verification
            </h3>
            <div>
                @if($business->verifications->count() > 0)
                    @php
                        $latestVerification = $business->verifications->first();
                    @endphp
                    @if($latestVerification->status === 'approved')
                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                            <i class="fas fa-check-circle mr-1"></i> Verified
                        </span>
                    @elseif(in_array($latestVerification->status, ['pending', 'under_review']))
                        <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                            <i class="fas fa-clock mr-1"></i> Under Review
                        </span>
                    @else
                        <span class="px-3 py-1 text-sm font-medium bg-red-100 text-red-800 rounded-full">
                            <i class="fas fa-times-circle mr-1"></i> Rejected
                        </span>
                    @endif
                @else
                    <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">
                        <i class="fas fa-exclamation-circle mr-1"></i> Not Submitted
                    </span>
                @endif
            </div>
        </div>

        @if($business->verifications->count() > 0)
            <div class="space-y-4">
                @foreach($business->verifications as $verification)
                <div class="border border-gray-200 rounded-lg p-4 {{ $verification->status === 'approved' ? 'bg-green-50' : ($verification->status === 'rejected' ? 'bg-red-50' : 'bg-yellow-50') }}">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <h4 class="text-sm font-semibold text-gray-900">
                                    {{ ucfirst(str_replace('_', ' ', $verification->verification_type)) }} Verification
                                </h4>
                                @if($verification->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>
                                @elseif($verification->status === 'rejected')
                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>
                                @elseif($verification->status === 'under_review')
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Under Review</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Pending</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-xs text-gray-600">
                                <div>
                                    <span class="font-medium">Document Type:</span> {{ ucfirst(str_replace('_', ' ', $verification->document_type ?? 'N/A')) }}
                                </div>
                                <div>
                                    <span class="font-medium">Submitted:</span> {{ $verification->created_at->format('M d, Y H:i') }}
                                </div>
                                @if($verification->reviewed_at)
                                <div>
                                    <span class="font-medium">Reviewed:</span> {{ $verification->reviewed_at->format('M d, Y H:i') }}
                                </div>
                                @if($verification->reviewer)
                                <div>
                                    <span class="font-medium">Reviewed By:</span> {{ $verification->reviewer->name }}
                                </div>
                                @endif
                                @endif
                            </div>
                            @if($verification->admin_notes)
                            <div class="mt-2 p-2 bg-white rounded border border-gray-200">
                                <p class="text-xs text-gray-600"><strong>Admin Notes:</strong> {{ $verification->admin_notes }}</p>
                            </div>
                            @endif
                            @if($verification->rejection_reason)
                            <div class="mt-2 p-2 bg-red-100 rounded border border-red-200">
                                <p class="text-xs text-red-800"><strong>Rejection Reason:</strong> {{ $verification->rejection_reason }}</p>
                            </div>
                            @endif
                        </div>
                        <div class="ml-4 flex flex-col space-y-2">
                            @if($verification->document_path)
                                <a href="{{ route('admin.businesses.verification.download', [$business, $verification]) }}" 
                                   class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs text-center">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                            @endif
                            @if(in_array($verification->status, ['pending', 'under_review']))
                                <button onclick="showApproveKYCModal({{ $verification->id }})" 
                                        class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-xs">
                                    <i class="fas fa-check mr-1"></i> Approve
                                </button>
                                <button onclick="showRejectKYCModal({{ $verification->id }})" 
                                        class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-xs">
                                    <i class="fas fa-times mr-1"></i> Reject
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8">
                <i class="fas fa-id-card text-gray-300 text-4xl mb-3"></i>
                <p class="text-sm text-gray-500">No KYC verification documents submitted yet.</p>
            </div>
        @endif
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
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Approve Website</h3>
        <form id="approveWebsiteForm" method="POST">
            @csrf
            <input type="hidden" name="website_id" id="approve_website_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Optional)</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm" 
                    placeholder="Add any notes about this approval..."></textarea>
            </div>
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

@push('scripts')
<script>
function showAddWebsiteModal() {
    document.getElementById('addWebsiteModal').classList.remove('hidden');
}

function closeAddWebsiteModal() {
    document.getElementById('addWebsiteModal').classList.add('hidden');
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
