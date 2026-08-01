@extends('layouts.business')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 text-sm">{{ session('success') }}</div>
    @endif

    <div class="flex gap-3">
        <a href="{{ route('business.team.index') }}" class="text-sm text-primary">← Team</a>
        <a href="{{ route('business.team.payroll.bulk') }}" class="px-3 py-1.5 bg-primary text-white rounded text-sm">Pay now</a>
        <a href="{{ route('business.team.payroll.schedule') }}" class="px-3 py-1.5 border rounded text-sm">New schedule</a>
    </div>

    <div class="bg-white rounded-lg border p-6">
        <h3 class="font-semibold mb-4">Recent batches</h3>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-gray-500 border-b"><th class="py-2">ID</th><th>Kind</th><th>Status</th><th>Total</th><th>When</th></tr></thead>
            <tbody>
                @forelse($batches as $batch)
                    <tr class="border-b">
                        <td class="py-2">#{{ $batch->id }}</td>
                        <td>{{ $batch->kind }}</td>
                        <td>{{ $batch->status }}</td>
                        <td>₦{{ number_format($batch->total_amount_ngn, 2) }}</td>
                        <td>{{ $batch->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-gray-500">No payroll runs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $batches->links() }}
    </div>

    <div class="bg-white rounded-lg border p-6">
        <h3 class="font-semibold mb-4">Schedules</h3>
        <ul class="space-y-2 text-sm">
            @forelse($schedules as $schedule)
                <li>{{ $schedule->name }} — {{ $schedule->cadence }} — {{ $schedule->status }}</li>
            @empty
                <li class="text-gray-500">No schedules yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
