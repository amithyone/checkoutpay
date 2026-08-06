@extends('layouts.admin')

@section('title', 'Overdraft loan applications')
@section('page-title', 'Overdraft loan applications')

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Business</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Amount</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Repayment</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Requested</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($applications as $b)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ $b->name }}</p>
                        <p class="text-xs text-gray-500">{{ $b->email }}</p>
                        @if($b->overdraft_application_notes)
                            <p class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($b->overdraft_application_notes, 80) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">
                        @if($b->overdraft_requested_amount)
                            ₦{{ number_format((float) $b->overdraft_requested_amount, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $b->overdraft_repayment_mode === 'split_30d' ? 'Split (~30d)' : 'Single' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $b->overdraft_requested_at?->format('M d, Y H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.businesses.show', $b) }}" class="text-primary hover:underline">Review</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No pending applications.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-gray-200">{{ $applications->links() }}</div>
</div>
@endsection
