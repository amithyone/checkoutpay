@extends('layouts.admin')

@section('title', 'Business payroll')
@section('page-title', 'Business payroll')

@section('content')
<div class="bg-white rounded-lg border p-6">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="text-left text-gray-500 border-b">
                <th class="py-2">Batch</th>
                <th>Business</th>
                <th>Status</th>
                <th>Total</th>
                <th>Success/Fail</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($batches as $batch)
                <tr class="border-b">
                    <td class="py-2">#{{ $batch->id }}</td>
                    <td>{{ $batch->business?->name ?? '—' }}</td>
                    <td>{{ $batch->status }}</td>
                    <td>₦{{ number_format($batch->total_amount_ngn, 2) }}</td>
                    <td>{{ $batch->success_count }}/{{ $batch->failed_count }}</td>
                    <td>{{ $batch->created_at->format('M d, Y H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $batches->links() }}
</div>
@endsection
