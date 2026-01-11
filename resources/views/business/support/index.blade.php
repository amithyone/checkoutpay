@extends('layouts.business')

@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Support Tickets</h2>
            <p class="text-gray-600 mt-1">Get help from our support team</p>
        </div>
        <a href="{{ route('business.support.create') }}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
            <i class="fas fa-plus mr-2"></i> New Ticket
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('business.support.index') }}" 
               class="px-3 py-1 rounded-lg {{ !request('status') ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                All
            </a>
            <a href="{{ route('business.support.index', ['status' => 'open']) }}" 
               class="px-3 py-1 rounded-lg {{ request('status') === 'open' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Open
            </a>
            <a href="{{ route('business.support.index', ['status' => 'in_progress']) }}" 
               class="px-3 py-1 rounded-lg {{ request('status') === 'in_progress' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                In Progress
            </a>
            <a href="{{ route('business.support.index', ['status' => 'resolved']) }}" 
               class="px-3 py-1 rounded-lg {{ request('status') === 'resolved' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Resolved
            </a>
            <a href="{{ route('business.support.index', ['status' => 'closed']) }}" 
               class="px-3 py-1 rounded-lg {{ request('status') === 'closed' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Closed
            </a>
        </div>
    </div>

    <!-- Tickets List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Replies</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($tickets as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $ticket->ticket_number }}</td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $ticket->subject }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($ticket->message, 60) }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($ticket->priority === 'urgent')
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Urgent</span>
                            @elseif($ticket->priority === 'high')
                                <span class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">High</span>
                            @elseif($ticket->priority === 'medium')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Medium</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Low</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($ticket->status === 'open')
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Open</span>
                            @elseif($ticket->status === 'in_progress')
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">In Progress</span>
                            @elseif($ticket->status === 'resolved')
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Resolved</span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Closed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $ticket->replies->count() }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('business.support.show', $ticket) }}" class="text-primary hover:underline text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-ticket-alt text-4xl mb-4 text-gray-300"></i>
                            <p>No support tickets found</p>
                            <a href="{{ route('business.support.create') }}" class="mt-4 inline-block text-primary hover:underline">Create your first ticket</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
