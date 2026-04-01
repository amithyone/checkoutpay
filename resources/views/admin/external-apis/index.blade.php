@extends('layouts.admin')

@section('title', 'External APIs')
@section('page-title', 'External APIs')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add External API</h3>
        <form action="{{ route('admin.external-apis.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="MEVONPAY" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Provider key</label>
                <input type="text" name="provider_key" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="mevonpay" required>
            </div>
            <div class="flex items-end">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" checked class="mr-2">
                    <span class="text-sm text-gray-700">Active</span>
                </label>
            </div>
            <div class="flex items-end justify-end">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Add API</button>
            </div>
        </form>
    </div>

    @foreach($externalApis as $api)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $api->name }}</h3>
                <p class="text-xs text-gray-500">Key: {{ $api->provider_key }}</p>
            </div>
            <span class="px-2 py-1 text-xs rounded-full {{ $api->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                {{ $api->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form action="{{ route('admin.external-apis.update-businesses', $api) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ $api->is_active ? 'checked' : '' }} class="mr-2">
                    <span class="text-sm text-gray-700">API enabled</span>
                </label>
            </div>

            <label class="block text-sm font-medium text-gray-700 mb-2">Businesses + services + mode</label>
            <div class="max-h-[28rem] overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-4">
                @php
                    $assignedMap = [];
                    foreach($api->businesses as $biz) {
                        $pivotServices = $biz->pivot->services;
                        if (is_string($pivotServices)) {
                            $pivotServices = json_decode($pivotServices, true) ?: [];
                        }
                        $assignedMap[$biz->id] = [
                            'mode' => $biz->pivot->assignment_mode ?? 'hybrid',
                            'services' => is_array($pivotServices) ? $pivotServices : [],
                            'va_generation_mode' => $biz->pivot->va_generation_mode ?? 'dynamic',
                        ];
                    }
                @endphp
                @forelse($businesses as $business)
                    @php
                        $assigned = $assignedMap[$business->id] ?? null;
                        $enabled = $assigned !== null;
                        $mode = $assigned['mode'] ?? 'hybrid';
                        $selectedServices = $assigned['services'] ?? [];
                        $vaGenerationMode = $assigned['va_generation_mode'] ?? 'dynamic';
                    @endphp
                    <div class="border border-gray-100 rounded-lg p-3">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <span class="text-sm text-gray-700">{{ $business->name }} <span class="text-xs text-gray-500">({{ $business->email }})</span></span>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="configs[{{ $business->id }}][enabled]" value="1" {{ $enabled ? 'checked' : '' }}>
                                <span class="text-xs text-gray-600">Enable</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Mode</label>
                                <select name="configs[{{ $business->id }}][mode]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                    <option value="external_only" {{ $mode === 'external_only' ? 'selected' : '' }}>External only</option>
                                    <option value="hybrid" {{ $mode === 'hybrid' ? 'selected' : '' }}>Hybrid (external + internal fallback)</option>
                                    <option value="internal_only" {{ $mode === 'internal_only' ? 'selected' : '' }}>Internal only</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">MEVONPAY VA Type</label>
                                <select name="configs[{{ $business->id }}][va_generation_mode]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                    <option value="dynamic" {{ $vaGenerationMode === 'dynamic' ? 'selected' : '' }}>Dynamic (createdynamic)</option>
                                    <option value="temp" {{ $vaGenerationMode === 'temp' ? 'selected' : '' }}>Temporary (createtempva)</option>
                                </select>
                                <p class="text-[11px] text-gray-500 mt-1">
                                    Temporary requires provider input like BVN/fname/lname (if needed).
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Services</label>
                                <div class="flex flex-wrap gap-3">
                                    @foreach($serviceOptions as $svc)
                                        <label class="flex items-center gap-1 text-xs text-gray-700">
                                            <input type="checkbox" name="configs[{{ $business->id }}][services][]" value="{{ $svc }}" {{ in_array($svc, $selectedServices, true) ? 'checked' : '' }}>
                                            <span>{{ $svc }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-[11px] text-gray-500 mt-1">If none selected, mode applies to all services.</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No active businesses found.</p>
                @endforelse
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Save Business List</button>
            </div>
        </form>
    </div>
    @endforeach
</div>
@endsection
