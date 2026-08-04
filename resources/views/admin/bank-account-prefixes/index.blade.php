@extends('layouts.admin')

@section('title', 'Bank account prefixes')
@section('page-title', 'Bank account prefixes')

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

    <div>
        <h3 class="text-lg font-semibold text-gray-900">App bank suggestions (fallback)</h3>
        <p class="text-sm text-gray-600 mt-1 max-w-3xl">
            CheckoutNow uses a built-in prefix table first. These rows are returned by
            <code class="text-xs bg-gray-100 px-1 rounded">GET /api/v1/rentals/banks/suggestions</code>
            when the app has no local match — add new banks or prefixes here without an app rebuild.
        </p>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4 lg:p-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Add prefix rule</h4>
        <form method="POST" action="{{ route('admin.bank-account-prefixes.store') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Account prefix (digits)</label>
                <input type="text" name="prefix" value="{{ old('prefix') }}" maxlength="10" placeholder="e.g. 802"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <p class="text-xs text-gray-500 mt-1">Min 2 digits. Longest match wins.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Bank</label>
                <select name="bank_code" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Select bank…</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank['code'] }}" @selected(old('bank_code') === $bank['code'])>{{ $bank['name'] }} ({{ $bank['code'] }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Display name (optional)</label>
                <input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="120" placeholder="Override label"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes (optional)</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" placeholder="e.g. OPay series"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add rule
                </button>
            </div>
        </form>
    </div>

    <form method="GET" class="bg-white rounded-lg border border-gray-200 p-4 flex flex-col sm:flex-row gap-3">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search prefix, code, or bank"
               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <input type="text" name="preview" value="{{ $previewAccount }}" placeholder="Test account e.g. 8021234567"
               class="sm:w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm">Filter / preview</button>
    </form>

    @if($previewAccount !== '')
        <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
            <p class="text-sm font-medium text-teal-900">Preview for <code class="bg-white px-1 rounded">{{ $previewAccount }}</code></p>
            @if($previewBanks === [])
                <p class="text-sm text-teal-800 mt-2">No suggestions (app would show all banks only).</p>
            @else
                <ul class="mt-2 space-y-1">
                    @foreach($previewBanks as $bank)
                        <li class="text-sm text-teal-900">{{ $bank['name'] }} <span class="text-teal-700">({{ $bank['code'] }})</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto admin-table-scroll">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prefix</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added by</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rules as $rule)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 font-mono text-sm">{{ $rule->prefix }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->bank_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-sm text-gray-600">{{ $rule->bank_code }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.bank-account-prefixes.update', $rule) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="prefix" value="{{ $rule->prefix }}">
                                    <input type="hidden" name="bank_code" value="{{ $rule->bank_code }}">
                                    <input type="hidden" name="bank_name" value="{{ $rule->bank_name }}">
                                    <input type="hidden" name="notes" value="{{ $rule->notes }}">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="is_active" value="1" @checked($rule->is_active)
                                            onchange="this.form.submit()" class="rounded border-gray-300 text-primary">
                                        <span>{{ $rule->is_active ? 'Yes' : 'No' }}</span>
                                    </label>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $rule->notes ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $rule->createdBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.bank-account-prefixes.destroy', $rule) }}" onsubmit="return confirm('Remove this prefix rule?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No prefix rules yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rules->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $rules->links() }}</div>
        @endif
    </div>
</div>
@endsection
