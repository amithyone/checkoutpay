@extends('layouts.business')

@section('title', 'Bulk payroll')
@section('page-title', 'Pay staff now')

@section('content')
<div class="bg-white rounded-lg border p-6 max-w-2xl">
    <form action="{{ route('business.team.payroll.bulk.store') }}" method="POST" class="space-y-4">
        @csrf
        <p class="text-sm text-gray-600">Pay full monthly salary to selected employees immediately from your business wallet.</p>
        @foreach($employees as $employee)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" checked class="rounded">
                <span>{{ $employee->name }} — ₦{{ number_format($employee->monthly_salary_ngn, 2) }} ({{ $employee->payment_method }})</span>
            </label>
        @endforeach
        <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
        </div>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Run payroll</button>
    </form>
</div>
@endsection
