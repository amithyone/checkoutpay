@extends('layouts.admin')

@section('title', $policy->exists ? 'Edit policy' : 'New policy')
@section('page-title', $policy->exists ? 'Edit desktop policy' : 'New desktop policy')

@section('content')
<div class="max-w-2xl bg-white border border-gray-200 rounded-lg p-6">
    @if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ $policy->exists ? route('admin.desktop-telemetry.policies.update', $policy) : route('admin.desktop-telemetry.policies.store') }}" class="space-y-4">
        @csrf
        @if($policy->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant id</label>
                <input type="text" name="tenant_id" value="{{ old('tenant_id', $policy->tenant_id ?? 'default-tenant') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @error('tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Scope type</label>
                <select name="scope_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="instance" @selected(old('scope_type', $policy->scope_type) === 'instance')>Instance</option>
                    <option value="role" @selected(old('scope_type', $policy->scope_type) === 'role')>Role (admin / player)</option>
                    <option value="global" @selected(old('scope_type', $policy->scope_type) === 'global')>Global</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Scope value</label>
                <input type="text" name="scope_value" value="{{ old('scope_value', $policy->scope_value) }}" required placeholder="e.g. instance-id, admin, player, *" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <p class="text-xs text-gray-500 mt-1">For "global" use <code>*</code>. For role use <code>admin</code> or <code>player</code>. For instance use the desktop appInstanceId.</p>
            </div>
        </div>

        <hr>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="locked" value="0">
            <input type="checkbox" name="locked" value="1" class="rounded border-gray-300" @checked(old('locked', $policy->locked))>
            Place this scope on a service hold (lock playback / admin)
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Lock reason code</label>
                <input type="text" name="lock_reason_code" value="{{ old('lock_reason_code', $policy->lock_reason_code) }}" maxlength="80" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="e.g. service_review">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min heartbeat (seconds)</label>
                <input type="number" name="min_heartbeat_seconds" min="30" max="86400" value="{{ old('min_heartbeat_seconds', $policy->min_heartbeat_seconds ?: 300) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Lock at</label>
                <input type="datetime-local" name="lock_at" value="{{ old('lock_at', optional($policy->lock_at)->format('Y-m-d\TH:i')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Grace until</label>
                <input type="datetime-local" name="grace_until" value="{{ old('grace_until', optional($policy->grace_until)->format('Y-m-d\TH:i')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Admin notes</label>
            <textarea name="admin_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('admin_notes', $policy->admin_notes) }}</textarea>
        </div>

        <div class="flex justify-between items-center">
            <a href="{{ route('admin.desktop-telemetry.policies.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
            <button class="px-4 py-2 bg-primary text-white rounded-lg text-sm">{{ $policy->exists ? 'Save changes' : 'Create policy' }}</button>
        </div>
    </form>
</div>
@endsection
