@extends('layouts.admin')

@section('title', 'Honeypot')
@section('page-title', 'Honeypot')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Honeypot &amp; IP blocks</h3>
            <p class="text-sm text-gray-600 mt-1">
                Scanners hitting <code class="text-xs bg-gray-100 px-1 rounded">{{ $honeypotPath }}</code> are logged and auto-blocked.
                Real admin is at <code class="text-xs bg-gray-100 px-1 rounded">{{ $realPath }}</code>.
            </p>
        </div>
        <div class="text-sm">
            @if($enabled)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs font-medium">Trap enabled</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-medium">Trap disabled</span>
            @endif
            <p class="text-xs text-gray-500 mt-1">Auto-ban after {{ $maxHits }} hits · {{ $banMinutes }} min</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
        <h4 class="font-semibold text-gray-900 mb-3">Block an IP</h4>
        <form method="POST" action="{{ route('admin.honeypot.ban') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">IP address</label>
                <input type="text" name="ip" value="{{ old('ip') }}" required placeholder="1.2.3.4"
                    class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Duration (minutes)</label>
                <input type="number" name="minutes" min="1" max="525600" value="{{ old('minutes', $banMinutes) }}"
                    class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Note (optional)</label>
                <input type="text" name="note" value="{{ old('note') }}" maxlength="255" placeholder="Scanner / abuse"
                    class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg"
                    onclick="return confirm('Block this IP from the whole site?')">
                    <i class="fas fa-ban mr-1"></i> Block IP
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h4 class="font-semibold text-gray-900">Blocked IPs ({{ count($bans) }})</h4>
        </div>

        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">IP</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Source</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Hits</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Last path</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Banned</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Expires</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Note</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($bans as $ban)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono font-medium text-gray-900">{{ $ban['ip'] }}</td>
                            <td class="px-4 py-3 capitalize text-gray-700">{{ $ban['source'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $ban['hits'] ?? 0 }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 truncate max-w-[12rem]" title="{{ $ban['last_path'] ?? '' }}">{{ $ban['last_path'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                @if(!empty($ban['banned_at']))
                                    {{ \Illuminate\Support\Carbon::parse($ban['banned_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                @if(!empty($ban['expires_at']))
                                    {{ \Illuminate\Support\Carbon::parse($ban['expires_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs truncate max-w-[10rem]">{{ $ban['note'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('admin.honeypot.unban') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="ip" value="{{ $ban['ip'] }}">
                                    <button type="submit" class="text-green-700 hover:underline text-sm"
                                        onclick="return confirm('Unblock {{ $ban['ip'] }}?')">
                                        Unblock
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">No blocked IPs right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lg:hidden divide-y divide-gray-100">
            @forelse($bans as $ban)
                <div class="px-4 py-3 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono font-semibold text-gray-900">{{ $ban['ip'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 capitalize">
                                {{ $ban['source'] ?? '—' }}
                                · {{ $ban['hits'] ?? 0 }} hits
                                @if(!empty($ban['last_path']))
                                    · {{ $ban['last_path'] }}
                                @endif
                            </p>
                            @if(!empty($ban['note']))
                                <p class="text-xs text-gray-600 mt-1">{{ $ban['note'] }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.honeypot.unban') }}">
                            @csrf
                            <input type="hidden" name="ip" value="{{ $ban['ip'] }}">
                            <button type="submit" class="text-sm text-green-700 font-medium"
                                onclick="return confirm('Unblock {{ $ban['ip'] }}?')">
                                Unblock
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-gray-500">No blocked IPs right now.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h4 class="font-semibold text-gray-900">Recent trap activity</h4>
            <p class="text-xs text-gray-500 mt-0.5">From today&apos;s honeypot log (newest first).</p>
        </div>
        <div class="divide-y divide-gray-100 max-h-[28rem] overflow-y-auto">
            @forelse($recent as $row)
                <div class="px-4 py-2.5 text-xs flex flex-wrap gap-x-3 gap-y-1">
                    <span class="text-gray-400 whitespace-nowrap">{{ $row['time'] }}</span>
                    <span class="font-medium
                        @if($row['level'] === 'alert') text-red-700
                        @elseif($row['level'] === 'warning') text-amber-700
                        @else text-gray-700
                        @endif">{{ $row['event'] }}</span>
                    @if($row['ip'])
                        <span class="font-mono text-gray-900">{{ $row['ip'] }}</span>
                        @if(! collect($bans)->contains(fn ($b) => ($b['ip'] ?? '') === $row['ip']))
                            <form method="POST" action="{{ route('admin.honeypot.ban') }}" class="inline">
                                @csrf
                                <input type="hidden" name="ip" value="{{ $row['ip'] }}">
                                <input type="hidden" name="note" value="From honeypot log">
                                <button type="submit" class="text-red-600 hover:underline">Block</button>
                            </form>
                        @endif
                    @endif
                    @if($row['path'])
                        <span class="font-mono text-gray-500 truncate">{{ $row['path'] }}</span>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-500">No honeypot log entries yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
