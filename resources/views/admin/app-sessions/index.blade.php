@extends('layouts.admin')

@section('title', 'App sessions')
@section('page-title', 'WhatsApp wallet — App sessions')

@section('content')
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">App sessions</h3>
            <p class="text-sm text-gray-600 mt-1">
                Mobile app sign-ins and activity. Sessions are created on the server at login even when the app does not send a session id.
            </p>
        </div>
        <div class="flex gap-2 text-sm">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800 font-medium">
                {{ number_format($activeCount) }} active now
            </span>
            <a href="{{ route('admin.app-sessions.events') }}" class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-stream mr-2"></i> All events
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.app-sessions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Phone, session UUID, device…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="ended" @selected(request('status') === 'ended')>Ended</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Platform</label>
                <select name="platform" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="ios" @selected(request('platform') === 'ios')>iOS</option>
                    <option value="android" @selected(request('platform') === 'android')>Android</option>
                    <option value="web" @selected(request('platform') === 'web')>Web</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Login method</label>
                <select name="login_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="pin" @selected(request('login_method') === 'pin')>PIN</option>
                    <option value="otp" @selected(request('login_method') === 'otp')>OTP</option>
                    <option value="passkey" @selected(request('login_method') === 'passkey')>Passkey</option>
                    <option value="device_bind" @selected(request('login_method') === 'device_bind')>Device bind</option>
                    <option value="register" @selected(request('login_method') === 'register')>Register</option>
                </select>
            </div>
            <div class="flex items-end gap-2 lg:col-span-6">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm">Filter</button>
                <a href="{{ route('admin.app-sessions.index') }}" class="text-sm text-gray-600 py-2">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Started</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Phone</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Login</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Platform</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Device</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Last seen</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sessions as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $s->started_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $s->phone_e164 }}</td>
                            <td class="px-4 py-3">{{ $s->loginMethodLabel() }}</td>
                            <td class="px-4 py-3 capitalize">{{ $s->platform ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-[10rem] truncate" title="{{ $s->device_label }}">{{ $s->device_label ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $s->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($s->isActive())
                                    <span class="text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">Active</span>
                                @else
                                    <span class="text-xs text-gray-500">Ended {{ $s->ended_at?->diffForHumans() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.app-sessions.show', $s) }}" class="text-primary hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No app sessions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $sessions->links() }}</div>
        @endif
    </div>
</div>
@endsection
