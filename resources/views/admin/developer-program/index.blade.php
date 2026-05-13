@extends('layouts.admin')

@section('title', 'Developer program')
@section('page-title', 'Developer program')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            <i class="fas fa-handshake mr-2 text-primary"></i>Published fee share
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            These values are shown on the marketing <a href="{{ route('developers.program') }}" target="_blank" rel="noopener" class="text-primary hover:underline">Developer Program</a> page so the percentage always matches what you configure here.
        </p>
        @if(auth('admin')->user()->canManageSettings())
        <form action="{{ route('admin.developer-program.settings.update') }}" method="post" class="space-y-4 max-w-2xl">
            @csrf
            @method('PUT')
            <div>
                <label for="developer_program_fee_share_percent" class="block text-sm font-medium text-gray-700 mb-1">Default partner share (% of our fee)</label>
                <input type="number" step="0.01" min="0" max="100" name="developer_program_fee_share_percent" id="developer_program_fee_share_percent"
                    value="{{ old('developer_program_fee_share_percent', $globalFeeSharePercent) }}"
                    class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                <p class="mt-1 text-xs text-gray-500">Example: <code class="bg-gray-100 px-1 rounded">10</code> means approved partners earn 10% of {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}’s transaction-fee revenue on qualifying attributed volume. Leave empty to hide the numeric rate on the public page.</p>
                @error('developer_program_fee_share_percent')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="developer_program_fee_share_base_description" class="block text-sm font-medium text-gray-700 mb-1">What the % applies to (short phrase)</label>
                <input type="text" name="developer_program_fee_share_base_description" id="developer_program_fee_share_base_description" maxlength="500"
                    value="{{ old('developer_program_fee_share_base_description', $feeShareBaseDescription) }}"
                    placeholder="e.g. CheckoutPay’s transaction fee revenue on qualifying attributed volume"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                @error('developer_program_fee_share_base_description')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium">
                <i class="fas fa-save mr-2"></i>Save published terms
            </button>
        </form>
        @else
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                Only admins with settings access can change the published percentage. Current global default:
                <strong>{{ $globalFeeSharePercent !== null && $globalFeeSharePercent !== '' ? rtrim(rtrim(number_format((float) $globalFeeSharePercent, 2, '.', ''), '0'), '.').'%' : 'not set' }}</strong>
            </p>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Applications</h3>
            <p class="text-sm text-gray-600">Set status to <strong>approved</strong> before revenue share can apply. Optional <em>custom %</em> overrides the global default for that developer only (not shown on the public page).</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Applicant</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Contact</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Community</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Business ID</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Applied</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Manage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($applications as $app)
                        <tr class="align-top">
                            <td class="px-3 py-3">
                                <p class="font-medium text-gray-900">{{ $app->name }}</p>
                                <p class="text-xs text-gray-500">#{{ $app->id }}</p>
                            </td>
                            <td class="px-3 py-3 text-gray-700">
                                <p>{{ $app->email }}</p>
                                <p class="text-xs text-gray-500">Phone: {{ $app->phone }}</p>
                                <p class="text-xs text-gray-500">WhatsApp: {{ $app->whatsapp }}</p>
                            </td>
                            <td class="px-3 py-3 text-gray-700">{{ $app->community_preference }}</td>
                            <td class="px-3 py-3 text-gray-700 font-mono text-xs">{{ $app->business_id ?: '—' }}</td>
                            <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $app->created_at?->format('M j, Y H:i') }}</td>
                            <td class="px-3 py-3 min-w-[280px]">
                                <form action="{{ route('admin.developer-program.applications.update', $app) }}" method="post" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex flex-wrap gap-2 items-center">
                                        <select name="status" class="border border-gray-300 rounded px-2 py-1 text-sm">
                                            <option value="pending" @selected($app->status === 'pending')>Pending</option>
                                            <option value="approved" @selected($app->status === 'approved')>Approved</option>
                                            <option value="rejected" @selected($app->status === 'rejected')>Rejected</option>
                                        </select>
                                        <input type="number" step="0.01" min="0" max="100" name="partner_fee_share_percent" placeholder="Custom %"
                                            value="{{ $app->partner_fee_share_percent }}"
                                            class="w-24 border border-gray-300 rounded px-2 py-1 text-sm" title="Leave blank to use global published %">
                                    </div>
                                    <textarea name="admin_notes" rows="2" placeholder="Internal notes" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">{{ $app->admin_notes }}</textarea>
                                    @php
                                        $globalForEff = $globalFeeSharePercent !== null && $globalFeeSharePercent !== '' ? (float) $globalFeeSharePercent : null;
                                        $effectivePct = $app->effectiveFeeSharePercent($globalForEff);
                                    @endphp
                                    <p class="text-xs text-gray-500">Effective share:
                                        <strong>{{ $effectivePct !== null ? rtrim(rtrim(number_format($effectivePct, 2, '.', ''), '0'), '.').'%' : '—' }}</strong>
                                        @if($globalForEff !== null)
                                            <span class="text-gray-400">(global {{ rtrim(rtrim(number_format($globalForEff, 2, '.', ''), '0'), '.') }}%)</span>
                                        @endif
                                    </p>
                                    <button type="submit" class="text-xs bg-gray-800 text-white px-3 py-1 rounded hover:bg-gray-700">Save row</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
