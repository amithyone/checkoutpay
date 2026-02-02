@extends('layouts.business')

@section('title', $event->title)
@section('page-title', $event->title)

@section('content')
<div class="space-y-4 lg:space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('business.tickets.events.index') }}" class="text-primary hover:text-primary/80">
            <i class="fas fa-arrow-left mr-2"></i> Back to Events
        </a>
        <div class="flex gap-2">
            @if($event->status === 'draft')
                <form action="{{ route('business.tickets.events.publish', $event) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-check mr-2"></i> Publish
                    </button>
                </form>
            @endif
            @if($event->status === 'published')
                <form action="{{ route('business.tickets.events.cancel', $event) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i> Cancel Event
                    </button>
                </form>
            @endif
            <a href="{{ route('business.tickets.events.edit', $event) }}" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Event Details -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <h1 class="text-3xl font-bold mb-4">{{ $event->title }}</h1>
                @if($event->cover_image)
                    <img src="{{ asset('storage/' . $event->cover_image) }}" alt="{{ $event->title }}" class="w-full h-64 object-cover rounded-lg mb-4">
                @endif
                @if($event->description)
                    <p class="text-gray-700 mb-4">{{ $event->description }}</p>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-500">Event Type</label>
                        <p class="font-semibold">
                            @if(($event->event_type ?? 'offline') === 'online')
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Online Event</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Offline Event</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Date & Time</label>
                        <p class="font-semibold">{{ $event->start_date->format('F d, Y h:i A') }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Venue/Location</label>
                        <p class="font-semibold">{{ $event->venue }}</p>
                    </div>
                    @if(($event->event_type ?? 'offline') === 'offline' && $event->address)
                        <div>
                            <label class="text-sm text-gray-500">Address</label>
                            <p class="font-semibold">{{ $event->address }}</p>
                        </div>
                    @elseif(($event->event_type ?? 'offline') === 'online' && $event->address)
                        <div>
                            <label class="text-sm text-gray-500">Platform Link</label>
                            <p class="font-semibold">
                                <a href="{{ $event->address }}" target="_blank" class="text-primary hover:underline">{{ $event->address }}</a>
                            </p>
                        </div>
                    @endif
                </div>
            </div>
            <div>
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <h3 class="font-semibold mb-4">Event Link</h3>
                    @if($event->status === 'published')
                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                            <div class="flex items-center gap-2 mb-2">
                                <input type="text" id="event-url" value="{{ $event->public_url }}" readonly class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50">
                                <button onclick="copyEventUrl()" class="px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <a href="{{ $event->public_url }}" target="_blank" class="text-sm text-primary hover:underline">
                                <i class="fas fa-external-link-alt mr-1"></i> View Public Page
                            </a>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Publish event to get public link</p>
                    @endif
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold mb-4">Statistics</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm text-gray-500">Status</label>
                            <p>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    @if($event->status === 'published') bg-green-100 text-green-800
                                    @elseif($event->status === 'draft') bg-gray-100 text-gray-800
                                    @elseif($event->status === 'cancelled') bg-red-100 text-red-800
                                    @else bg-blue-100 text-blue-800
                                    @endif">
                                    {{ ucfirst($event->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Page Views</label>
                            <p class="font-semibold text-lg">{{ number_format($stats['view_count']) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Unique Buyers</label>
                            <p class="font-semibold text-lg">{{ number_format($stats['unique_buyers']) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Tickets Sold</label>
                            <p class="font-semibold text-lg">{{ number_format($stats['total_tickets_sold']) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Total Orders</label>
                            <p class="font-semibold">{{ number_format($stats['total_orders']) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Total Revenue</label>
                            <p class="font-semibold text-lg">₦{{ number_format($stats['total_revenue'], 2) }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Commission</label>
                            <p class="font-semibold">₦{{ number_format($stats['total_commission'], 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket Types -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-bold mb-4">Ticket Types</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sold</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($event->ticketTypes as $ticketType)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $ticketType->name }}</div>
                                @if($ticketType->description)
                                    <div class="text-sm text-gray-500">{{ $ticketType->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold">
                                @if($ticketType->price == 0)
                                    <span class="text-green-600">FREE</span>
                                @else
                                    ₦{{ number_format($ticketType->price, 2) }}
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $ticketType->quantity_available }}</td>
                            <td class="px-4 py-3">{{ $ticketType->quantity_sold }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $ticketType->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $ticketType->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Recent Orders</h2>
            <a href="{{ route('business.tickets.orders.index', ['event_id' => $event->id]) }}" class="text-primary hover:text-primary/80">
                View All Orders
            </a>
        </div>
        @if($event->ticketOrders->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($event->ticketOrders as $order)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $order->order_number }}</td>
                                <td class="px-4 py-3">{{ $order->customer_name }}</td>
                                <td class="px-4 py-3">
                                    @if($order->discount_amount > 0)
                                        <div class="text-sm text-gray-500 line-through">₦{{ number_format($order->total_amount + $order->discount_amount, 2) }}</div>
                                        <div class="font-semibold text-green-600">₦{{ number_format($order->total_amount, 2) }}</div>
                                        <div class="text-xs text-gray-500">Discount: ₦{{ number_format($order->discount_amount, 2) }}</div>
                                    @else
                                        <div class="font-semibold">₦{{ number_format($order->total_amount, 2) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        @if($order->payment_status === 'paid') bg-green-100 text-green-800
                                        @elseif($order->payment_status === 'pending') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ ucfirst($order->payment_status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $order->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No orders yet</p>
        @endif
    </div>

    <!-- Coupon Codes -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Coupon Codes</h2>
            <button onclick="showCreateCouponModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                <i class="fas fa-plus mr-2"></i> Add Coupon
            </button>
        </div>
        @if($event->coupons->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Used</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valid Until</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($event->coupons as $coupon)
                            <tr>
                                <td class="px-4 py-3 font-mono font-semibold">{{ $coupon->code }}</td>
                                <td class="px-4 py-3">
                                    @if($coupon->discount_type === 'percentage')
                                        {{ $coupon->discount_value }}%
                                    @else
                                        ₦{{ number_format($coupon->discount_value, 2) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $coupon->used_count }}
                                    @if($coupon->usage_limit)
                                        / {{ $coupon->usage_limit }}
                                    @else
                                        / ∞
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($coupon->valid_until)
                                        {{ $coupon->valid_until->format('M d, Y') }}
                                    @else
                                        No expiry
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $coupon->isValid() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $coupon->isValid() ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <form action="{{ route('business.tickets.events.coupons.destroy', [$event, $coupon]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this coupon?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No coupon codes yet</p>
        @endif
    </div>
</div>

<!-- Create Coupon Modal -->
<div id="couponModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Create Coupon Code</h3>
        <form action="{{ route('business.tickets.events.coupons.store', $event) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Coupon Code *</label>
                    <input type="text" name="code" required class="w-full px-4 py-2 border border-gray-300 rounded-lg uppercase" placeholder="SAVE20" maxlength="20">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type *</label>
                    <select name="discount_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₦)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value *</label>
                    <input type="number" name="discount_value" required min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="20">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Usage Limit</label>
                    <input type="number" name="usage_limit" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Leave empty for unlimited">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                        <input type="datetime-local" name="valid_from" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                        <input type="datetime-local" name="valid_until" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="hideCreateCouponModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Create</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyEventUrl() {
    const urlInput = document.getElementById('event-url');
    urlInput.select();
    document.execCommand('copy');
    alert('Event link copied to clipboard!');
}

function showCreateCouponModal() {
    document.getElementById('couponModal').classList.remove('hidden');
    document.getElementById('couponModal').classList.add('flex');
}

function hideCreateCouponModal() {
    document.getElementById('couponModal').classList.add('hidden');
    document.getElementById('couponModal').classList.remove('flex');
}

// Close modal on outside click
document.getElementById('couponModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideCreateCouponModal();
    }
});
</script>
@endsection
