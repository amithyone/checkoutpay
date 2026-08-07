@extends('layouts.admin')

@section('title', 'Investor pitch access')
@section('page-title', 'Investor pitch access')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('created_access_url'))
        <div class="bg-indigo-50 border border-indigo-200 text-indigo-950 px-4 py-4 rounded-lg space-y-2">
            <p class="font-semibold"><i class="fas fa-key mr-2"></i>Share this with the investor (copy now)</p>
            <p class="text-sm"><strong>Personal link:</strong>
                <a class="underline break-all" href="{{ session('created_access_url') }}" target="_blank" rel="noopener">{{ session('created_access_url') }}</a>
            </p>
            @if(session('created_access_password'))
                <p class="text-sm"><strong>Password:</strong>
                    <code class="bg-white px-2 py-1 rounded border text-base">{{ session('created_access_password') }}</code>
                    <span class="text-xs text-indigo-700 ml-1">(shown once — not stored in plain text)</span>
                </p>
            @endif
            <p class="text-xs text-indigo-800">The lock page will greet them by name. Entering the password + NDA checkbox = signed NDA.</p>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">
            <i class="fas fa-user-lock mr-2 text-primary"></i>Create investor access
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            Each investor gets a unique link + password. The gate page says: “Hello [name], here is your password to view our investor pitch.” Using the password accepts the NDA.
        </p>
        <form action="{{ route('admin.investor-pitch-access.store') }}" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-4xl">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="name">Investor name *</label>
                <input type="text" name="name" id="name" required value="{{ old('name') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="e.g. Ada Okafor">
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="company">Company / fund</label>
                <input type="text" name="company" id="company" value="{{ old('company') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Optional">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Optional">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="password">Password *</label>
                <input type="text" name="password" id="password" required minlength="8" value="{{ old('password') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono" placeholder="Min 8 characters">
                <p class="text-xs text-gray-500 mt-1">They will type this on their personal lock page. Stored hashed only.</p>
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="notes">Internal notes</label>
                <textarea name="notes" id="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Round, intro source, etc.">{{ old('notes') }}</textarea>
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked class="rounded border-gray-300">
                <label for="is_active" class="text-sm text-gray-700">Active (can unlock pitch)</label>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-primary text-white font-semibold rounded-lg hover:opacity-90">
                    <i class="fas fa-plus mr-2"></i>Create &amp; show link
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Issued access</h3>
            <p class="text-sm text-gray-500">Passwords are never shown again unless you set a new one.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Investor</th>
                        <th class="px-4 py-3 font-semibold">Link</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">NDA / visits</th>
                        <th class="px-4 py-3 font-semibold">Pages opened</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($accesses as $row)
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $row->name }}</div>
                                @if($row->company)<div class="text-gray-500">{{ $row->company }}</div>@endif
                                @if($row->email)<div class="text-gray-500">{{ $row->email }}</div>@endif
                                @if($row->notes)<div class="text-xs text-gray-400 mt-1">{{ \Illuminate\Support\Str::limit($row->notes, 80) }}</div>@endif
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <a href="{{ $row->gateUrl() }}" target="_blank" rel="noopener" class="text-primary break-all hover:underline text-xs">{{ $row->gateUrl() }}</a>
                            </td>
                            <td class="px-4 py-3">
                                @if($row->is_active)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Revoked</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <div>NDA: {{ $row->nda_accepted_at ? $row->nda_accepted_at->format('Y-m-d H:i') : '—' }}</div>
                                <div>Unlocks: {{ $row->access_count }}</div>
                                <div class="text-xs">Page events: {{ $row->page_views_count }}</div>
                                <div class="text-xs">Last unlock: {{ $row->last_accessed_at?->diffForHumans() ?? 'never' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 max-w-xs">
                                @if($row->pageViews->isEmpty())
                                    <span class="text-gray-400">No opens yet</span>
                                @else
                                    <ul class="space-y-1">
                                        @foreach($row->pageViews as $view)
                                            <li>
                                                <span class="font-medium text-gray-800">{{ $view->label() }}</span>
                                                <span class="text-gray-400">· {{ $view->viewed_at?->format('M j, H:i') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="px-4 py-3 space-y-3 min-w-[220px]">
                                <form action="{{ route('admin.investor-pitch-access.update', $row) }}" method="post" class="space-y-2 p-3 bg-gray-50 rounded-lg border">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $row->name }}" class="w-full px-2 py-1 border rounded text-sm" required>
                                    <input type="text" name="company" value="{{ $row->company }}" class="w-full px-2 py-1 border rounded text-sm" placeholder="Company">
                                    <input type="email" name="email" value="{{ $row->email }}" class="w-full px-2 py-1 border rounded text-sm" placeholder="Email">
                                    <input type="text" name="password" class="w-full px-2 py-1 border rounded text-sm font-mono" placeholder="New password (optional)" minlength="8">
                                    <label class="flex items-center gap-2 text-xs">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($row->is_active)> Active
                                    </label>
                                    <button type="submit" class="text-xs font-semibold text-white bg-primary px-3 py-1.5 rounded">Save</button>
                                </form>
                                <form action="{{ route('admin.investor-pitch-access.regenerate', $row) }}" method="post" onsubmit="return confirm('Invalidate the old link for {{ $row->name }}?');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-amber-800 hover:underline">Regenerate link</button>
                                </form>
                                <form action="{{ route('admin.investor-pitch-access.destroy', $row) }}" method="post" onsubmit="return confirm('Delete access for {{ $row->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No investor access yet. Create one above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accesses->hasPages())
            <div class="px-4 py-3 border-t">{{ $accesses->links() }}</div>
        @endif
    </div>
</div>
@endsection
