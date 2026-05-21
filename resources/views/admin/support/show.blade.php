@extends('layouts.admin')

@section('title', 'Support Ticket')
@section('page-title', 'Support Ticket: ' . $ticket->ticket_number)

@section('content')
@php
    $isPublic = $ticket->isPublicChannel();
    $displayName = $ticket->displayName();
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
    $lastReplyId = $ticket->replies->max('id') ?? 0;
@endphp
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-start justify-between mb-4 flex-wrap gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $ticket->subject }}</h3>
                <p class="text-sm text-gray-600 mt-1 flex flex-wrap items-center gap-2">
                    From: <strong>{{ $displayName }}</strong>
                    @if($ticket->isWalletLinked())
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">WhatsApp linked</span>
                    @elseif($ticket->isPublicChannel())
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700">Anonymous</span>
                    @endif
                    @if($ticket->visitor_phone)
                        · WhatsApp: <span class="font-mono">{{ $ticket->visitor_phone }}</span>
                        @if($ticket->visitor_country)
                            · {{ $ticket->visitor_country }}
                        @endif
                    @endif
                    @if($ticket->whatsapp_wallet_id)
                        · Wallet #{{ $ticket->whatsapp_wallet_id }}
                    @endif
                </p>
                <p class="text-xs text-gray-500 mt-1">Channel: {{ str_replace('_', ' ', $ticket->channel) }}</p>
                @if($ticket->issueTypeLabel())
                    <p class="text-xs text-gray-600 mt-2">
                        <span class="font-medium">Issue:</span> {{ $ticket->issueTypeLabel() }}
                    </p>
                @endif
                @if($ticket->payment_transaction_id || $ticket->payment)
                    <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm">
                        <p class="font-semibold text-amber-900 mb-1">Payment context</p>
                        <p class="text-amber-900">
                            Bank session ID: <span class="font-mono">{{ $ticket->payment_transaction_id ?? $ticket->payment?->transaction_id }}</span>
                            @if($ticket->payment_amount_reported)
                                · Reported: ₦{{ number_format((float) $ticket->payment_amount_reported, 2) }}
                            @endif
                        </p>
                        @if($ticket->payment)
                            <p class="text-xs text-amber-800 mt-1">
                                System: ₦{{ number_format((float) $ticket->payment->amount, 2) }}
                                · Status: <strong>{{ $ticket->payment->status }}</strong>
                                @if($ticket->payment->expires_at)
                                    · Expires {{ $ticket->payment->expires_at->format('M d, H:i') }}
                                @endif
                            </p>
                            <a href="{{ route('admin.payments.show', $ticket->payment) }}" class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-primary hover:underline">
                                <i class="fas fa-external-link-alt"></i> Open payment in admin
                            </a>
                        @else
                            <p class="text-xs text-amber-800 mt-1">No matching payment row — search payments by session ID.</p>
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 text-sm font-medium rounded-full {{ $priorityColors[$ticket->priority] ?? 'bg-gray-100' }}">
                    {{ ucfirst($ticket->priority) }}
                </span>
                <span class="px-3 py-1 text-sm font-medium rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100' }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>
        </div>

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

        <div class="bg-gray-50 rounded-lg p-4 mb-4" data-message-id="0">
            <div class="flex items-start space-x-3">
                <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-700 flex items-center justify-center">
                    {{ substr($displayName, 0, 1) }}
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-gray-900">{{ $displayName }}</span>
                        <span class="text-xs text-gray-500">{{ $ticket->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $ticket->message }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Conversation</h4>
        <div id="chat-messages" class="space-y-4 mb-4" style="max-height: 500px; overflow-y: auto;">
            @foreach($ticket->replies as $reply)
                @if($reply->is_internal_note)
                    @continue
                @endif
            <div class="flex items-start space-x-3 {{ $reply->user_type === 'admin' ? 'flex-row-reverse space-x-reverse' : '' }}" data-message-id="{{ $reply->id }}">
                <div class="w-10 h-10 rounded-full {{ $reply->user_type === 'admin' ? 'bg-primary text-white' : 'bg-gray-300 text-gray-700' }} flex items-center justify-center">
                    {{ substr($reply->authorLabel(), 0, 1) }}
                </div>
                <div class="flex-1 {{ $reply->user_type === 'admin' ? 'text-right' : '' }}">
                    <div class="inline-block bg-gray-100 rounded-lg p-3 max-w-md">
                        <div class="flex items-center justify-between mb-1 {{ $reply->user_type === 'admin' ? 'flex-row-reverse' : '' }}">
                            <span class="text-xs font-medium text-gray-700">{{ $reply->authorLabel() }}</span>
                            <span class="text-xs text-gray-500">{{ $reply->created_at->format('H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $reply->message }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

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
                            <span class="text-sm text-gray-700">Internal note (not visible to {{ $isPublic ? 'visitor' : 'business' }})</span>
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
(function() {
    const ticketId = {{ $ticket->id }};
    let lastId = {{ (int) $lastReplyId }};
    const pollUrl = @json(route('admin.support.messages', $ticket));
    const replyUrl = @json(route('admin.support.reply', $ticket));
    const adminName = @json(auth('admin')->user()->name);
    const chatMessages = document.getElementById('chat-messages');

    function appendMessage(msg) {
        if (document.querySelector('[data-message-id="' + msg.id + '"]')) {
            return;
        }
        const isAdmin = msg.user_type === 'admin';
        const label = isAdmin ? adminName : @json($displayName);
        const div = document.createElement('div');
        div.className = 'flex items-start space-x-3' + (isAdmin ? ' flex-row-reverse space-x-reverse' : '');
        div.dataset.messageId = msg.id;
        div.innerHTML = `
            <div class="w-10 h-10 rounded-full ${isAdmin ? 'bg-primary text-white' : 'bg-gray-300 text-gray-700'} flex items-center justify-center">
                ${label.charAt(0)}
            </div>
            <div class="flex-1 ${isAdmin ? 'text-right' : ''}">
                <div class="inline-block bg-gray-100 rounded-lg p-3 max-w-md">
                    <div class="flex items-center justify-between mb-1 ${isAdmin ? 'flex-row-reverse' : ''}">
                        <span class="text-xs font-medium text-gray-700">${label}</span>
                        <span class="text-xs text-gray-500">${msg.created_at_human || ''}</span>
                    </div>
                    <p class="text-sm text-gray-800 whitespace-pre-wrap"></p>
                </div>
            </div>`;
        div.querySelector('p').textContent = msg.message;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        lastId = Math.max(lastId, msg.id);
        if (!isAdmin && window.AdminSupportNotify && typeof window.AdminSupportNotify.play === 'function') {
            window.AdminSupportNotify.play();
        }
    }

    async function poll() {
        try {
            const res = await fetch(pollUrl + '?after_id=' + lastId, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.success && data.data && data.data.messages) {
                data.data.messages.forEach(function(msg) {
                    if (msg.id > 0) appendMessage(msg);
                });
            }
        } catch (e) {
            console.warn('poll failed', e);
        }
    }

    setInterval(poll, {{ (int) config('support.poll_interval_seconds', 4) * 1000 }});

    document.getElementById('reply-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = document.getElementById('reply-message').value.trim();
        const isInternal = document.getElementById('is-internal-note').checked;
        if (!message) return alert('Please enter a message');

        const formData = new FormData();
        formData.append('message', message);
        formData.append('is_internal_note', isInternal ? '1' : '0');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        try {
            const response = await fetch(replyUrl, { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                if (!isInternal) appendMessage(data.reply);
                document.getElementById('reply-message').value = '';
                document.getElementById('is-internal-note').checked = false;
            } else {
                alert(data.message || 'Error sending reply');
            }
        } catch (error) {
            console.error(error);
            alert('Error sending reply');
        }
    });
})();
</script>
@endsection
