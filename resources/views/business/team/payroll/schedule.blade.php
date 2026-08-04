@extends('layouts.business')

@section('title', 'Schedule salary')
@section('page-title', 'Schedule gradual salary')

@section('content')
<div class="bg-white rounded-lg border p-6 max-w-2xl space-y-4">
    <p class="text-sm text-gray-600">
        Split each employee’s monthly salary into installments (weekly / biweekly / monthly cadence).
        Daily amounts below are monthly ÷ 30 so you can see the trickle rate before creating a schedule.
    </p>

    <form action="{{ route('business.team.payroll.schedule.store') }}" method="POST" class="space-y-4" id="schedule-form">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Schedule name</label>
            <input type="text" name="name" required class="w-full border rounded-lg px-3 py-2" value="{{ old('name', 'Monthly salary') }}">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Cadence</label>
                <select name="cadence" id="schedule_cadence" class="w-full border rounded-lg px-3 py-2 bg-white">
                    <option value="daily">Daily trickle</option>
                    <option value="weekly" selected>Weekly</option>
                    <option value="biweekly">Biweekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Installments per month</label>
                <input type="number" name="installment_count" id="installment_count" min="1" max="31" value="4" class="w-full border rounded-lg px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Auto-fills from cadence; you can override.</p>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Start date</label>
            <input type="date" name="start_date" required value="{{ now()->toDateString() }}" class="w-full border rounded-lg px-3 py-2">
        </div>

        <div class="border rounded-lg divide-y">
            @forelse($employees as $employee)
                <label class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50">
                    <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" checked class="rounded mt-1 employee-check"
                        data-monthly="{{ $employee->monthlyAmount() }}"
                        data-daily="{{ $employee->dailyAmount() }}"
                        data-frequency="{{ $employee->pay_frequency }}">
                    <span class="flex-1">
                        <span class="font-medium text-gray-900">{{ $employee->name }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            ₦{{ number_format($employee->monthlyAmount(), 2) }}/mo ·
                            ₦{{ number_format($employee->dailyAmount(), 2) }}/day ·
                            prefers {{ $employee->frequencyLabel() }} ·
                            {{ $employee->payment_method }}
                        </span>
                    </span>
                </label>
            @empty
                <p class="p-4 text-sm text-gray-500">Add active employees first.</p>
            @endforelse
        </div>

        <div class="rounded-lg bg-gray-50 border px-4 py-3 text-sm">
            <p>Selected monthly total: <strong id="sel-monthly">₦0.00</strong></p>
            <p class="text-gray-600 mt-1">Selected daily estimate: <strong id="sel-daily">₦0.00</strong></p>
            <p class="text-gray-600 mt-1">Approx. per installment: <strong id="sel-installment">₦0.00</strong></p>
        </div>

        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Create schedule</button>
    </form>
</div>

<script>
(function () {
    const cadence = document.getElementById('schedule_cadence');
    const installments = document.getElementById('installment_count');
    const defaults = { daily: 30, weekly: 4, biweekly: 2, monthly: 1 };

    function money(n) {
        return '₦' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function syncCadence() {
        const v = cadence.value;
        if (defaults[v]) installments.value = defaults[v];
        updateTotals();
    }

    function updateTotals() {
        let monthly = 0, daily = 0;
        document.querySelectorAll('.employee-check:checked').forEach(function (el) {
            monthly += parseFloat(el.dataset.monthly || '0') || 0;
            daily += parseFloat(el.dataset.daily || '0') || 0;
        });
        const count = Math.max(1, parseInt(installments.value || '1', 10));
        document.getElementById('sel-monthly').textContent = money(monthly);
        document.getElementById('sel-daily').textContent = money(daily);
        document.getElementById('sel-installment').textContent = money(monthly / count);
    }

    cadence.addEventListener('change', syncCadence);
    installments.addEventListener('input', updateTotals);
    document.querySelectorAll('.employee-check').forEach(function (el) {
        el.addEventListener('change', updateTotals);
    });
    syncCadence();
})();
</script>
@endsection
