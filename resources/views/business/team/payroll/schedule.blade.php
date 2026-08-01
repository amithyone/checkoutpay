@extends('layouts.business')

@section('title', 'Schedule salary')
@section('page-title', 'Schedule gradual salary')

@section('content')
<div class="bg-white rounded-lg border p-6 max-w-2xl">
    <form action="{{ route('business.team.payroll.schedule.store') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Schedule name</label>
            <input type="text" name="name" required class="w-full border rounded-lg px-3 py-2" value="Monthly salary">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Cadence</label>
            <select name="cadence" class="w-full border rounded-lg px-3 py-2">
                <option value="weekly">Weekly</option>
                <option value="biweekly">Biweekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Installments per month</label>
            <input type="number" name="installment_count" min="1" max="12" value="4" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Start date</label>
            <input type="date" name="start_date" required value="{{ now()->toDateString() }}" class="w-full border rounded-lg px-3 py-2">
        </div>
        @foreach($employees as $employee)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" checked class="rounded">
                <span>{{ $employee->name }}</span>
            </label>
        @endforeach
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Create schedule</button>
    </form>
</div>
@endsection
