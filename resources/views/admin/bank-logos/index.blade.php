@extends('layouts.admin')

@section('title', 'Bank Logos')
@section('page-title', 'Bank Logos')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Bank logo manager</h3>
            <p class="text-sm text-gray-600 mt-1">
                Map logos onto existing banks (code + name). Auto-map uses the vendored library;
                upload SVG/PNG for missing fintechs (OPay, Kuda, etc.).
            </p>
            <p class="text-sm text-gray-500 mt-2">
                {{ $stats['mapped'] }} mapped · {{ $stats['unmapped'] }} unmapped · {{ $stats['total'] }} total
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form action="{{ route('admin.bank-logos.auto-map') }}" method="POST">
                @csrf
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm">
                    Auto-map matches
                </button>
            </form>
            <form action="{{ route('admin.bank-logos.auto-map') }}" method="POST" onsubmit="return confirm('Replace existing logos where a library match exists?');">
                @csrf
                <input type="hidden" name="force" value="1">
                <button type="submit" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm">
                    Force re-map
                </button>
            </form>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 flex flex-col sm:flex-row gap-3">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search name or code"
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="all" @selected($status === 'all')>All</option>
            <option value="mapped" @selected($status === 'mapped')>Mapped</option>
            <option value="unmapped" @selected($status === 'unmapped')>Unmapped</option>
        </select>
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm">Filter</button>
    </form>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assign / upload</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($banks as $bank)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                @if($bank->logoUrl())
                                    <img src="{{ $bank->logoUrl() }}" alt="" class="h-10 w-10 object-contain bg-gray-50 rounded border border-gray-100 p-1">
                                @else
                                    <div class="h-10 w-10 rounded bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">—</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $bank->name }}</div>
                                @if($bank->logo_source)
                                    <div class="text-xs text-gray-500 mt-0.5">source: {{ $bank->logo_source }}</div>
                                @elseif(!empty($suggestions[$bank->id]))
                                    <div class="text-xs text-amber-700 mt-0.5">suggested: {{ $suggestions[$bank->id] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $bank->code }}</td>
                            <td class="px-4 py-3 space-y-2 min-w-[16rem]">
                                <form action="{{ route('admin.bank-logos.assign', $bank) }}" method="POST" class="flex gap-2">
                                    @csrf
                                    <select name="library_file" class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                                        <option value="">Library SVG…</option>
                                        @foreach($library as $file)
                                            <option value="{{ $file }}" @selected(($suggestions[$bank->id] ?? null) === $file)>{{ $file }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-gray-900 text-white">Assign</button>
                                </form>
                                <form action="{{ route('admin.bank-logos.upload', $bank) }}" method="POST" enctype="multipart/form-data" class="flex gap-2 items-center">
                                    @csrf
                                    <input type="file" name="logo" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/*" class="text-xs flex-1 border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                                    <button type="submit" class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-700">Upload</button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                @if($bank->hasLogo())
                                    <form action="{{ route('admin.bank-logos.clear', $bank) }}" method="POST" onsubmit="return confirm('Clear logo for {{ addslashes($bank->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">Clear</button>
                                    </form>
                                    <a href="{{ $bank->logoUrl() }}" target="_blank" class="text-sm text-primary hover:underline block mt-1">Open</a>
                                @else
                                    <span class="text-xs text-gray-400">No logo</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                No banks found. Sync banks first with <code class="text-xs bg-gray-100 px-1 rounded">php artisan banks:sync</code>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($banks->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $banks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
