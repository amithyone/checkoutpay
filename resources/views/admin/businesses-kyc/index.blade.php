@extends('layouts.admin')

@section('title', 'Business KYC')
@section('page-title', 'Business KYC')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-id-card text-primary mr-2"></i> Verification document queue
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Review submitted KYC documents, preview files, and approve or reject each item.
                    BVN and NIN must be verified via Mevon before approval.
                </p>
            </div>

            <form method="GET" action="{{ route('admin.businesses-kyc.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Status</label>
                    <select name="status" class="border border-gray-300 rounded-md text-sm px-3 py-2">
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending review</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Document type</label>
                    <select name="type" class="border border-gray-300 rounded-md text-sm px-3 py-2">
                        <option value="">All types</option>
                        @foreach($requiredTypes as $type)
                            <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>
                                {{ \App\Models\BusinessVerification::getTypeLabel($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-3 py-2 rounded-md bg-gray-900 text-white text-sm">Filter</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg p-3 text-sm">
            {{ session('warning') }}
        </div>
    @endif

    @if($verifications->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            No verification documents found for this filter.
        </div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            @foreach($verifications as $verification)
                @php
                    $business = $verification->business;
                    $statusBadge = match($verification->status) {
                        'approved' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        'under_review' => 'bg-yellow-100 text-yellow-800',
                        default => 'bg-amber-100 text-amber-800',
                    };
                    $isPending = in_array($verification->status, ['pending', 'under_review'], true);
                @endphp
                <article class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 leading-tight">
                                {{ \App\Models\BusinessVerification::getTypeLabel($verification->verification_type) }}
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $business?->name ?? 'Unknown business' }}
                                @if($business)
                                    · <a href="{{ route('admin.businesses.show', $business) }}" class="text-primary hover:underline">Open business</a>
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst(str_replace('_', ' ', $verification->status)) }}
                        </span>
                    </div>

                    <div class="px-4 py-3 space-y-3 text-sm flex-1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <div class="font-semibold text-gray-500 uppercase tracking-wide">Business email</div>
                                <div class="text-gray-900 break-all">{{ $business?->email ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-500 uppercase tracking-wide">Submitted</div>
                                <div class="text-gray-900">{{ $verification->created_at?->format('M d, Y H:i') ?? '—' }}</div>
                            </div>
                            @if($verification->requiresMevonVerification() && $business?->rubies_signatory_dob)
                                <div>
                                    <div class="font-semibold text-gray-500 uppercase tracking-wide">Date of birth</div>
                                    <div class="text-gray-900">{{ $business->rubies_signatory_dob->format('M d, Y') }}</div>
                                </div>
                            @endif
                        </div>

                        @if($verification->document_type)
                            <div class="rounded-lg bg-gray-50 border border-gray-100 p-3 text-xs">
                                <div class="font-semibold text-gray-700 mb-1">Submitted details</div>
                                <div class="text-gray-900 break-words">{{ $verification->document_type }}</div>
                            </div>
                        @endif

                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Document</div>
                            <div class="flex flex-wrap gap-2">
                                @if($verification->isTextBased())
                                    <span class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold">Text verification (no file)</span>
                                @elseif($verification->document_path)
                                    @if($verification->document_exists)
                                        <button type="button"
                                                class="px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold"
                                                onclick="openDocModal('{{ route('admin.businesses-kyc.document', $verification) }}', {{ json_encode(\App\Models\BusinessVerification::getTypeLabel($verification->verification_type).' — '.($business?->name ?? 'Business')) }})">
                                            <i class="fas fa-eye mr-1"></i> View document
                                        </button>
                                        <a href="{{ route('admin.businesses.verification.download', [$business, $verification]) }}"
                                           class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold inline-flex items-center">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </a>
                                    @else
                                        <span class="px-3 py-1.5 rounded-lg bg-red-50 text-red-700 text-xs">File missing on server</span>
                                    @endif
                                @else
                                    <span class="text-gray-500 text-xs">No file uploaded.</span>
                                @endif
                            </div>
                        </div>

                        @if($verification->reviewed_at)
                            <div class="text-xs text-gray-500">
                                Reviewed {{ $verification->reviewed_at->format('M d, Y H:i') }}
                                @if($verification->reviewer)
                                    by {{ $verification->reviewer->name }}
                                @endif
                            </div>
                        @endif

                        @if($verification->rejection_reason)
                            <div class="text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg p-2">
                                <span class="font-semibold">Rejection reason:</span> {{ $verification->rejection_reason }}
                            </div>
                        @endif

                        @if($verification->admin_notes)
                            <div class="text-xs text-gray-700 bg-gray-50 border border-gray-100 rounded-lg p-2">
                                <span class="font-semibold">Admin notes:</span> {{ $verification->admin_notes }}
                            </div>
                        @endif

                        @if($verification->requiresMevonVerification())
                            @php
                                $providerPassed = $verification->isProviderVerified();
                                $providerFailed = $verification->provider_verify_status === \App\Models\BusinessVerification::PROVIDER_VERIFY_FAILED;
                            @endphp
                            <div class="rounded-lg border p-3 text-xs {{ $providerPassed ? 'bg-green-50 border-green-200' : ($providerFailed ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200') }}">
                                <div class="font-semibold text-gray-800 mb-1">
                                    <i class="fas fa-shield-alt mr-1"></i> Mevon identity check
                                </div>
                                @if($providerPassed)
                                    <div class="text-green-800">
                                        Verified {{ $verification->provider_verified_at?->format('M d, Y H:i') }}
                                        @if($verification->provider_verified_name)
                                            · Registered name: <span class="font-semibold">{{ $verification->provider_verified_name }}</span>
                                        @endif
                                    </div>
                                @elseif($providerFailed)
                                    <div class="text-red-800">
                                        Verification failed {{ $verification->provider_verified_at?->format('M d, Y H:i') }}:
                                        {{ $verification->provider_verify_message }}
                                        @if($verification->provider_verified_name)
                                            <div class="mt-1">Mevon name: <span class="font-semibold">{{ $verification->provider_verified_name }}</span></div>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-amber-900">
                                        Not verified yet. Run Mevon check to confirm the submitted name matches the BVN/NIN record.
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($isPending && $canDecide && $business)
                        <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 space-y-3">
                            @if($verification->requiresMevonVerification())
                                <form method="POST" action="{{ route('admin.businesses-kyc.verify-identity', $verification) }}" class="space-y-2 border border-indigo-100 bg-indigo-50/40 rounded-lg p-3">
                                    @csrf
                                    <input type="hidden" name="return_to" value="kyc_queue">
                                    <div class="text-xs font-semibold text-indigo-900">
                                        Verify with Mevon (required before approval)
                                    </div>
                                    <div class="text-xs text-indigo-800">
                                        Submitted name: <span class="font-semibold">{{ $business->name }}</span>
                                        · Number: <span class="font-mono">{{ $verification->extractSubmittedIdentityNumber() ?? '—' }}</span>
                                    </div>
                                    @if(! $business->rubies_signatory_dob)
                                        <div class="rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-xs p-2">
                                            Date of birth was not collected on submission. Enter it below to run Mevon verification.
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-700 mb-1">Signatory date of birth (required for Mevon)</label>
                                            <input type="date" name="signatory_dob" required max="{{ now()->subDay()->format('Y-m-d') }}"
                                                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                                        </div>
                                    @endif
                                    <button type="submit"
                                            class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 {{ ($mevonVerifyAvailable ?? false) ? '' : 'opacity-60 cursor-not-allowed' }}"
                                            @if(! ($mevonVerifyAvailable ?? false)) disabled title="Mevon is not configured" @endif>
                                        <i class="fas fa-user-check mr-1"></i>
                                        {{ $verification->isProviderVerified() ? 'Re-check with Mevon' : 'Verify with Mevon' }}
                                    </button>
                                </form>
                            @endif

                            @php $canApproveIdentity = ! $verification->requiresMevonVerification() || $verification->isProviderVerified(); @endphp
                            <form method="POST" action="{{ route('admin.businesses.verification.approve', [$business, $verification]) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="return_to" value="kyc_queue">
                                <textarea name="admin_notes" rows="2" placeholder="Admin notes (optional)"
                                          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"></textarea>
                                @if(! $canApproveIdentity)
                                    <p class="text-xs text-amber-800">Complete Mevon verification above before approving.</p>
                                @endif
                                <button type="submit"
                                        class="px-3 py-2 rounded-lg text-white text-sm font-semibold {{ $canApproveIdentity ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed' }}"
                                        @if(! $canApproveIdentity) disabled @endif>
                                    <i class="fas fa-check mr-1"></i> Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.businesses.verification.reject', [$business, $verification]) }}" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                @csrf
                                <input type="hidden" name="return_to" value="kyc_queue">
                                <input name="rejection_reason" type="text" required placeholder="Rejection reason"
                                       class="flex-1 border border-gray-300 rounded-lg text-sm px-3 py-2">
                                <button type="submit" class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 shrink-0">
                                    Reject
                                </button>
                            </form>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $verifications->links() }}
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
        modal.classList.add('hidden');
        frame.src = '';
        img.src = '';
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
            <iframe id="kycDocFrame" src="" class="hidden w-full h-[70vh] border border-gray-200 rounded"></iframe>
        </div>
    </div>
</div>
@endpush
