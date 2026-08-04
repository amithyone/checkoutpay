@extends('layouts.business')

@section('title', 'Team')
@section('page-title', 'Team & Staff')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 text-sm">{{ session('error') }}</div>
    @endif

    @if(!$linkedWallet)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900">
            Link your CheckoutNow business wallet before running payroll.
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('business.team.payroll.index') }}" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Payroll runs</a>
        <a href="{{ route('business.team.payroll.bulk') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Pay now (bulk)</a>
        <a href="{{ route('business.team.payroll.schedule') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Schedule salary</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add employee</h3>
        <form action="{{ route('business.team.employees.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Monthly salary (₦)</label>
                <input type="number" name="monthly_salary_ngn" min="0" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment method</label>
                <select name="payment_method" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="bank">Bank transfer</option>
                    <option value="wallet">CheckoutNow wallet</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Wallet phone (if wallet)</label>
                <input type="text" name="phone_e164" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
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
                @error('bank_code')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account number</label>
                <input type="text" name="account_number" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Account name</label>
                <input type="text" name="account_name" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
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
                        <th class="py-2 pr-4">Method</th>
                        <th class="py-2 pr-4">Salary</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr class="border-b border-gray-100">
                            <td class="py-3 pr-4">{{ $employee->name }}</td>
                            <td class="py-3 pr-4">{{ ucfirst($employee->payment_method) }}</td>
                            <td class="py-3 pr-4">₦{{ number_format($employee->monthly_salary_ngn, 2) }}</td>
                            <td class="py-3 pr-4">{{ $employee->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="py-3">
                                <form action="{{ route('business.team.employees.destroy', $employee) }}" method="POST" onsubmit="return confirm('Remove employee?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-gray-500">No employees yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
