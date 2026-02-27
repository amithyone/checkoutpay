@extends('layouts.business')

@section('title', 'Request Withdrawal - Step 1')
@section('page-title', 'Request Withdrawal')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white font-medium">1</span>
            <span>Account</span>
            <span class="text-gray-300">→</span>
            <span class="text-gray-400">Amount & confirm</span>
        </div>
        <div class="mb-4 sm:mb-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Step 1: Choose account</h3>
            <p class="text-xs sm:text-sm text-gray-600 mt-1">Available to withdraw: <span class="font-semibold text-primary">₦{{ number_format($business->getAvailableBalance(), 2) }}</span></p>
        </div>

        @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                @foreach($errors->all() as $err)
                    <p>{{ $err }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('business.withdrawals.create.account') }}" id="step1-form">
            @csrf

            @if($savedAccounts->count() > 0)
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Use a saved account</label>
                    <select name="saved_account_id" id="saved_account_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">-- Select saved account or enter new below --</option>
                        @foreach($savedAccounts as $saved)
                            <option value="{{ $saved->id }}">
                                {{ $saved->bank_name }} – {{ $saved->account_name }} ({{ $saved->account_number }}){{ $saved->is_default ? ' [Default]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p class="text-sm text-gray-500 mb-4">Or enter a new account:</p>
            @endif

            <div class="space-y-4 border-t border-gray-200 pt-4">
                <div>
                    <label for="bank_search" class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                    <div class="relative">
                        <input type="text" id="bank_search" autocomplete="off" placeholder="Search bank..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <input type="hidden" name="bank_code" id="bank_code">
                        <input type="hidden" name="bank_name" id="bank_name">
                        <div id="bank_dropdown" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto mt-1"></div>
                    </div>
                </div>
                <div>
                    <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account number</label>
                    <input type="text" name="account_number" id="account_number" maxlength="10" placeholder="10-digit NUBAN"
                        value="{{ old('account_number') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    <p id="validation_msg" class="mt-1 text-sm hidden"></p>
                </div>
                <div id="account_name_row" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account name (verified)</label>
                    <input type="text" id="account_name_display" readonly class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                    <input type="hidden" name="account_name" id="account_name">
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('business.withdrawals.index') }}" class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-center text-sm">
                    Cancel
                </a>
                <button type="submit" id="btn_continue" disabled class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                    Continue
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const banks = @json(config('banks', []));
    const form = document.getElementById('step1-form');
    const savedSelect = document.getElementById('saved_account_id');
    const bankSearch = document.getElementById('bank_search');
    const bankCode = document.getElementById('bank_code');
    const bankName = document.getElementById('bank_name');
    const accountNumber = document.getElementById('account_number');
    const accountName = document.getElementById('account_name');
    const accountNameDisplay = document.getElementById('account_name_display');
    const accountNameRow = document.getElementById('account_name_row');
    const validationMsg = document.getElementById('validation_msg');
    const btnContinue = document.getElementById('btn_continue');

    let isValidated = false;

    function setValidated(ok, msg, name) {
        isValidated = ok;
        validationMsg.classList.remove('hidden');
        validationMsg.textContent = msg || '';
        validationMsg.className = 'mt-1 text-sm ' + (ok ? 'text-green-600' : 'text-red-600');
        if (ok && name) {
            accountNameRow.classList.remove('hidden');
            accountNameDisplay.value = name;
            accountName.value = name;
        } else {
            accountNameRow.classList.add('hidden');
            accountName.value = '';
        }
        btnContinue.disabled = !ok && !(savedSelect && savedSelect.value);
    }

    if (savedSelect) {
        savedSelect.addEventListener('change', function() {
            if (this.value) {
                setValidated(true, 'Using saved account', '');
                btnContinue.disabled = false;
            } else {
                setValidated(false, '', '');
                btnContinue.disabled = !isValidated;
            }
        });
    }

    if (bankSearch && document.getElementById('bank_dropdown')) {
        const dropdown = document.getElementById('bank_dropdown');
        bankSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            if (q.length < 2) { dropdown.classList.add('hidden'); return; }
            const list = banks.filter(b => (b.bank_name || '').toLowerCase().includes(q)).slice(0, 12);
            dropdown.innerHTML = list.length ? list.map(b => '<div class="px-4 py-2 hover:bg-gray-100 cursor-pointer" data-code="' + (b.code || '') + '" data-name="' + (b.bank_name || '').replace(/"/g, '&quot;') + '">' + (b.bank_name || '') + '</div>').join('') : '<div class="px-4 py-2 text-gray-500">No bank found</div>';
            dropdown.classList.remove('hidden');
        });
        dropdown.addEventListener('click', function(e) {
            const el = e.target.closest('[data-code]');
            if (el) {
                bankCode.value = el.dataset.code || '';
                bankName.value = el.dataset.name || '';
                bankSearch.value = el.dataset.name || '';
                dropdown.classList.add('hidden');
                isValidated = false;
                setValidated(false, '', '');
                if (accountNumber.value.length === 10) validateAccount();
            }
        });
        document.addEventListener('click', function(e) {
            if (!bankSearch.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden');
        });
    }

    function validateAccount() {
        const an = (accountNumber.value || '').replace(/\D/g, '');
        const bc = bankCode.value;
        if (an.length !== 10 || !bc) {
            setValidated(false, an.length === 10 ? 'Please select a bank.' : 'Enter 10-digit account number.', '');
            return;
        }
        validationMsg.classList.remove('hidden');
        validationMsg.textContent = 'Verifying...';
        validationMsg.className = 'mt-1 text-sm text-blue-600';
        fetch('{{ route("business.withdrawals.validate-account") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ account_number: an, bank_code: bc })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.valid && data.account_name) {
                setValidated(true, '✓ ' + data.account_name, data.account_name);
                if (bankName && data.bank_name) bankName.value = data.bank_name;
                if (bankCode && data.bank_code) bankCode.value = data.bank_code;
                btnContinue.disabled = false;
            } else {
                setValidated(false, data.message || 'Invalid or inactive account. Please check and try again.', '');
            }
        })
        .catch(() => {
            setValidated(false, 'Verification failed. Try again.', '');
        });
    }

    accountNumber.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
        if (savedSelect && savedSelect.value) savedSelect.value = '';
        isValidated = false;
        setValidated(false, '', '');
        if (this.value.length === 10) validateAccount();
    });

    form.addEventListener('submit', function(e) {
        if (savedSelect && savedSelect.value) return true;
        if (!isValidated) {
            e.preventDefault();
            setValidated(false, 'Please verify your account number first.', '');
            return false;
        }
    });
})();
</script>
@endpush
@endsection
