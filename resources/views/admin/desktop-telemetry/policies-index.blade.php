@extends('layouts.admin')

@section('title', 'Desktop policies')
@section('page-title', 'Desktop policies')

@section('content')
<div class="space-y-4">
    @if(session('success'))<div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>@endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">Policies are evaluated by precedence: instance &gt; role &gt; global. The most specific match wins.</p>
        <a href="{{ route('admin.desktop-telemetry.policies.create') }}" class="px-3 py-1.5 bg-primary text-white text-sm rounded-lg">New policy</a>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Tenant</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Scope</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Locked</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Reason</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Heartbeat (s)</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($policies as $p)
                    <tr>
                        <td class="px-4 py-2">{{ $p->tenant_id }}</td>
                        <td class="px-4 py-2"><span class="font-mono text-xs">{{ $p->scope_type }} = {{ $p->scope_value }}</span></td>
                        <td class="px-4 py-2">
                            @if($p->locked)
                                <span class="px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs font-medium">Locked</span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs font-medium">Open</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-700">{{ $p->lock_reason_code ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs">{{ $p->min_heartbeat_seconds }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <a href="{{ route('admin.desktop-telemetry.policies.edit', $p) }}" class="text-xs text-primary hover:underline">Edit</a>
                            <form action="{{ route('admin.desktop-telemetry.policies.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('Delete this policy?');">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No policies. The default open policy applies.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $policies->links() }}</div>
    </div>
</div>
@endsection
