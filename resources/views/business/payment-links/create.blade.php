@extends('layouts.business')

@section('title', 'Create payment link')
@section('page-title', 'Create payment link')

@section('content')
<div class="max-w-6xl pb-20 lg:pb-0">
    <a href="{{ route('business.payment-links.index') }}" class="text-sm text-gray-600 hover:text-gray-900 mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back</a>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">New payment link</h2>
            <p class="text-sm text-gray-600 mb-6">Your client opens a dedicated pay page and transfers to a CheckoutPay account. The preview on the right is what they see.</p>

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
            <div class="rounded-2xl border border-gray-200 bg-gray-100 p-3 sm:p-4 shadow-sm">
                <div class="rounded-xl bg-gray-50 overflow-hidden border border-gray-200">
                    <div class="bg-white px-4 py-3 border-b border-gray-100 text-center">
                        <p class="text-[11px] text-gray-400">checkoutpay.com/pay/l/••••</p>
                        <h3 class="text-lg font-bold text-gray-900 mt-1">Pay <span id="preview-business">{{ $business->name }}</span></h3>
                        <p id="preview-title" class="text-sm text-gray-600">Your product title</p>
                    </div>

                    <div class="p-4 space-y-4">
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Details</h4>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <p class="text-gray-500">Business</p>
                                    <p class="font-medium text-gray-900">{{ $business->name }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Amount</p>
                                    <p id="preview-amount" class="font-medium text-gray-900">₦0.00</p>
                                </div>
                                <div id="preview-note-wrap" class="hidden">
                                    <p class="text-gray-500">Note</p>
                                    <p id="preview-note" class="text-gray-800"></p>
                                </div>
                                <p id="preview-reuse" class="text-xs text-gray-500">One-time link</p>
                            </div>
                        </div>

                        <div id="preview-open-form" class="bg-white rounded-lg border border-gray-200 p-4 hidden">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Continue to pay</h4>
                            <div class="space-y-3 pointer-events-none opacity-90">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Your name</label>
                                    <div class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-400">Customer name</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Amount (₦, minimum 100)</label>
                                    <div class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-400">They type the amount</div>
                                </div>
                                <div class="w-full px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium text-center">Get payment details</div>
                            </div>
                        </div>

                        <div id="preview-pay-modal" class="bg-white rounded-lg border border-gray-200 p-4">
                            <h4 class="text-sm font-semibold text-gray-900 mb-1">Payment instructions</h4>
                            <p class="text-xs text-gray-500 mb-3">Transfer the exact amount to the account below.</p>
                            <div class="bg-gray-50 rounded-lg p-3 space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-gray-500">Bank</span><span class="font-medium">Assigned at checkout</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Account name</span><span class="font-medium">CheckoutPay</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Account number</span><span class="font-mono font-semibold">•••• •••• ••••</span></div>
                            </div>
                            <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 flex justify-between items-center">
                                <span class="text-xs font-medium text-blue-900">Amount to pay</span>
                                <span id="preview-pay-amount" class="text-lg font-bold text-blue-900">₦0.00</span>
                            </div>
                        </div>
                    </div>
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
        previewPayModal.classList.toggle('hidden', open);

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
