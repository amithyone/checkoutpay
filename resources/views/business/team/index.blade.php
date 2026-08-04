@extends('layouts.business')

@section('title', 'Team')
@section('page-title', 'Team & Staff')

@section('content')
@php
    $active = $employees->where('is_active', true);
    $monthlyTotal = round($active->sum(fn ($e) => (float) $e->monthly_salary_ngn), 2);
    $dailyTotal = round($monthlyTotal / \App\Models\BusinessEmployee::DAYS_PER_MONTH, 2);
    $weeklyTotal = round($dailyTotal * 7, 2);
@endphp
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(isset($businessBalance))
        <div class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
            Payroll is funded from your <strong>business balance</strong>
            (available: <strong>₦{{ number_format($businessBalance, 2) }}</strong>), not your personal CheckoutNow wallet.
        </div>
    @endif

    @if(!$linkedWallet)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
            Optional: link a CheckoutNow wallet for app visibility. Staff payouts still debit this business account balance.
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500">Active staff (monthly)</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">₦{{ number_format($monthlyTotal, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $active->count() }} active · {{ $employees->count() }} total</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500">Est. daily payroll</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">₦{{ number_format($dailyTotal, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Monthly ÷ 30 days</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs uppercase tracking-wide text-gray-500">Est. weekly payroll</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">₦{{ number_format($weeklyTotal, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Daily × 7</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('business.team.payroll.index') }}" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Payroll runs</a>
        <a href="{{ route('business.team.payroll.bulk') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Pay now (bulk)</a>
        <a href="{{ route('business.team.payroll.schedule') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Schedule salary</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900">Add employee</h3>
        <p class="text-sm text-gray-500 mt-1 mb-4">Enter monthly salary — we show daily and per-cycle amounts so you can plan bank or wallet payouts.</p>

        <form action="{{ route('business.team.employees.store') }}" method="POST" id="employee-form" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly salary (₦)</label>
                    <input type="number" name="monthly_salary_ngn" id="monthly_salary_ngn" value="{{ old('monthly_salary_ngn') }}" min="0" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>

            <div id="salary-preview" class="rounded-lg border border-teal-100 bg-teal-50/60 px-4 py-3 text-sm text-teal-950 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <p class="text-xs text-teal-700">Est. daily</p>
                    <p class="font-semibold" data-preview="daily">₦0.00</p>
                </div>
                <div>
                    <p class="text-xs text-teal-700">Est. weekly</p>
                    <p class="font-semibold" data-preview="weekly">₦0.00</p>
                </div>
                <div>
                    <p class="text-xs text-teal-700">Per pay cycle</p>
                    <p class="font-semibold" data-preview="cycle">₦0.00</p>
                    <p class="text-xs text-teal-700 mt-0.5" data-preview="cycle-label">Monthly</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">How often are they paid?</label>
                    <select name="pay_frequency" id="pay_frequency" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        @foreach(\App\Models\BusinessEmployee::frequencyOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('pay_frequency', 'monthly') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Used when you schedule gradual salary; daily = trickle across the month.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pay day hint (optional)</label>
                    <input type="text" name="pay_day_hint" value="{{ old('pay_day_hint') }}" maxlength="40" placeholder="e.g. Fridays, 25th of month" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payout destination</label>
                    <select name="payment_method" id="payment_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="bank" @selected(old('payment_method', 'bank') === 'bank')>Bank transfer</option>
                        <option value="wallet" @selected(old('payment_method') === 'wallet')>CheckoutNow wallet</option>
                    </select>
                </div>
                <div>
                    <label class="flex items-center gap-2 mt-8 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300 text-primary">
                        Active (include in payroll)
                    </label>
                </div>
            </div>

            <div id="wallet-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ old('payment_method') === 'wallet' ? '' : 'hidden' }}">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wallet phone</label>
                    <input type="text" name="phone_e164" value="{{ old('phone_e164') }}" placeholder="e.g. 08012345678" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">Must match their CheckoutNow / WhatsApp wallet number.</p>
                </div>
            </div>

            <div id="bank-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ old('payment_method', 'bank') === 'bank' ? '' : 'hidden' }}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                    <select name="bank_code" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Select bank…</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank['code'] }}" @selected(old('bank_code') === $bank['code'])>
                                {{ $bank['name'] }} ({{ $bank['code'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account number</label>
                    <input type="text" name="account_number" value="{{ old('account_number') }}" inputmode="numeric" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account name</label>
                    <input type="text" name="account_name" value="{{ old('account_name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Role, department, or payout notes">{{ old('notes') }}</textarea>
            </div>

            <div>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Add employee</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Employees</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Pay cycle</th>
                        <th class="py-2 pr-4">Monthly</th>
                        <th class="py-2 pr-4">Daily</th>
                        <th class="py-2 pr-4">Per cycle</th>
                        <th class="py-2 pr-4">Paid to</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr class="border-b border-gray-100 align-top">
                            <td class="py-3 pr-4">
                                <div class="font-medium text-gray-900">{{ $employee->name }}</div>
                                @if($employee->pay_day_hint)
                                    <div class="text-xs text-gray-500">{{ $employee->pay_day_hint }}</div>
                                @endif
                            </td>
                            <td class="py-3 pr-4">{{ $employee->frequencyLabel() }}</td>
                            <td class="py-3 pr-4">₦{{ number_format($employee->monthlyAmount(), 2) }}</td>
                            <td class="py-3 pr-4 font-medium text-gray-900">₦{{ number_format($employee->dailyAmount(), 2) }}</td>
                            <td class="py-3 pr-4">₦{{ number_format($employee->amountPerPayCycle(), 2) }}</td>
                            <td class="py-3 pr-4">
                                <div class="capitalize">{{ $employee->payment_method }}</div>
                                <div class="text-xs text-gray-500">{{ $employee->paymentDestinationLabel() }}</div>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $employee->is_active ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3">
                                <form action="{{ route('business.team.employees.destroy', $employee) }}" method="POST" onsubmit="return confirm('Remove employee?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-gray-500">No employees yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const salaryInput = document.getElementById('monthly_salary_ngn');
    const frequencySelect = document.getElementById('pay_frequency');
    const methodSelect = document.getElementById('payment_method');
    const bankFields = document.getElementById('bank-fields');
    const walletFields = document.getElementById('wallet-fields');
    const days = {{ \App\Models\BusinessEmployee::DAYS_PER_MONTH }};

    function money(n) {
        return '₦' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updatePreview() {
        const monthly = Math.max(0, parseFloat(salaryInput.value || '0') || 0);
        const daily = monthly / days;
        const weekly = daily * 7;
        const freq = frequencySelect.value;
        let cycle = monthly;
        let label = 'Monthly';
        if (freq === 'daily') { cycle = daily; label = 'Daily trickle'; }
        else if (freq === 'weekly') { cycle = weekly; label = 'Weekly'; }
        else if (freq === 'biweekly') { cycle = daily * 14; label = 'Every 2 weeks'; }

        document.querySelector('[data-preview="daily"]').textContent = money(daily);
        document.querySelector('[data-preview="weekly"]').textContent = money(weekly);
        document.querySelector('[data-preview="cycle"]').textContent = money(cycle);
        document.querySelector('[data-preview="cycle-label"]').textContent = label;
    }

    function syncMethod() {
        const isWallet = methodSelect.value === 'wallet';
        bankFields.classList.toggle('hidden', isWallet);
        walletFields.classList.toggle('hidden', !isWallet);
    }

    salaryInput.addEventListener('input', updatePreview);
    frequencySelect.addEventListener('change', updatePreview);
    methodSelect.addEventListener('change', syncMethod);
    updatePreview();
    syncMethod();
})();
</script>
@endsection
