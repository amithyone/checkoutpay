@extends('layouts.admin')

@section('title', 'Desktop app tokens')
@section('page-title', 'Desktop app tokens')

@section('content')
<div class="space-y-4 max-w-5xl">
    @if(session('success'))<div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif

    @if(session('new_token_bearer'))
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm space-y-2">
            <p class="font-semibold text-amber-900">Copy these credentials now. They are shown only once.</p>
            <p class="text-xs text-amber-900">Bearer token (Authorization header):</p>
            <pre class="bg-white border border-amber-200 rounded p-2 text-xs overflow-x-auto">Bearer {{ session('new_token_bearer') }}</pre>
            <p class="text-xs text-amber-900">HMAC secret (for X-Amithy-Signature):</p>
            <pre class="bg-white border border-amber-200 rounded p-2 text-xs overflow-x-auto">{{ session('new_token_secret') }}</pre>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <p class="text-sm font-semibold text-gray-900">Create new app token</p>
        <form method="POST" action="{{ route('admin.desktop-telemetry.tokens.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
            @csrf
            <input type="text" name="name" placeholder="Friendly name" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm md:col-span-2">
            <input type="text" name="tenant_id" value="default-tenant" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <button class="px-3 py-2 bg-primary text-white rounded-lg text-sm">Create</button>
            <textarea name="admin_notes" rows="2" placeholder="Notes (optional)" class="px-3 py-2 border border-gray-300 rounded-lg text-sm md:col-span-4"></textarea>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Name</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Tenant</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Active</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Last seen</th>
                    <th class="px-4 py-3 text-right text-gray-600 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($tokens as $t)
                    <tr>
                        <td class="px-4 py-2">
                            <p class="font-medium">{{ $t->name }}</p>
                            @if($t->admin_notes)<p class="text-xs text-gray-500 whitespace-pre-wrap">{{ $t->admin_notes }}</p>@endif
                        </td>
                        <td class="px-4 py-2 text-xs">{{ $t->tenant_id }}</td>
                        <td class="px-4 py-2">
                            @if($t->is_active)
                                <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs">Active</span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-gray-200 text-gray-700 text-xs">Disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs">{{ $t->last_seen_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <form action="{{ route('admin.desktop-telemetry.tokens.toggle', $t) }}" method="POST" class="inline">@csrf<button class="text-xs text-gray-600 hover:underline">{{ $t->is_active ? 'Disable' : 'Enable' }}</button></form>
                            <form action="{{ route('admin.desktop-telemetry.tokens.rotate', $t) }}" method="POST" class="inline" onsubmit="return confirm('Rotate token + secret? Old credentials stop working immediately.');">@csrf<button class="text-xs text-amber-700 hover:underline">Rotate</button></form>
                            <form action="{{ route('admin.desktop-telemetry.tokens.destroy', $t) }}" method="POST" class="inline" onsubmit="return confirm('Delete token?');">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No tokens yet. The desktop app can fall back to AMITHY_API_TOKEN until you create one.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $tokens->links() }}</div>
    </div>
</div>
@endsection
