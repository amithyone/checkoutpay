@extends('layouts.business')

@section('title', 'Support Ticket')
@section('page-title', 'Support Ticket: ' . $ticket->ticket_number)

@section('content')
<div class="max-w-4xl space-y-6">
    <!-- Ticket Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $ticket->subject }}</h2>
                <p class="text-sm text-gray-500 mt-1">Ticket #{{ $ticket->ticket_number }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if($ticket->status === 'open')
                    <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded-full">Open</span>
                @elseif($ticket->status === 'in_progress')
                    <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">In Progress</span>
                @elseif($ticket->status === 'resolved')
                    <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">Resolved</span>
                @else
                    <span class="px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800 rounded-full">Closed</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-500">Priority:</span>
                <span class="font-medium text-gray-900 ml-2 capitalize">{{ $ticket->priority }}</span>
            </div>
            <div>
                <span class="text-gray-500">Created:</span>
                <span class="font-medium text-gray-900 ml-2">{{ $ticket->created_at->format('M d, Y') }}</span>
            </div>
            <div>
                <span class="text-gray-500">Replies:</span>
                <span class="font-medium text-gray-900 ml-2">{{ $ticket->replies->count() }}</span>
            </div>
            @if($ticket->resolved_at)
            <div>
                <span class="text-gray-500">Resolved:</span>
                <span class="font-medium text-gray-900 ml-2">{{ $ticket->resolved_at->format('M d, Y') }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Initial Message -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                {{ substr($ticket->business->name, 0, 1) }}
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-gray-900">{{ $ticket->business->name }}</span>
                    <span class="text-sm text-gray-500">{{ $ticket->created_at->format('M d, Y h:i A') }}</span>
                </div>
                <div class="text-gray-700 whitespace-pre-wrap">{{ $ticket->message }}</div>
            </div>
        </div>
    </div>

    <!-- Replies -->
    @foreach($ticket->replies->where('is_internal_note', false) as $reply)
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-{{ $reply->user_type === 'business' ? 'primary' : 'gray-600' }} rounded-full flex items-center justify-center text-white font-semibold">
                @if($reply->user_type === 'business')
                    {{ substr($reply->user->name ?? 'B', 0, 1) }}
                @else
                    A
                @endif
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-gray-900">
                        {{ $reply->user_type === 'business' ? ($reply->user->name ?? 'You') : 'Support Team' }}
                    </span>
                    <span class="text-sm text-gray-500">{{ $reply->created_at->format('M d, Y h:i A') }}</span>
                </div>
                <div class="text-gray-700 whitespace-pre-wrap">{{ $reply->message }}</div>
            </div>
        </div>
    </div>
    @endforeach

    <!-- Reply Form -->
    @if(!in_array($ticket->status, ['resolved', 'closed']))
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Reply</h3>
        <form action="{{ route('business.support.reply', $ticket) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <textarea name="message" rows="6" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Type your message here..."></textarea>
                    @error('message')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                        <i class="fas fa-paper-plane mr-2"></i> Send Reply
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endif

    <div class="text-center">
        <a href="{{ route('business.support.index') }}" class="text-primary hover:underline">
            <i class="fas fa-arrow-left mr-2"></i> Back to Tickets
        </a>
    </div>
</div>
@endsection
