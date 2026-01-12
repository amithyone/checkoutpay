@extends('layouts.admin')

@section('title', 'Support Ticket')
@section('page-title', 'Support Ticket: ' . $ticket->ticket_number)

@section('content')
<div class="space-y-6">
    <!-- Ticket Info -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $ticket->subject }}</h3>
                <p class="text-sm text-gray-600 mt-1">Business: {{ $ticket->business->name ?? 'N/A' }}</p>
            </div>
            <div class="flex items-center gap-3">
                @php
                    $statusColors = [
                        'open' => 'bg-yellow-100 text-yellow-800',
                        'in_progress' => 'bg-blue-100 text-blue-800',
                        'resolved' => 'bg-green-100 text-green-800',
                        'closed' => 'bg-gray-100 text-gray-800',
                    ];
                    $priorityColors = [
                        'low' => 'bg-gray-100 text-gray-800',
                        'medium' => 'bg-blue-100 text-blue-800',
                        'high' => 'bg-orange-100 text-orange-800',
                        'urgent' => 'bg-red-100 text-red-800',
                    ];
                @endphp
                <span class="px-3 py-1 text-sm font-medium rounded-full {{ $priorityColors[$ticket->priority] ?? 'bg-gray-100' }}">
                    {{ ucfirst($ticket->priority) }}
                </span>
                <span class="px-3 py-1 text-sm font-medium rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100' }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>
        </div>

        <!-- Status Update Form -->
        <form action="{{ route('admin.support.update-status', $ticket) }}" method="POST" class="mb-4 p-4 bg-gray-50 rounded-lg">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Update Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                    <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Unassigned</option>
                        @foreach(\App\Models\Admin::all() as $admin)
                            <option value="{{ $admin->id }}" {{ $ticket->assigned_to === $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="mt-3 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                Update
            </button>
        </form>

        <!-- Initial Message -->
        <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <div class="flex items-start space-x-3">
                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center">
                    {{ substr($ticket->business->name ?? 'B', 0, 1) }}
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-gray-900">{{ $ticket->business->name ?? 'Business' }}</span>
                        <span class="text-xs text-gray-500">{{ $ticket->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $ticket->message }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Messages -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Conversation</h4>
        <div id="chat-messages" class="space-y-4 mb-4" style="max-height: 500px; overflow-y: auto;">
            @foreach($ticket->replies as $reply)
            <div class="flex items-start space-x-3 {{ $reply->user_type === 'admin' ? 'flex-row-reverse space-x-reverse' : '' }}">
                <div class="w-10 h-10 rounded-full {{ $reply->user_type === 'admin' ? 'bg-primary text-white' : 'bg-gray-300 text-gray-700' }} flex items-center justify-center">
                    @if($reply->user_type === 'admin')
                        {{ substr($reply->user->name ?? 'A', 0, 1) }}
                    @else
                        {{ substr($reply->user->name ?? 'B', 0, 1) }}
                    @endif
                </div>
                <div class="flex-1 {{ $reply->user_type === 'admin' ? 'text-right' : '' }}">
                    <div class="inline-block bg-gray-100 rounded-lg p-3 max-w-md">
                        <div class="flex items-center justify-between mb-1 {{ $reply->user_type === 'admin' ? 'flex-row-reverse' : '' }}">
                            <span class="text-xs font-medium text-gray-700">
                                {{ $reply->user_type === 'admin' ? ($reply->user->name ?? 'Admin') : ($reply->user->name ?? 'Business') }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $reply->created_at->format('H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $reply->message }}</p>
                        @if($reply->is_internal_note)
                            <span class="text-xs text-orange-600 mt-1 block">Internal Note</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Reply Form -->
        <form id="reply-form" class="border-t border-gray-200 pt-4">
            @csrf
            <div class="flex items-start space-x-3">
                <div class="flex-1">
                    <textarea id="reply-message" rows="3" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                        placeholder="Type your reply..."></textarea>
                    <div class="mt-2 flex items-center space-x-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="is-internal-note" 
                                class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-700">Internal note (not visible to business)</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                    <i class="fas fa-paper-plane mr-2"></i> Send
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('reply-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = document.getElementById('reply-message').value.trim();
    const isInternal = document.getElementById('is-internal-note').checked;
    
    if (!message) {
        alert('Please enter a message');
        return;
    }

    const formData = new FormData();
    formData.append('message', message);
    formData.append('is_internal_note', isInternal ? '1' : '0');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

    try {
        const response = await fetch('{{ route("admin.support.reply", $ticket) }}', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Add message to chat
            const chatMessages = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex items-start space-x-3 flex-row-reverse space-x-reverse';
            messageDiv.innerHTML = `
                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center">
                    {{ substr(auth('admin')->user()->name, 0, 1) }}
                </div>
                <div class="flex-1 text-right">
                    <div class="inline-block bg-gray-100 rounded-lg p-3 max-w-md">
                        <div class="flex items-center justify-between mb-1 flex-row-reverse">
                            <span class="text-xs font-medium text-gray-700">{{ auth('admin')->user()->name }}</span>
                            <span class="text-xs text-gray-500">${data.reply.created_at_human}</span>
                        </div>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">${data.reply.message}</p>
                        ${isInternal ? '<span class="text-xs text-orange-600 mt-1 block">Internal Note</span>' : ''}
                    </div>
                </div>
            `;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Clear form
            document.getElementById('reply-message').value = '';
            document.getElementById('is-internal-note').checked = false;
        } else {
            alert('Error sending reply');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error sending reply');
    }
});
</script>
@endsection
