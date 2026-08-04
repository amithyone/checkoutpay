@extends('layouts.business')

@section('title', 'Bulk payroll')
@section('page-title', 'Pay staff now')

@section('content')
@php
    $monthlyTotal = round($employees->sum(fn ($e) => (float) $e->monthly_salary_ngn), 2);
    $dailyTotal = round($monthlyTotal / \App\Models\BusinessEmployee::DAYS_PER_MONTH, 2);
@endphp
<div class="bg-white rounded-lg border p-6 max-w-2xl space-y-4">
    <p class="text-sm text-gray-600">
        Pay <strong>full monthly salary</strong> to selected employees immediately from your business wallet.
        Daily figures are estimates (monthly ÷ 30) for planning only — this run pays the monthly amount.
    </p>

    <div class="rounded-lg bg-gray-50 border px-4 py-3 text-sm grid grid-cols-2 gap-3">
        <div>
            <p class="text-xs text-gray-500">Selected monthly total</p>
            <p class="font-semibold" id="bulk-monthly">₦{{ number_format($monthlyTotal, 2) }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Est. daily (all selected)</p>
            <p class="font-semibold" id="bulk-daily">₦{{ number_format($dailyTotal, 2) }}</p>
        </div>
    </div>

    <form action="{{ route('business.team.payroll.bulk.store') }}" method="POST" class="space-y-4">
        @csrf
        <div class="border rounded-lg divide-y">
            @forelse($employees as $employee)
                <label class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50">
                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" checked
                        class="rounded mt-1 bulk-check"
                        data-monthly="{{ $employee->monthlyAmount() }}"
                        data-daily="{{ $employee->dailyAmount() }}">
                    <span class="flex-1">
                        <span class="font-medium text-gray-900">{{ $employee->name }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            ₦{{ number_format($employee->monthlyAmount(), 2) }}/mo ·
                            ₦{{ number_format($employee->dailyAmount(), 2) }}/day ·
                            {{ $employee->frequencyLabel() }} ·
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
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Run payroll</button>
    </form>
</div>

<script>
(function () {
    function money(n) {
        return '₦' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function update() {
        let m = 0, d = 0;
        document.querySelectorAll('.bulk-check:checked').forEach(function (el) {
            m += parseFloat(el.dataset.monthly || '0') || 0;
            d += parseFloat(el.dataset.daily || '0') || 0;
        });
        document.getElementById('bulk-monthly').textContent = money(m);
        document.getElementById('bulk-daily').textContent = money(d);
    }
    document.querySelectorAll('.bulk-check').forEach(function (el) {
        el.addEventListener('change', update);
    });
})();
</script>
@endsection
