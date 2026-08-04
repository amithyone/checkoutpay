@extends('layouts.business')

@section('title', 'Bulk payroll')
@section('page-title', 'Pay staff now')

@section('content')
@php
    $cycleTotal = round($employees->sum(fn ($e) => $e->amountPerPayCycle()), 2);
    $monthlyTotal = round($employees->sum(fn ($e) => $e->monthlyAmount()), 2);
@endphp
<div class="bg-white rounded-lg border p-6 max-w-2xl space-y-4">
    <p class="text-sm text-gray-600">
        Pays from your <strong>business balance</strong>. By default each staff gets only their
        <strong>current pay-cycle amount</strong> (daily / weekly / biweekly / monthly) — not the full month
        unless you choose that below.
    </p>

    <div class="rounded-lg bg-gray-50 border px-4 py-3 text-sm grid grid-cols-2 gap-3">
        <div>
            <p class="text-xs text-gray-500">Business balance available</p>
            <p class="font-semibold">₦{{ number_format($businessBalance ?? $business->getAvailableBalance(), 2) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">This run will pay</p>
            <p class="font-semibold text-primary" id="bulk-pay-now">₦{{ number_format($cycleTotal, 2) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">If full monthly</p>
            <p class="font-semibold" id="bulk-monthly">₦{{ number_format($monthlyTotal, 2) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Selected cycle total</p>
            <p class="font-semibold" id="bulk-cycle">₦{{ number_format($cycleTotal, 2) }}</p>
        </div>
    </div>

    <form action="{{ route('business.team.payroll.bulk.store') }}" method="POST" class="space-y-4" id="bulk-form">
        @csrf
        <div class="space-y-2 border rounded-lg p-3">
            <label class="flex items-start gap-2 text-sm">
                <input type="radio" name="amount_mode" value="cycle" class="mt-1 amount-mode" checked>
                <span>
                    <span class="font-medium text-gray-900">Pay this cycle only</span>
                    <span class="block text-xs text-gray-500">Uses each person’s pay frequency (e.g. daily trickle = monthly ÷ 30).</span>
                </span>
            </label>
            <label class="flex items-start gap-2 text-sm">
                <input type="radio" name="amount_mode" value="monthly" class="mt-1 amount-mode">
                <span>
                    <span class="font-medium text-gray-900">Pay full monthly salary</span>
                    <span class="block text-xs text-gray-500">Sends the whole month in one go (only if you intend that).</span>
                </span>
            </label>
        </div>

        <div class="border rounded-lg divide-y">
            @forelse($employees as $employee)
                <label class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50">
                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" checked
                        class="rounded mt-1 bulk-check"
                        data-monthly="{{ $employee->monthlyAmount() }}"
                        data-cycle="{{ $employee->amountPerPayCycle() }}">
                    <span class="flex-1">
                        <span class="font-medium text-gray-900">{{ $employee->name }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            Cycle: <strong>₦{{ number_format($employee->amountPerPayCycle(), 2) }}</strong>
                            ({{ $employee->frequencyLabel() }}) ·
                            Month: ₦{{ number_format($employee->monthlyAmount(), 2) }} ·
                            {{ ucfirst($employee->payment_method) }} → {{ $employee->paymentDestinationLabel() }}
                        </span>
                    </span>
                </label>
            @empty
                <p class="p-4 text-sm text-gray-500">No active employees.</p>
            @endforelse
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
        </div>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg" id="bulk-submit">
            Pay cycle amounts
        </button>
    </form>
</div>

<script>
(function () {
    function money(n) {
        return '₦' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function mode() {
        var el = document.querySelector('.amount-mode:checked');
        return el ? el.value : 'cycle';
    }
    function update() {
        var m = 0, c = 0;
        document.querySelectorAll('.bulk-check:checked').forEach(function (el) {
            m += parseFloat(el.dataset.monthly || '0') || 0;
            c += parseFloat(el.dataset.cycle || '0') || 0;
        });
        var pay = mode() === 'monthly' ? m : c;
        document.getElementById('bulk-monthly').textContent = money(m);
        document.getElementById('bulk-cycle').textContent = money(c);
        document.getElementById('bulk-pay-now').textContent = money(pay);
        document.getElementById('bulk-submit').textContent = mode() === 'monthly'
            ? 'Pay full monthly salaries'
            : 'Pay cycle amounts';
    }
    document.querySelectorAll('.bulk-check, .amount-mode').forEach(function (el) {
        el.addEventListener('change', update);
    });
    update();
})();
</script>
@endsection
