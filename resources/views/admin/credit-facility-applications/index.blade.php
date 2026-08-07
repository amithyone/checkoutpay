@extends('layouts.admin')

@section('title', 'Loan & overdraft requests')
@section('page-title', 'Loan & overdraft requests')

@section('content')
<div class="space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
        <p>
            <strong class="text-gray-900">Master loan account</strong> holds the float.
            For <strong>overdraft</strong>: when the business draws (balance goes negative), that master account is debited;
            when they repay, the master is credited back.
            For <strong>loan</strong>: approving debits the master and credits the borrower immediately.
        </p>
        <p class="mt-2 text-xs text-gray-500">
            Overdraft volume rules (last ~90 days outbound): under ₦5m = not tier-eligible;
            ≥ ₦5m = Tier 1 (max limit ₦5m); ≥ ₦10m = Tier 2 (max limit ₦10m). Approval cannot exceed the tier max.
        </p>
        @if($masters->isEmpty())
            <p class="mt-2 text-amber-700">No master loan accounts yet. Open a business and enable “Master loan account”, or ensure the capital reserve business ({{ \App\Models\Setting::get('overdraft_capital_reserve_email', 'admin@check-outpay.com') }}) exists.</p>
        @else
            <p class="mt-2 text-xs text-gray-500">
                Master accounts:
                @foreach($masters as $m)
                    <span class="inline-block mr-2">{{ $m->name }} (₦{{ number_format((float) $m->balance, 2) }})</span>
                @endforeach
            </p>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.credit-facility-applications.index', ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-lg border {{ $status === $key ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">When</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Kind</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Requester</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Amount</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($applications as $row)
                    <tr class="align-top">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $row->created_at?->format('M d, Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $row->kind === 'loan' ? 'bg-blue-50 text-blue-800' : 'bg-amber-50 text-amber-800' }}">
                                {{ ucfirst($row->kind) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($row->business)
                                <p class="font-medium text-gray-900">{{ $row->business->name }}</p>
                                <p class="text-xs text-gray-500">{{ $row->business->email }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    90d vol: ₦{{ number_format((float) $row->business->overdraft_volume_90d, 0) }}
                                    @if($row->business->overdraft_volume_tier)
                                        · {{ str_replace('_', ' ', $row->business->overdraft_volume_tier) }}
                                        · max ₦{{ number_format(app(\App\Services\Credit\OverdraftEligibilityService::class)->tierMaxLimit($row->business->overdraft_volume_tier), 0) }}
                                    @else
                                        · below tier (need ≥ ₦5m)
                                    @endif
                                </p>
                                <a href="{{ route('admin.businesses.show', $row->business) }}" class="text-xs text-primary hover:underline">Business</a>
                            @elseif($row->wallet)
                                <p class="font-medium text-gray-900">Wallet {{ $row->wallet->phone_e164 }}</p>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                            @if($row->note)
                                <p class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($row->note, 100) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">₦{{ number_format((float) $row->amount, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="capitalize">{{ $row->status }}</span>
                            @if($row->funder)
                                <p class="text-xs text-gray-500 mt-1">Master: {{ $row->funder->name }}</p>
                            @endif
                            @if($row->approved_amount)
                                <p class="text-xs text-gray-500">Approved: ₦{{ number_format((float) $row->approved_amount, 2) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($row->status === 'pending' && auth('admin')->user()?->isSuperAdmin())
                                <form action="{{ route('admin.credit-facility-applications.approve', $row) }}" method="POST" class="space-y-2 mb-3 p-3 bg-gray-50 border rounded-lg">
                                    @csrf
                                    <div>
                                        <label class="text-xs text-gray-600">Master loan account</label>
                                        <select name="funder_business_id" class="w-full text-sm border rounded px-2 py-1" required>
                                            <option value="">Select…</option>
                                            @foreach($masters as $m)
                                                <option value="{{ $m->id }}">{{ $m->name }} — ₦{{ number_format((float) $m->balance, 2) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Approve amount (₦)</label>
                                        <input type="number" step="0.01" min="1" name="approved_amount" value="{{ (float) $row->amount }}" class="w-full text-sm border rounded px-2 py-1" required>
                                    </div>
                                    @if($row->kind === 'overdraft')
                                        <div>
                                            <label class="text-xs text-gray-600">Overdraft limit (₦, optional)</label>
                                            <input type="number" step="0.01" min="1" name="overdraft_limit" value="{{ (float) $row->amount }}" class="w-full text-sm border rounded px-2 py-1">
                                            <p class="text-[11px] text-gray-500 mt-0.5">Master is debited only when they draw, credited when they repay.</p>
                                        </div>
                                    @else
                                        <p class="text-[11px] text-gray-500">Loan: master is debited and borrower credited immediately.</p>
                                    @endif
                                    <div>
                                        <label class="text-xs text-gray-600">Admin notes</label>
                                        <input type="text" name="admin_notes" class="w-full text-sm border rounded px-2 py-1" maxlength="2000">
                                    </div>
                                    <button type="submit" class="w-full text-xs px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700">Approve</button>
                                </form>
                                <form action="{{ route('admin.credit-facility-applications.reject', $row) }}" method="POST" onsubmit="return confirm('Reject this request?');">
                                    @csrf
                                    <button type="submit" class="text-xs px-3 py-1.5 border border-red-200 text-red-700 rounded hover:bg-red-50">Reject</button>
                                </form>
                            @elseif($row->status === 'pending')
                                <span class="text-xs text-gray-500">Super admin only</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No {{ $status === 'all' ? '' : $status.' ' }}requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">{{ $applications->links() }}</div>
    </div>

    @if(($legacyPending ?? collect())->isNotEmpty())
        <div class="bg-white rounded-lg border border-amber-200 p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Legacy pending overdrafts (business dashboard)</h3>
            <p class="text-xs text-gray-500 mb-3">These were submitted before the app credit-facility flow. Review on the business page.</p>
            <ul class="text-sm space-y-1">
                @foreach($legacyPending as $b)
                    <li>
                        <a href="{{ route('admin.businesses.show', $b) }}" class="text-primary hover:underline">{{ $b->name }}</a>
                        · ₦{{ number_format((float) ($b->overdraft_requested_amount ?: 0), 2) }}
                        · {{ $b->overdraft_requested_at?->format('M d, Y H:i') }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
