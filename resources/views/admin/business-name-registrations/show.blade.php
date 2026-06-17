@extends('layouts.admin')

@section('title', $registration->reference)
@section('page-title', 'Business name registration')

@section('content')
@php
    $wallet = $registration->wallet;
    $statusBadge = match ($registration->status) {
        \App\Models\BusinessNameRegistration::STATUS_APPROVED => 'bg-green-100 text-green-800',
        \App\Models\BusinessNameRegistration::STATUS_REJECTED => 'bg-red-100 text-red-800',
        \App\Models\BusinessNameRegistration::STATUS_UNDER_REVIEW => 'bg-indigo-100 text-indigo-800',
        \App\Models\BusinessNameRegistration::STATUS_PROCESSING => 'bg-blue-100 text-blue-800',
        default => 'bg-amber-100 text-amber-800',
    };
    $defaultProgress = \App\Models\BusinessNameRegistration::defaultProgressForStatus($registration->status);
@endphp
<div class="space-y-6">
    @include('admin.whatsapp-wallet.partials.nav')

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.business-name-registrations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> All applications
        </a>
        @if($wallet)
            <a href="{{ route('admin.business-name-registrations.index', ['wallet_id' => $wallet->id]) }}" class="text-sm text-gray-600 hover:text-gray-900">
                Other apps for this wallet
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-500 font-mono">{{ $registration->reference }} · {{ $registration->public_id }}</p>
                        <h2 class="text-2xl font-bold text-gray-900 mt-1">{{ $registration->proposed_name }}</h2>
                        @if($registration->alternate_name)
                            <p class="text-sm text-gray-600 mt-1">Alternate: {{ $registration->alternate_name }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium {{ $statusBadge }}">
                            {{ $registration->statusDisplayLabel() }}
                        </span>
                        <p class="text-sm text-gray-600 mt-2">{{ (int) $registration->progress_percent }}% complete</p>
                    </div>
                </div>

                <div class="h-2 bg-gray-200 rounded-full overflow-hidden mb-6">
                    <div class="h-full bg-green-600 rounded-full" style="width: {{ min(100, max(0, (int) $registration->progress_percent)) }}%"></div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Owner / director</dt>
                        <dd class="font-medium text-gray-900">{{ $registration->owner_full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Owner phone</dt>
                        <dd class="font-mono">{{ $registration->owner_phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Owner email</dt>
                        <dd>{{ $registration->owner_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">ID type</dt>
                        <dd class="uppercase">{{ str_replace('_', ' ', $registration->id_type) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Business address</dt>
                        <dd>{{ $registration->business_address }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Nature of business</dt>
                        <dd>{{ $registration->nature_of_business }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Fee paid</dt>
                        <dd>₦{{ number_format((float) $registration->fee_amount, 2) }} {{ $registration->fee_currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Submitted</dt>
                        <dd>{{ $registration->submitted_at?->format('M j, Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Est. completion</dt>
                        <dd>{{ $registration->estimated_completion_hours_min ?? 12 }}–{{ $registration->estimated_completion_hours_max ?? 24 }} hours</dd>
                    </div>
                    @if($registration->approved_at)
                        <div>
                            <dt class="text-gray-500">Approved</dt>
                            <dd>{{ $registration->approved_at->format('M j, Y H:i') }}</dd>
                        </div>
                    @endif
                    @if($registration->approved_business_name)
                        <div>
                            <dt class="text-gray-500">Approved name</dt>
                            <dd class="font-medium">{{ $registration->approved_business_name }}</dd>
                        </div>
                    @endif
                    @if($registration->rejected_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Rejection reason</dt>
                            <dd class="text-red-800">{{ $registration->rejected_reason }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-6 pt-4 border-t border-gray-100 flex flex-wrap gap-3">
                    <a href="{{ route('admin.business-name-registrations.id-document', $registration) }}"
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg text-sm hover:bg-gray-800">
                        <i class="fas fa-id-card mr-2"></i> View ID document
                    </a>
                    @if($registration->feeTransaction)
                        <a href="{{ route('admin.whatsapp-wallet.transactions.show', $registration->feeTransaction) }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 rounded-lg text-sm hover:bg-gray-200">
                            <i class="fas fa-receipt mr-2"></i> Fee transaction #{{ $registration->feeTransaction->id }}
                        </a>
                    @endif
                </div>
            </div>

            @if($registration->business_account_number)
                <div class="bg-green-50 border border-green-200 rounded-lg p-5">
                    <h3 class="font-semibold text-green-900 mb-2">Business receive account</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-green-800/70">Account number</dt>
                            <dd class="font-mono font-medium text-green-900">{{ $registration->business_account_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-green-800/70">Account name</dt>
                            <dd class="font-medium text-green-900">{{ $registration->business_account_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-green-800/70">Bank</dt>
                            <dd>{{ $registration->business_bank_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-green-800/70">Bank code</dt>
                            <dd class="font-mono">{{ $registration->business_bank_code ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @if($wallet)
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-900 mb-3">Wallet</h3>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Phone</dt>
                            <dd class="font-mono">
                                <a href="{{ route('admin.whatsapp-wallet.wallets.show', $wallet) }}" class="text-primary hover:underline">{{ $wallet->phone_e164 }}</a>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Balance</dt>
                            <dd class="font-medium">₦{{ number_format((float) $wallet->balance, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Display name</dt>
                            <dd>{{ $wallet->displayName() ?? '—' }}</dd>
                        </div>
                        @if($wallet->hasBusinessPayIn())
                            <div class="pt-2 border-t border-gray-100">
                                <dt class="text-gray-500 mb-1">Active business VA</dt>
                                <dd class="font-mono text-xs">{{ $wallet->business_pay_in_bank_name }} · {{ $wallet->business_pay_in_account_number }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-900 mb-1">Update status</h3>
                <p class="text-sm text-gray-600 mb-4">On approval, enter the business VA details for the mobile Receive Funds business slide.</p>

                @if($registration->status === \App\Models\BusinessNameRegistration::STATUS_APPROVED)
                    <p class="text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        This application is approved. Business pay-in is live on the wallet.
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.business-name-registrations.status', $registration) }}" class="space-y-4" id="bnr-status-form">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="bnr-status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @foreach(['paid' => 'Paid — queued (15%)', 'processing' => 'Processing (40%)', 'under_review' => 'Under review (65%)', 'approved' => 'Approved (100%)', 'rejected' => 'Rejected (0%)'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $registration->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Status label (optional)</label>
                            <input type="text" name="status_label" value="{{ old('status_label', $registration->status_label) }}"
                                placeholder="e.g. With CAC partner"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Progress %</label>
                            <input type="number" name="progress_percent" id="bnr-progress" min="0" max="100"
                                value="{{ old('progress_percent', $registration->progress_percent) }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>

                        <div id="bnr-approve-fields" class="space-y-3 hidden border border-green-200 bg-green-50 rounded-lg p-3">
                            <p class="text-xs font-semibold text-green-900 uppercase tracking-wide">Approval — business VA</p>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Approved business name</label>
                                <input type="text" name="approved_business_name"
                                    value="{{ old('approved_business_name', $registration->approved_business_name ?? $registration->proposed_name) }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Account number</label>
                                <input type="text" name="business_account_number"
                                    value="{{ old('business_account_number', $registration->business_account_number) }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Account name</label>
                                <input type="text" name="business_account_name"
                                    value="{{ old('business_account_name', $registration->business_account_name ?? $registration->proposed_name) }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Bank name</label>
                                    <input type="text" name="business_bank_name"
                                        value="{{ old('business_bank_name', $registration->business_bank_name ?? 'Rubies MFB') }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Bank code</label>
                                    <input type="text" name="business_bank_code"
                                        value="{{ old('business_bank_code', $registration->business_bank_code) }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                                </div>
                            </div>
                        </div>

                        <div id="bnr-reject-fields" class="hidden">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Rejection reason</label>
                            <textarea name="rejected_reason" rows="3"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                placeholder="Shown to the user in the app">{{ old('rejected_reason', $registration->rejected_reason) }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">Fees are not refunded automatically.</p>
                        </div>

                        <button type="submit" class="w-full bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-800">
                            Save update
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const statusEl = document.getElementById('bnr-status');
    const progressEl = document.getElementById('bnr-progress');
    const approveFields = document.getElementById('bnr-approve-fields');
    const rejectFields = document.getElementById('bnr-reject-fields');
    if (!statusEl) return;

    const progressByStatus = {
        paid: 15,
        processing: 40,
        under_review: 65,
        approved: 100,
        rejected: 0,
    };

    function syncFields() {
        const status = statusEl.value;
        if (approveFields) approveFields.classList.toggle('hidden', status !== 'approved');
        if (rejectFields) rejectFields.classList.toggle('hidden', status !== 'rejected');
        if (progressEl && document.activeElement !== progressEl) {
            progressEl.value = progressByStatus[status] ?? progressEl.value;
        }
    }

    statusEl.addEventListener('change', syncFields);
    syncFields();
})();
</script>
@endpush
@endsection
