@extends('layouts.admin')

@section('title', 'Transaction Log Details')
@section('page-title', 'Transaction Log: ' . $transactionId)

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Timeline</h3>
        
        <div class="space-y-4">
            @foreach($logs as $log)
            <div class="border-l-4 border-primary pl-4 py-2">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                            {{ str_replace('_', ' ', ucwords($log->event_type, '_')) }}
                        </span>
                        <span class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y H:i:s') }}</span>
                    </div>
                </div>
                <p class="text-sm text-gray-900 mb-1">{{ $log->description }}</p>
                @if($log->business)
                    <p class="text-xs text-gray-600">Business: {{ $log->business->name }}</p>
                @endif
                @if($log->metadata)
                    <details class="mt-2">
                        <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">View Details</summary>
                        <pre class="mt-2 text-xs bg-gray-50 p-2 rounded overflow-x-auto">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
