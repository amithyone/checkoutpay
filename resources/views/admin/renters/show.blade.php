@extends('layouts.admin')

@section('title', 'Rental user '.$renter->name)
@section('page-title', 'Rental user')

@section('content')
<div class="space-y-6 max-w-4xl">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.renters.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> All rental users
        </a>
        <a href="{{ route('admin.renters-kyc.index', ['renter' => $renter->id]) }}" class="text-sm text-primary hover:underline">
            KYC &amp; documents
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $renter->name }}</h2>
                    <p class="text-sm text-gray-500 font-mono mt-1">#{{ $renter->id }} · {{ $renter->email }}</p>
                </div>
                @if($renter->is_active)
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                @else
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Disabled</span>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.renters.update', $renter) }}" class="space-y-4 border-t border-gray-100 pt-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" value="{{ old('name', $renter->name) }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $renter->email) }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $renter->phone) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address" rows="2"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">{{ old('address', $renter->address) }}</textarea>
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="is_active" value="1" class="mt-1 rounded border border-gray-300"
                           @checked(old('is_active', $renter->is_active))>
                    <span>Account active</span>
                </label>

                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="balance_audit_exempt" value="1" class="mt-1 rounded border border-gray-300"
                           @checked(old('balance_audit_exempt', $renter->balance_audit_exempt))>
                    <span>
                        Exclude from bank float audit
                        <span class="block text-xs text-gray-500">Test renters — won’t count on Audits totals</span>
                    </span>
                </label>

                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:bg-primary/90">Save profile</button>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Wallet balance</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">₦{{ number_format((float) $renter->wallet_balance, 2) }}</p>
                @if($renter->balance_audit_exempt)
                    <p class="mt-2 text-xs text-amber-700">Excluded from bank float audit</p>
                @endif

                @if(auth('admin')->user()?->canUpdateBusinessBalance())
                    <form method="POST" action="{{ route('admin.renters.update-balance', $renter) }}" class="mt-4 pt-4 border-t border-gray-100 space-y-3"
                          onsubmit="return confirm('Update this renter wallet balance?');">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New balance (₦)</label>
                            <input type="number" name="wallet_balance" step="0.01" min="0" required
                                   value="{{ old('wallet_balance', number_format((float) $renter->wallet_balance, 2, '.', '')) }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                            <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                                   placeholder="Reason for adjustment">
                        </div>
                        <button type="submit" class="w-full bg-gray-900 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800">Update balance</button>
                    </form>
                @else
                    <p class="mt-3 text-xs text-gray-500">Only super admins can edit renter wallet balances.</p>
                @endif
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-5 shadow-sm text-sm space-y-2">
                <h3 class="font-semibold text-gray-900">Bank on file</h3>
                <p class="text-gray-600">{{ $renter->verified_bank_name ?: '—' }}</p>
                <p class="font-mono text-gray-800">{{ $renter->verified_account_number ?: '—' }}</p>
                <p class="text-gray-600">{{ $renter->verified_account_name ?: '—' }}</p>
                <p class="text-xs text-gray-500 pt-2">Updated {{ $renter->updated_at?->diffForHumans() ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
