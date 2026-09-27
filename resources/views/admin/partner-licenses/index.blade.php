@extends('layouts.admin')

@section('title', 'Partner licenses')
@section('page-title', 'Partner licenses')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    @unless($issuerEnabled)
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3 text-sm">
            Set <code class="text-xs bg-amber-100 px-1 rounded">PARTNER_LICENSE_ISSUER_ENABLED=true</code> on check-outpay.com (issuer) to create keys. Partner drops use <code class="text-xs bg-amber-100 px-1 rounded">PARTNER_LICENSE_ENFORCED=true</code> instead.
        </div>
    @endunless

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Issue partner license</h3>
        <form action="{{ route('admin.partner-licenses.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Partner name</label>
                <input type="text" name="partner_name" value="{{ old('partner_name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valid until (optional)</label>
                <input type="date" name="valid_until" value="{{ old('valid_until') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Allowed hosts (comma or space separated; empty = any)</label>
                <input type="text" name="allowed_hosts" value="{{ old('allowed_hosts') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="pay.partner.com, *.partner.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Minimum build version</label>
                <input type="text" name="min_build_version" value="{{ old('min_build_version', '1.0.0') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90" @disabled(! $issuerEnabled)>Create license</button>
            </div>
        </form>
        <p class="text-xs text-gray-500 mt-3">Latest build advertised to partners: <code>{{ config('partner_license.latest_build_version') ?: '— set PARTNER_LICENSE_LATEST_BUILD_VERSION' }}</code></p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Partner</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Key</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Last ping</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($licenses as $license)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $license->partner_name }}</div>
                            @if($license->notes)
                                <div class="text-xs text-gray-500">{{ $license->notes }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs break-all">{{ $license->license_key }}</td>
                        <td class="px-4 py-3">
                            @if($license->isRevoked())
                                <span class="text-red-700">Revoked</span>
                            @elseif($license->isExpired())
                                <span class="text-amber-700">Expired</span>
                            @else
                                <span class="text-green-700">Active</span>
                            @endif
                            <div class="text-xs text-gray-500">min build {{ $license->min_build_version }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            @if($license->last_ping_at)
                                {{ $license->last_ping_at->timezone('Africa/Lagos')->format('Y-m-d H:i') }}
                                @if($license->last_ping_host)
                                    <br>{{ $license->last_ping_host }}
                                @endif
                                @if($license->last_build_version)
                                    <br>build {{ $license->last_build_version }}
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @unless($license->isRevoked())
                                <form action="{{ route('admin.partner-licenses.revoke', $license) }}" method="POST" onsubmit="return confirm('Revoke this license?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Revoke</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No partner licenses yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
