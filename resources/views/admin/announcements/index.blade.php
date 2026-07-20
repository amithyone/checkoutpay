@extends('layouts.admin')

@section('title', 'Announcements')
@section('page-title', 'Announcements / Mailer')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Email + app push mailer</h3>
            <p class="text-sm text-gray-600 mt-1">Message wallet, rental, and business accounts. Never sends WhatsApp.</p>
        </div>
        <a href="{{ route('admin.announcements.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm inline-flex items-center border border-transparent">
            <i class="fas fa-paper-plane mr-2"></i> Compose
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['wallet' => 'Wallet / CheckoutNow', 'rentals' => 'Rentals users', 'business' => 'Businesses'] as $key => $label)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-sm font-medium text-gray-900">{{ $label }}</div>
                <div class="text-xs text-gray-500 mt-2">Emails reachable: {{ number_format($reach[$key]['emails'] ?? 0) }}</div>
                <div class="text-xs text-gray-500">App pushes reachable: {{ number_format($reach[$key]['pushes'] ?? 0) }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Audiences</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channels</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Results</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $item->title }}</div>
                                <div class="text-xs text-gray-500">{{ $item->created_at?->format('Y-m-d H:i') }} · {{ $item->admin?->name ?? 'Admin' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ implode(', ', $item->audienceList()) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                @if($item->channel_email) Email @endif
                                @if($item->channel_email && $item->channel_push)·@endif
                                @if($item->channel_push) Push @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full
                                    @if($item->status === 'sent') bg-green-100 text-green-800
                                    @elseif($item->status === 'failed') bg-red-100 text-red-800
                                    @elseif($item->status === 'sending') bg-amber-100 text-amber-800
                                    @else bg-gray-100 text-gray-800 @endif">{{ $item->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                ✉ {{ $item->emails_sent }}/fail {{ $item->emails_failed }}
                                · 📱 {{ $item->pushes_sent }}/fail {{ $item->pushes_failed }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.announcements.show', $item) }}" class="text-sm text-primary hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No announcements yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="px-4 py-3 border-t">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
