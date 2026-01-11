@extends('layouts.business')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Notifications</h2>
            <p class="text-gray-600 mt-1">Stay updated with your account activity</p>
        </div>
        <form action="{{ route('business.notifications.read-all') }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                Mark All as Read
            </button>
        </form>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="divide-y divide-gray-200">
            @forelse($notifications as $notification)
            <div class="p-6 hover:bg-gray-50 {{ !$notification->is_read ? 'bg-blue-50/50' : '' }}">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        @if($notification->type === 'payment_received')
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-money-bill-wave text-green-600"></i>
                            </div>
                        @elseif($notification->type === 'withdrawal_approved')
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-blue-600"></i>
                            </div>
                        @elseif($notification->type === 'verification_approved')
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-id-card text-purple-600"></i>
                            </div>
                        @else
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-bell text-gray-600"></i>
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $notification->title }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ $notification->message }}</p>
                                <p class="text-xs text-gray-500 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>
                            @if(!$notification->is_read)
                                <form action="{{ route('business.notifications.read', $notification) }}" method="POST" class="ml-4">
                                    @csrf
                                    <button type="submit" class="text-xs text-primary hover:underline">Mark as read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-12 text-center">
                <i class="fas fa-bell-slash text-4xl mb-4 text-gray-300"></i>
                <p class="text-gray-500">No notifications</p>
            </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
