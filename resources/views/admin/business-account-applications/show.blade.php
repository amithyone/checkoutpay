@extends('layouts.admin')

@section('title', $application->reference)
@section('page-title', 'Business account application')

@section('content')
@php
    $wallet = $application->wallet;
    $statusBadge = match ($application->status) {
        \App\Models\BusinessAccountApplication::STATUS_ACTIVE => 'bg-green-100 text-green-800',
        \App\Models\BusinessAccountApplication::STATUS_REJECTED => 'bg-red-100 text-red-800',
        \App\Models\BusinessAccountApplication::STATUS_UNDER_REVIEW => 'bg-indigo-100 text-indigo-800',
        \App\Models\BusinessAccountApplication::STATUS_AWAITING_PASSWORD => 'bg-blue-100 text-blue-800',
        default => 'bg-amber-100 text-amber-800',
    };
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
        <a href="{{ route('admin.business-account-applications.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> All applications
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-500 font-mono">{{ $application->reference }} · {{ $application->public_id }}</p>
                        <h2 class="text-2xl font-bold text-gray-900 mt-1">{{ $application->business_name }}</h2>
                        <p class="text-sm text-gray-600 mt-1 capitalize">{{ str_replace('_', ' ', $application->account_plan) }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium {{ $statusBadge }}">
                            {{ $application->statusDisplayLabel() }}
                        </span>
                        <p class="text-sm text-gray-600 mt-2">{{ (int) $application->progress_percent }}% complete</p>
                    </div>
                </div>

                <div class="h-2 bg-gray-200 rounded-full overflow-hidden mb-6">
                    <div class="h-full bg-green-600 rounded-full" style="width: {{ min(100, max(0, (int) $application->progress_percent)) }}%"></div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Email</dt>
                        <dd>{{ $application->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Phone</dt>
                        <dd class="font-mono">{{ $application->phone ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Address</dt>
                        <dd>{{ $application->address }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Website</dt>
                        <dd>{{ $application->website_url ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Service categories</dt>
                        <dd>{{ implode(', ', (array) ($application->service_categories ?? [])) ?: 'payments' }}</dd>
                    </div>
                    @if($application->linkedBusiness)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Linked merchant</dt>
                            <dd>
                                {{ $application->linkedBusiness->name }}
                                (ID {{ $application->linkedBusiness->id }})
                            </dd>
                        </div>
                    @endif
                </dl>

                @if($application->cac_document_path)
                    <div class="mt-6">
                        <a href="{{ route('admin.business-account-applications.cac-document', $application) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">
                            <i class="fas fa-file-alt"></i> Download CAC document
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            @if($wallet)
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5 text-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">Wallet</h3>
                    <dl class="space-y-2">
                        <div><dt class="text-gray-500 inline">Phone:</dt> <dd class="inline font-mono">{{ $wallet->phone_e164 }}</dd></div>
                        <div><dt class="text-gray-500 inline">Balance:</dt> <dd class="inline">₦{{ number_format((float) $wallet->balance, 2) }}</dd></div>
                        <div><dt class="text-gray-500 inline">Linked business:</dt> <dd class="inline">{{ $wallet->linked_business_id ?: 'None' }}</dd></div>
                    </dl>
                    <a href="{{ route('admin.whatsapp-wallet.wallets.show', $wallet) }}" class="inline-block mt-4 text-primary hover:underline text-sm">Open wallet</a>
                </div>
            @endif

            @if(! in_array($application->status, [\App\Models\BusinessAccountApplication::STATUS_ACTIVE, \App\Models\BusinessAccountApplication::STATUS_REJECTED], true))
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-900 mb-4">Update status</h3>
                    <form method="POST" action="{{ route('admin.business-account-applications.status', $application) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="under_review">Under review</option>
                                <option value="awaiting_password">Approve &amp; create business account</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status label (optional)</label>
                            <input type="text" name="status_label" value="{{ old('status_label') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rejected reason</label>
                            <textarea name="rejected_reason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('rejected_reason') }}</textarea>
                        </div>
                        <button type="submit" class="w-full py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800">
                            Save
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
