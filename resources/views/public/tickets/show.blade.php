<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }} - Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Event Header -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                @if($event->cover_image)
                    <img src="{{ asset('storage/' . $event->cover_image) }}" alt="{{ $event->title }}" class="w-full h-64 object-cover">
                @endif
                <div class="p-6">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $event->title }}</h1>
                    <div class="flex flex-wrap gap-4 text-gray-600 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-calendar mr-2"></i>
                            {{ $event->start_date->format('F d, Y') }}
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            {{ $event->start_date->format('h:i A') }}
                        </div>
                        <div class="flex items-center">
                            @if(($event->event_type ?? 'offline') === 'online')
                                <i class="fas fa-video mr-2"></i>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs mr-2">Online Event</span>
                            @else
                                <i class="fas fa-map-marker-alt mr-2"></i>
                            @endif
                            {{ $event->venue }}
                        </div>
                        @if(($event->event_type ?? 'offline') === 'offline' && $event->address)
                            <div class="flex items-center">
                                <i class="fas fa-location-dot mr-2"></i>
                                {{ $event->address }}
                            </div>
                        @elseif(($event->event_type ?? 'offline') === 'online' && $event->address)
                            <div class="flex items-center">
                                <i class="fas fa-link mr-2"></i>
                                <a href="{{ $event->address }}" target="_blank" class="text-blue-600 hover:underline">{{ $event->address }}</a>
                            </div>
                        @endif
                    </div>
                    @if($event->description)
                        <p class="text-gray-700 mb-4">{{ $event->description }}</p>
                    @endif
                </div>
            </div>

            <!-- Speakers/Artists Section -->
            @if($event->speakers->count() > 0)
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        @if(($event->event_type ?? 'offline') === 'online')
                            Speakers
                        @else
                            Speakers/Artists
                        @endif
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                        @foreach($event->speakers as $speaker)
                            <div class="text-center">
                                @if($speaker->photo)
                                    <img src="{{ asset('storage/' . $speaker->photo) }}" alt="{{ $speaker->name }}" class="w-24 h-24 rounded-full object-cover mx-auto mb-3 border-2 border-gray-200">
                                @else
                                    <div class="w-24 h-24 rounded-full bg-gray-200 mx-auto mb-3 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-400 text-3xl"></i>
                                    </div>
                                @endif
                                <h3 class="font-semibold text-gray-900 mb-1">{{ $speaker->name }}</h3>
                                @if($speaker->topic)
                                    <p class="text-sm text-gray-600 mb-2">{{ $speaker->topic }}</p>
                                @endif
                                @if($speaker->bio)
                                    <p class="text-xs text-gray-500">{{ Str::limit($speaker->bio, 60) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Ticket Selection Form -->
            <form action="{{ route('tickets.purchase', $event) }}" method="POST" class="bg-white rounded-xl shadow-lg p-6">
                @csrf
                
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Select Tickets</h2>

                @if($event->ticketTypes->count() > 0)
                    <div class="space-y-4 mb-6">
                        @foreach($event->ticketTypes as $ticketType)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-lg text-gray-900">{{ $ticketType->name }}</h3>
                                        @if($ticketType->description)
                                            <p class="text-sm text-gray-600 mt-1">{{ $ticketType->description }}</p>
                                        @endif
                                        <p class="text-sm text-gray-500 mt-2">
                                            {{ $ticketType->remaining_quantity }} available
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        @if($ticketType->price == 0)
                                            <div class="text-2xl font-bold text-green-600">FREE</div>
                                        @else
                                            <div class="text-2xl font-bold text-primary">₦{{ number_format($ticketType->price, 2) }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <label class="text-sm text-gray-700 mr-3">Quantity:</label>
                                    <input type="number" 
                                           name="tickets[{{ $loop->index }}][quantity]" 
                                           value="0" 
                                           min="0" 
                                           max="{{ min($ticketType->remaining_quantity, $event->max_tickets_per_customer ?? 100) }}"
                                           class="w-20 px-3 py-2 border border-gray-300 rounded-lg"
                                           onchange="calculateTotal()">
                                    <input type="hidden" name="tickets[{{ $loop->index }}][ticket_type_id]" value="{{ $ticketType->id }}">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Customer Information -->
                    <div class="border-t border-gray-200 pt-6 mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Your Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                <input type="text" name="customer_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input type="email" name="customer_email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" name="customer_phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- Coupon Code -->
                    @if($event->activeCoupons->count() > 0)
                    <div class="border-t border-gray-200 pt-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Have a Coupon Code?</h3>
                        <div class="flex gap-2">
                            <input type="text" 
                                   id="coupon-code" 
                                   name="coupon_code" 
                                   placeholder="Enter coupon code"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg uppercase"
                                   onchange="applyCoupon()">
                            <button type="button" 
                                    onclick="applyCoupon()" 
                                    class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                Apply
                            </button>
                        </div>
                        <div id="coupon-message" class="mt-2 text-sm hidden"></div>
                        <input type="hidden" id="applied-coupon-id" name="applied_coupon_id" value="">
                    </div>
                    @endif

                    <!-- Total -->
                    <div class="border-t border-gray-200 pt-4 mb-6">
                        <div id="subtotal-section" class="flex justify-between items-center text-gray-600 mb-2 hidden">
                            <span>Subtotal:</span>
                            <span id="subtotal-amount">₦0.00</span>
                        </div>
                        <div id="discount-section" class="flex justify-between items-center text-green-600 mb-2 hidden">
                            <span>Discount:</span>
                            <span id="discount-amount">-₦0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-xl font-bold border-t border-gray-200 pt-2">
                            <span>Total:</span>
                            <span id="total-amount" class="text-primary">₦0.00</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-primary/90">
                        Proceed to Payment
                    </button>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-ticket-alt text-4xl mb-4"></i>
                        <p>No tickets available for this event</p>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <script>
        const ticketTypes = @json($event->ticketTypes->map(fn($t) => ['id' => $t->id, 'price' => $t->price]));
        const coupons = @json($event->activeCoupons->map(fn($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'discount_type' => $c->discount_type,
            'discount_value' => $c->discount_value
        ]));
        let appliedCoupon = null;
        
        function formatCurrency(amount) {
            return '₦' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        function calculateTotal() {
            let subtotal = 0;
            document.querySelectorAll('input[type="number"]').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const ticketTypeId = input.closest('.border').querySelector('input[type="hidden"]').value;
                const ticketType = ticketTypes.find(t => t.id == ticketTypeId);
                if (ticketType) {
                    subtotal += quantity * ticketType.price;
                }
            });
            
            const subtotalEl = document.getElementById('subtotal-amount');
            const discountEl = document.getElementById('discount-amount');
            const totalEl = document.getElementById('total-amount');
            const subtotalSection = document.getElementById('subtotal-section');
            const discountSection = document.getElementById('discount-section');
            
            if (subtotal > 0) {
                subtotalSection.classList.remove('hidden');
                subtotalEl.textContent = formatCurrency(subtotal);
            } else {
                subtotalSection.classList.add('hidden');
            }
            
            let discount = 0;
            if (appliedCoupon && subtotal > 0) {
                if (appliedCoupon.discount_type === 'percentage') {
                    discount = (subtotal * appliedCoupon.discount_value) / 100;
                } else {
                    discount = Math.min(appliedCoupon.discount_value, subtotal);
                }
                
                if (discount > 0) {
                    discountSection.classList.remove('hidden');
                    discountEl.textContent = '-' + formatCurrency(discount);
                } else {
                    discountSection.classList.add('hidden');
                }
            } else {
                discountSection.classList.add('hidden');
            }
            
            const total = Math.max(0, subtotal - discount);
            totalEl.textContent = formatCurrency(total);
        }
        
        function applyCoupon() {
            const codeInput = document.getElementById('coupon-code');
            const code = codeInput.value.trim().toUpperCase();
            const messageEl = document.getElementById('coupon-message');
            const couponIdInput = document.getElementById('applied-coupon-id');
            
            if (!code) {
                appliedCoupon = null;
                couponIdInput.value = '';
                messageEl.classList.add('hidden');
                calculateTotal();
                return;
            }
            
            const coupon = coupons.find(c => c.code === code);
            if (coupon) {
                appliedCoupon = coupon;
                couponIdInput.value = coupon.id;
                messageEl.classList.remove('hidden');
                messageEl.classList.remove('text-red-600');
                messageEl.classList.add('text-green-600');
                messageEl.textContent = 'Coupon applied successfully!';
                calculateTotal();
            } else {
                appliedCoupon = null;
                couponIdInput.value = '';
                messageEl.classList.remove('hidden');
                messageEl.classList.remove('text-green-600');
                messageEl.classList.add('text-red-600');
                messageEl.textContent = 'Invalid coupon code';
                calculateTotal();
            }
        }
        
        // Calculate total on page load
        calculateTotal();
    </script>
</body>
</html>
