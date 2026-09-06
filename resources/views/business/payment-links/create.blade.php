@extends('layouts.business')

@section('title', 'Create payment link')
@section('page-title', 'Create payment link')

@section('content')
<style>
    .cn-preview { background:#0a0a0c; color:#fff; border:1px solid rgba(255,255,255,.1); }
    .cn-preview-card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); }
    .cn-preview-kicker { letter-spacing:.3em; color:#22d3ee; }
    .cn-preview-blob-a { background:rgba(37,99,235,.35); }
    .cn-preview-blob-b { background:rgba(147,51,234,.28); }
</style>
<div class="max-w-6xl pb-20 lg:pb-0">
    <a href="{{ route('business.payment-links.index') }}" class="text-sm text-gray-600 hover:text-gray-900 mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back</a>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">New payment link</h2>
            <p class="text-sm text-gray-600 mb-6">Your client opens a dedicated pay page{{ $business->card_payments_enabled ? ' and can pay by card or transfer to a CheckoutPay account' : ' and transfers to a CheckoutPay account' }}. The preview on the right is what they see.</p>

            <form id="link-form" method="POST" action="{{ route('business.payment-links.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" id="field-title" value="{{ old('title') }}" required maxlength="255" placeholder="e.g. Website deposit, School fees"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg @error('title') border-red-400 @enderror">
                    @error('title')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                    <textarea name="description" id="field-description" rows="3" maxlength="2000" class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                            <input type="radio" name="amount_mode" value="fixed" {{ old('amount_mode', 'fixed') === 'fixed' ? 'checked' : '' }}>
                            <span class="text-sm">Fixed amount</span>
                        </label>
                        <div id="fixed-amount" class="{{ old('amount_mode', 'fixed') === 'open' ? 'hidden' : '' }} pl-8">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500">₦</span>
                                <input type="number" name="amount" id="field-amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="w-40 px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            @error('amount')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                            <input type="radio" name="amount_mode" value="open" {{ old('amount_mode') === 'open' ? 'checked' : '' }}>
                            <span class="text-sm">Open — client types how much to pay (minimum ₦100)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">How many times can this link be paid?</label>
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer mb-2">
                        <input type="radio" name="reuse_mode" value="one_time" {{ old('reuse_mode', 'one_time') === 'one_time' ? 'checked' : '' }}>
                        <span class="text-sm">One-time — closes after the first successful payment</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                        <input type="radio" name="reuse_mode" value="reusable" {{ old('reuse_mode') === 'reusable' ? 'checked' : '' }}>
                        <span class="text-sm">Reusable — many clients can pay the same link</span>
                    </label>
                    @error('reuse_mode')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Create link</button>
            </form>
        </div>

        <div class="lg:sticky lg:top-6">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-2">Customer preview</p>
            <div class="cn-preview mx-auto max-w-[360px] rounded-3xl shadow-xl overflow-hidden relative">
                <div class="cn-preview-blob-a absolute -top-16 -left-10 w-48 h-48 rounded-full pointer-events-none" style="filter:blur(48px)"></div>
                <div class="cn-preview-blob-b absolute -bottom-16 -right-10 w-44 h-44 rounded-full pointer-events-none" style="filter:blur(48px)"></div>
                <div class="relative px-4 pt-4 pb-3">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.32em] text-blue-400 mb-3">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</p>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-blue-500/20 text-blue-300 flex items-center justify-center text-sm font-extrabold">{{ strtoupper(mb_substr($business->name, 0, 1)) }}</div>
                        <div>
                            <h3 class="text-sm font-bold leading-tight">Pay <span id="preview-business">{{ $business->name }}</span></h3>
                            <p id="preview-title" class="text-xs text-gray-400">Your product title</p>
                        </div>
                    </div>
                    <div class="text-center py-2">
                        <p class="text-[10px] font-extrabold uppercase tracking-[0.3em] text-blue-500">Amount</p>
                        <p id="preview-amount" class="text-3xl font-thin tracking-tight">₦0.00</p>
                        <p id="preview-note-wrap" class="hidden text-xs text-gray-400 mt-1"><span id="preview-note"></span></p>
                    </div>
                </div>

                <div class="relative px-4 pb-4 space-y-3">
                    <div id="preview-open-form" class="cn-preview-card rounded-3xl p-3 hidden">
                        <h4 class="text-xs font-extrabold uppercase tracking-widest text-cyan-400 mb-2">Continue to pay</h4>
                        <div class="space-y-2 pointer-events-none">
                            @if($business->card_payments_enabled)
                                <p class="text-xs text-gray-400">How do you want to pay?</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="p-2 border border-white/10 rounded-2xl text-xs font-semibold">Account number</div>
                                    <div class="p-2 border border-white/10 rounded-2xl text-xs font-semibold">Card payment</div>
                                </div>
                            @endif
                            <div class="px-3 py-2 border border-white/10 rounded-2xl text-sm text-gray-500">Customer name</div>
                            <div class="px-3 py-2 border border-white/10 rounded-2xl text-sm text-gray-500">They type the amount</div>
                            <div class="w-full px-4 py-2 bg-blue-500 text-white rounded-full text-[10px] font-extrabold uppercase tracking-widest text-center">{{ $business->card_payments_enabled ? 'Get account number' : 'Get payment details' }}</div>
                        </div>
                    </div>

                    <div id="preview-methods" class="cn-preview-card rounded-3xl p-3 {{ $business->card_payments_enabled ? '' : 'hidden' }}">
                        <h4 class="text-xs font-extrabold uppercase tracking-widest text-cyan-400 mb-2">Continue to pay</h4>
                        <div class="space-y-2 pointer-events-none">
                            <p class="text-xs text-gray-400">How do you want to pay?</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2 border border-white/10 rounded-2xl text-xs font-semibold">Account number</div>
                                <div class="p-2 border border-white/10 rounded-2xl text-xs font-semibold">Card payment</div>
                            </div>
                            <div class="px-3 py-2 border border-white/10 rounded-2xl text-sm text-gray-500">Customer name</div>
                            <div class="w-full px-4 py-2 bg-blue-500 text-white rounded-full text-[10px] font-extrabold uppercase tracking-widest text-center">Get account number</div>
                        </div>
                    </div>

                    <div id="preview-pay-modal" class="cn-preview-card rounded-3xl p-4 {{ $business->card_payments_enabled ? 'hidden' : '' }}">
                        <h4 class="cn-preview-kicker text-[10px] font-extrabold uppercase mb-2">Payment instructions</h4>
                        <p class="text-[11px] text-gray-400 mb-3">Transfer the exact amount to this account.</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Account Number</p>
                        <p class="font-mono font-bold text-lg tracking-wide">•••• •••• ••••</p>
                        <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
                            <div><span class="text-gray-500 uppercase tracking-widest text-[10px]">Bank</span><p class="font-semibold">Assigned at checkout</p></div>
                            <div><span class="text-gray-500 uppercase tracking-widest text-[10px]">Account name</span><p class="font-semibold">CheckoutPay</p></div>
                        </div>
                        <p id="preview-pay-amount" class="hidden">₦0.00</p>
                    </div>
                    <p id="preview-reuse" class="text-[11px] text-gray-500">One-time link</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('link-form');
    const titleEl = document.getElementById('field-title');
    const descEl = document.getElementById('field-description');
    const amountEl = document.getElementById('field-amount');
    const fixedWrap = document.getElementById('fixed-amount');

    const previewTitle = document.getElementById('preview-title');
    const previewAmount = document.getElementById('preview-amount');
    const previewPayAmount = document.getElementById('preview-pay-amount');
    const previewNoteWrap = document.getElementById('preview-note-wrap');
    const previewNote = document.getElementById('preview-note');
    const previewReuse = document.getElementById('preview-reuse');
    const previewOpenForm = document.getElementById('preview-open-form');
    const previewPayModal = document.getElementById('preview-pay-modal');
    const previewMethods = document.getElementById('preview-methods');
    const cardsEnabled = {{ $business->card_payments_enabled ? 'true' : 'false' }};

    function naira(value) {
        const n = Number(value);
        if (!Number.isFinite(n) || n <= 0) {
            return '₦0.00';
        }
        return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function amountMode() {
        const checked = form.querySelector('input[name="amount_mode"]:checked');
        return checked ? checked.value : 'fixed';
    }

    function reuseMode() {
        const checked = form.querySelector('input[name="reuse_mode"]:checked');
        return checked ? checked.value : 'one_time';
    }

    function refresh() {
        const title = (titleEl.value || '').trim();
        previewTitle.textContent = title || 'Your product title';

        const note = (descEl.value || '').trim();
        if (note) {
            previewNote.textContent = note;
            previewNoteWrap.classList.remove('hidden');
        } else {
            previewNoteWrap.classList.add('hidden');
        }

        const open = amountMode() === 'open';
        fixedWrap.classList.toggle('hidden', open);
        previewOpenForm.classList.toggle('hidden', !open);
        if (previewMethods) {
            previewMethods.classList.toggle('hidden', open || !cardsEnabled);
        }
        previewPayModal.classList.toggle('hidden', open || cardsEnabled);

        if (open) {
            previewAmount.textContent = 'Customer chooses';
            previewPayAmount.textContent = '₦100.00+';
        } else {
            const formatted = naira(amountEl.value);
            previewAmount.textContent = formatted;
            previewPayAmount.textContent = formatted;
        }

        previewReuse.textContent = reuseMode() === 'reusable'
            ? 'Reusable — many clients can pay'
            : 'One-time — closes after first payment';
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
