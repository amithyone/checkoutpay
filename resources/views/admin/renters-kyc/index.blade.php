@extends('layouts.admin')

@section('title', 'Renters KYC')
@section('page-title', 'Renters KYC')

@section('content')
<div class="space-y-6">
    @if(!empty($singleRenterId))
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <span>Viewing a specific renter (ID {{ $singleRenterId }}). Status filters below apply only to the full queue.</span>
            <a href="{{ route('admin.renters-kyc.index', array_filter(['status' => $status])) }}" class="inline-flex font-semibold text-blue-800 underline whitespace-nowrap">← Back to full KYC queue</a>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-sm text-gray-600">Review government ID uploads</div>
                <div class="text-xs text-gray-500">KYC is only Verified when bank is verified and ID is approved.</div>
            </div>

            <form method="GET" action="{{ route('admin.renters-kyc.index') }}" class="flex flex-wrap items-center gap-2">
                @if(!empty($singleRenterId))
                    <input type="hidden" name="renter" value="{{ $singleRenterId }}" />
                @endif
                <label class="text-xs text-gray-600">Status</label>
                <select name="status" class="border border-gray-300 rounded-md text-sm px-3 py-2">
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
                <button type="submit" class="px-3 py-2 rounded-md bg-gray-900 text-white text-sm">Filter</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    @if($renters->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            @if(!empty($singleRenterId))
                No renter found with this ID, or they are outside the rentals system.
            @else
                No renters found for this filter.
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($renters as $renter)
                @php
                    $s = $renter->kyc_id_status ?: 'pending';
                    $badgeBg = $s === 'approved' ? 'bg-green-100 text-green-800' : ($s === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                @endphp
                <article id="renter-{{ $renter->id }}" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 text-lg leading-tight">{{ $renter->name }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Renter #{{ $renter->id }}</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 justify-end">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $badgeBg }}">{{ ucfirst($s) }}</span>
                            @if($renter->is_active)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Disabled</span>
                            @endif
                        </div>
                    </div>

                    <div class="px-4 py-3 space-y-3 text-sm flex-1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</div>
                                <div class="text-gray-900 break-all">{{ $renter->email }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Phone</div>
                                <div class="text-gray-900">{{ $renter->phone ?: '—' }}</div>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 border border-gray-100 p-3 text-xs space-y-1">
                            <div class="font-semibold text-gray-700 mb-1">Bank (KYC)</div>
                            <div><span class="text-gray-500">Name:</span> {{ $renter->verified_account_name ?? '—' }}</div>
                            <div><span class="text-gray-500">Account:</span> {{ $renter->verified_account_number ?? '—' }}</div>
                            <div><span class="text-gray-500">Bank:</span> {{ $renter->verified_bank_name ?? '—' }}</div>
                        </div>

                        <div class="rounded-lg bg-gray-50 border border-gray-100 p-3 text-xs space-y-1">
                            <div class="font-semibold text-gray-700 mb-1">Extra KYC</div>
                            <div><span class="text-gray-500">BVN:</span> {{ $renter->bvn ?? '—' }}</div>
                            <div><span class="text-gray-500">Age:</span> {{ $renter->age ?? '—' }}</div>
                            <div class="break-all">
                                <span class="text-gray-500">Instagram:</span>
                                @if($renter->instagram_url)
                                    <a href="{{ $renter->instagram_url }}" target="_blank" rel="noopener" class="text-primary hover:underline">{{ $renter->instagram_url }}</a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase">ID type</span>
                            <span class="text-gray-900">{{ $renter->kyc_id_type ?? '—' }}</span>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">ID documents</div>
                            <div class="flex flex-wrap gap-2">
                                @if($renter->kyc_id_front_path)
                                    @if(!empty($renter->kyc_id_front_exists))
                                        <button type="button" class="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold" onclick="openDocModal('{{ route('admin.renters-kyc.document', ['renter' => $renter->id, 'type' => 'front']) }}', {{ json_encode('ID Front — '.$renter->name) }})">Front</button>
                                    @else
                                        <span class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-500 text-xs">Front missing</span>
                                    @endif
                                @endif
                                @if($renter->kyc_id_back_path)
                                    @if(!empty($renter->kyc_id_back_exists))
                                        <button type="button" class="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold" onclick="openDocModal('{{ route('admin.renters-kyc.document', ['renter' => $renter->id, 'type' => 'back']) }}', {{ json_encode('ID Back — '.$renter->name) }})">Back</button>
                                    @else
                                        <span class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-500 text-xs">Back missing</span>
                                    @endif
                                @endif
                                @if($renter->kyc_id_card_path)
                                    @if(!empty($renter->kyc_id_card_exists))
                                        <button type="button" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold" onclick="openDocModal('{{ route('admin.renters-kyc.document', ['renter' => $renter->id, 'type' => 'card']) }}', {{ json_encode('Uploaded ID — '.$renter->name) }})">Uploaded ID</button>
                                    @else
                                        <span class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-500 text-xs">Uploaded ID missing</span>
                                    @endif
                                @endif
                                @if(!$renter->kyc_id_front_path && !$renter->kyc_id_back_path && !$renter->kyc_id_card_path)
                                    <span class="text-gray-500 text-sm">No ID files uploaded yet.</span>
                                @endif
                            </div>
                        </div>

                        @if($s === 'rejected' && $renter->kyc_id_rejection_reason)
                            <div class="text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg p-2">
                                <span class="font-semibold">Rejection reason:</span> {{ $renter->kyc_id_rejection_reason }}
                            </div>
                        @endif

                        <div class="text-xs text-gray-500">
                            @if($renter->kyc_id_reviewed_at)
                                Reviewed {{ $renter->kyc_id_reviewed_at->format('Y-m-d H:i') }}
                            @else
                                Not reviewed yet
                            @endif
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.renters-kyc.approve', $renter) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">Approve ID</button>
                            </form>
                            <form method="POST" action="{{ route('admin.renters-kyc.toggle-active', $renter) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-2 rounded-lg {{ $renter->is_active ? 'bg-gray-700' : 'bg-blue-600' }} text-white text-sm font-semibold hover:opacity-95">
                                    {{ $renter->is_active ? 'Disable account' : 'Enable account' }}
                                </button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('admin.renters-kyc.reject', $renter) }}" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                            @csrf
                            <input name="reason" type="text" placeholder="Rejection reason (optional)" class="flex-1 border border-gray-300 rounded-lg text-sm px-3 py-2" />
                            <button type="submit" class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 shrink-0">Reject ID</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $renters->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function openDocModal(url, title) {
        const modal = document.getElementById('kycDocModal');
        const frame = document.getElementById('kycDocFrame');
        const img = document.getElementById('kycDocImage');
        const err = document.getElementById('kycDocError');
        const titleEl = document.getElementById('kycDocTitle');
        const isPdf = /\.pdf($|\?)/i.test(url);

        titleEl.textContent = title || 'KYC Document';
        err.classList.add('hidden');
        err.textContent = '';
        if (isPdf) {
            frame.src = url;
            frame.classList.remove('hidden');
            img.classList.add('hidden');
            img.src = '';
        } else {
            img.src = url;
            img.classList.remove('hidden');
            frame.classList.add('hidden');
            frame.src = '';
        }

        modal.classList.remove('hidden');
    }

    function closeDocModal() {
        const modal = document.getElementById('kycDocModal');
        const frame = document.getElementById('kycDocFrame');
        const img = document.getElementById('kycDocImage');
        const err = document.getElementById('kycDocError');
        modal.classList.add('hidden');
        frame.src = '';
        img.src = '';
        err.classList.add('hidden');
        err.textContent = '';
    }
</script>

<div id="kycDocModal" class="hidden fixed inset-0 z-50 bg-black/70 p-4">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-xl h-full flex flex-col">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 id="kycDocTitle" class="text-sm font-semibold text-gray-900">KYC Document</h3>
            <button type="button" onclick="closeDocModal()" class="px-3 py-1.5 rounded-md bg-gray-800 text-white text-sm">Close</button>
        </div>
        <div class="p-4 flex-1 overflow-auto">
            <div id="kycDocError" class="hidden mb-3 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm"></div>
            <img id="kycDocImage" src="" alt="KYC document preview" class="hidden w-full h-auto rounded border border-gray-200"
                 onerror="this.classList.add('hidden');document.getElementById('kycDocError').classList.remove('hidden');document.getElementById('kycDocError').textContent='Document file is missing or unavailable.';" />
            <iframe id="kycDocFrame" src="" class="hidden w-full h-[70vh] border border-gray-200 rounded"
                    onerror="this.classList.add('hidden');document.getElementById('kycDocError').classList.remove('hidden');document.getElementById('kycDocError').textContent='Document file is missing or unavailable.';"></iframe>
        </div>
    </div>
</div>
@endpush
