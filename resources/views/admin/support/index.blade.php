@extends('layouts.admin')

@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
<div class="space-y-6">
    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Unread</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['unread'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-bell text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Open</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-folder-open text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">In Progress</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-spinner text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Resolved</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['resolved'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Closed</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['closed'] }}</p>
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-times-circle text-gray-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 sm:gap-4">
            <select name="status" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Status</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
            <select name="channel" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Channels</option>
                <option value="checkout_web" {{ request('channel') === 'checkout_web' ? 'selected' : '' }}>Website</option>
                <option value="checkoutnow_app" {{ request('channel') === 'checkoutnow_app' ? 'selected' : '' }}>CheckoutNow</option>
                <option value="business_dashboard" {{ request('channel') === 'business_dashboard' ? 'selected' : '' }}>Business</option>
            </select>
            <select name="priority" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">All Priorities</option>
                <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
            </select>
            <div class="flex gap-2 sm:contents">
                <button type="submit" class="flex-1 sm:flex-none bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm font-medium">Filter</button>
                <a href="{{ route('admin.support.index') }}" class="flex-1 sm:flex-none text-center text-gray-600 hover:text-gray-900 text-sm py-2">Clear</a>
            </div>
        </form>
    </div>

    <!-- Mobile ticket cards -->
    <div class="md:hidden space-y-3">
        @forelse($tickets as $ticket)
        @php
            $priorityColors = [
                'low' => 'bg-gray-100 text-gray-800',
                'medium' => 'bg-blue-100 text-blue-800',
                'high' => 'bg-orange-100 text-orange-800',
                'urgent' => 'bg-red-100 text-red-800',
            ];
            $statusColors = [
                'open' => 'bg-yellow-100 text-yellow-800',
                'in_progress' => 'bg-blue-100 text-blue-800',
                'resolved' => 'bg-green-100 text-green-800',
                'closed' => 'bg-gray-100 text-gray-800',
            ];
        @endphp
        <a href="{{ route('admin.support.show', $ticket) }}"
           class="block bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:border-primary/40 {{ $ticket->admin_unread_count > 0 ? 'border-primary/30 bg-primary/5' : '' }}">
            <div class="flex items-start justify-between gap-2 mb-2">
                <span class="text-xs font-mono text-gray-600">{{ $ticket->ticket_number }}</span>
                @if($ticket->admin_unread_count > 0)
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-red-500 rounded-full">{{ $ticket->admin_unread_count }}</span>
                @endif
            </div>
            <p class="font-medium text-gray-900 text-sm break-words">{{ $ticket->subject }}</p>
            <p class="text-sm text-gray-600 mt-1">{{ $ticket->displayName() }}</p>
            <div class="flex flex-wrap gap-2 mt-3">
                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $priorityColors[$ticket->priority] ?? 'bg-gray-100' }}">{{ ucfirst($ticket->priority) }}</span>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                <span class="text-xs text-gray-500">{{ $ticket->created_at->format('M d, Y') }}</span>
            </div>
        </a>
        @empty
        <div class="bg-white rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">No tickets found</div>
        @endforelse
        @if($tickets->hasPages())
        <div class="py-2">{{ $tickets->links() }}</div>
        @endif
    </div>

    <!-- Desktop table -->
    <div class="hidden md:block bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($tickets as $ticket)
                <tr class="hover:bg-gray-50 {{ $ticket->admin_unread_count > 0 ? 'bg-primary/5' : '' }}">
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">
                        {{ $ticket->ticket_number }}
                        @if($ticket->admin_unread_count > 0)
                            <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-red-500 rounded-full">{{ $ticket->admin_unread_count }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $ticket->displayName() }}
                        @if($ticket->isWalletLinked())
                            <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">WhatsApp linked</span>
                        @elseif($ticket->isPublicChannel())
                            <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700">Anonymous</span>
                        @endif
                        @if($ticket->visitor_phone)
                            <span class="block text-xs text-gray-400">{{ $ticket->visitor_phone }}@if($ticket->visitor_country) · {{ $ticket->visitor_country }}@endif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500">{{ str_replace('_', ' ', $ticket->channel ?? 'business_dashboard') }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        {{ \Illuminate\Support\Str::limit($ticket->subject, 50) }}
                        @if($ticket->payment_transaction_id)
                            <span class="block text-xs font-mono text-amber-700 mt-0.5">{{ $ticket->payment_transaction_id }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $priorityColors = [
                                'low' => 'bg-gray-100 text-gray-800',
                                'medium' => 'bg-blue-100 text-blue-800',
                                'high' => 'bg-orange-100 text-orange-800',
                                'urgent' => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $priorityColors[$ticket->priority] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst($ticket->priority) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $statusColors = [
                                'open' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'resolved' => 'bg-green-100 text-green-800',
                                'closed' => 'bg-gray-100 text-gray-800',
                            ];
                        @endphp
                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $ticket->assignedAdmin->name ?? 'Unassigned' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->format('M d, Y') }}</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.support.show', $ticket) }}" class="text-primary hover:underline text-sm">
                            <i class="fas fa-comments"></i> Open
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">No tickets found</td>
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
