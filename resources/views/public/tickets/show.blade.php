<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }} - Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #14b8a6;
            --primary-dark: #0d9488;
            --accent: #8b5cf6;
        }
        .bg-gradient-dark {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        .ticket-card {
            transition: all 0.3s ease;
        }
        @media (min-width: 768px) {
            .ticket-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            }
        }
        .ticket-card.popular {
            border: 2px solid var(--primary);
        }
        .hero-overlay {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.7) 0%, rgba(15, 23, 42, 0.95) 100%);
        }
        .quantity-btn {
            transition: all 0.2s ease;
            min-width: 44px;
            min-height: 44px;
        }
        @media (min-width: 768px) {
            .quantity-btn:hover {
                background-color: var(--primary-dark);
            }
        }
        .quantity-btn:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    @include('partials.nav')

    <!-- Hero Section -->
    <div class="relative min-h-[50vh] md:min-h-[70vh] flex items-center justify-center overflow-hidden">
        @if($event->cover_image)
            <img src="{{ asset('storage/' . $event->cover_image) }}" 
                 alt="{{ $event->title }}" 
                 class="absolute inset-0 w-full h-full object-cover">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-purple-900 via-blue-900 to-teal-900"></div>
        @endif
        <div class="hero-overlay absolute inset-0"></div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-20 text-center">
            @if($event->status === 'published')
                <span class="inline-block px-3 py-1.5 md:px-4 md:py-2 mb-3 md:mb-4 bg-teal-500 text-white text-xs md:text-sm font-semibold rounded-full">
                    LIVE EVENT
                </span>
            @endif
            
            <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-7xl font-bold mb-4 md:mb-6 text-white px-2">
                {{ $event->title }}
            </h1>
            
            <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-gray-200 mb-6 md:mb-8 max-w-3xl mx-auto px-4">
                {{ $event->description ?? 'Join us for an unforgettable experience' }}
            </p>
            
            <div class="flex flex-wrap justify-center gap-3 md:gap-6 text-sm md:text-base text-gray-200 px-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-calendar text-teal-400"></i>
                    <span>{{ $event->start_date->format('F d, Y') }}</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-clock text-teal-400"></i>
                    <span>{{ $event->start_date->format('h:i A') }}</span>
                </div>
                <div class="flex items-center space-x-2">
                    @if(($event->event_type ?? 'offline') === 'online')
                        <i class="fas fa-video text-teal-400"></i>
                        <span class="px-2 py-1 md:px-3 md:py-1 bg-blue-500/20 text-blue-300 rounded-full text-xs md:text-sm">Online Event</span>
                    @else
                        <i class="fas fa-map-marker-alt text-teal-400"></i>
                        <span class="break-words">{{ $event->venue }}</span>
                    @endif
                </div>
                @if($event->view_count > 0)
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-users text-teal-400"></i>
                        <span>{{ number_format($event->view_count) }}+ viewing</span>
                    </div>
                @endif
            </div>
            
            @if(($event->event_type ?? 'offline') === 'offline' && $event->address)
                <div class="mt-4 px-4">
                    <i class="fas fa-location-dot text-teal-400 mr-2"></i>
                    <span class="text-gray-300 text-sm md:text-base break-words">{{ $event->address }}</span>
                </div>
            @elseif(($event->event_type ?? 'offline') === 'online' && $event->address)
                <div class="mt-4 px-4">
                    <a href="{{ $event->address }}" target="_blank" class="text-teal-400 hover:text-teal-300 inline-flex items-center text-sm md:text-base">
                        <i class="fas fa-link mr-2"></i>
                        <span>Join Online Event</span>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
            <!-- Left Column: Ticket Selection -->
            <div class="lg:col-span-2 order-2 lg:order-1">
                <!-- Speakers/Artists Section -->
                @if($event->speakers->count() > 0)
                    <div class="bg-gray-800 rounded-xl md:rounded-2xl p-4 md:p-8 mb-6 md:mb-8 border border-gray-700">
                        <h2 class="text-2xl md:text-3xl font-bold mb-4 md:mb-6 text-white">
                            @if(($event->event_type ?? 'offline') === 'online')
                                Speakers
                            @else
                                Speakers & Artists
                            @endif
                        </h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
                            @foreach($event->speakers as $speaker)
                                <div class="text-center">
                                    @if($speaker->photo)
                                        <img src="{{ asset('storage/' . $speaker->photo) }}" 
                                             alt="{{ $speaker->name }}" 
                                             class="w-24 h-24 rounded-full object-cover mx-auto mb-3 border-2 border-teal-500">
                                    @else
                                        <div class="w-24 h-24 rounded-full bg-gradient-primary mx-auto mb-3 flex items-center justify-center">
                                            <i class="fas fa-user text-white text-3xl"></i>
                                        </div>
                                    @endif
                                    <h3 class="font-semibold text-white mb-1">{{ $speaker->name }}</h3>
                                    @if($speaker->topic)
                                        <p class="text-sm text-teal-400 mb-2">{{ $speaker->topic }}</p>
                                    @endif
                                    @if($speaker->bio)
                                        <p class="text-xs text-gray-400">{{ Str::limit($speaker->bio, 60) }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Ticket Selection Form -->
                <form action="{{ route('tickets.purchase', $event) }}" method="POST" id="ticket-form">
                    @csrf
                    
                    <div class="mb-6 md:mb-8">
                        <h2 class="text-2xl md:text-3xl font-bold mb-2 text-white">Select Your Tickets</h2>
                        <p class="text-sm md:text-base text-gray-400">Choose from our ticket options and get ready for an unforgettable experience.</p>
                    </div>

                    @if($event->ticketTypes->count() > 0)
                        <div class="space-y-4 md:space-y-6 mb-6 md:mb-8">
                            @foreach($event->ticketTypes as $ticketType)
                                @php
                                    $isPopular = false; // Can be determined by a field or logic
                                    $features = [];
                                    if ($ticketType->description) {
                                        // Extract features from description if they exist
                                        $lines = explode("\n", $ticketType->description);
                                        foreach ($lines as $line) {
                                            if (str_starts_with(trim($line), '•') || str_starts_with(trim($line), '-')) {
                                                $features[] = trim(str_replace(['•', '-'], '', $line));
                                            }
                                        }
                                    }
                                @endphp
                                <div class="ticket-card bg-gray-800 rounded-xl md:rounded-2xl p-4 md:p-6 border border-gray-700 {{ $isPopular ? 'popular' : '' }}">
                                    @if($isPopular)
                                        <div class="mb-3 md:mb-4">
                                            <span class="inline-block px-2 py-1 md:px-3 md:py-1 bg-gradient-primary text-white text-xs font-semibold rounded-full">
                                                MOST POPULAR
                                            </span>
                                        </div>
                                    @endif
                                    
                                    <div class="flex flex-col md:flex-row md:justify-between md:items-start mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl md:text-2xl font-bold text-white mb-2">{{ $ticketType->name }}</h3>
                                            @if($ticketType->description && empty($features))
                                                <p class="text-sm md:text-base text-gray-300 mb-3 md:mb-4">{{ $ticketType->description }}</p>
                                            @endif
                                            @if(!empty($features))
                                                <ul class="space-y-1.5 md:space-y-2 mb-3 md:mb-4">
                                                    @foreach($features as $feature)
                                                        <li class="flex items-start text-sm md:text-base text-gray-300">
                                                            <i class="fas fa-check-circle text-teal-400 mr-2 mt-0.5 md:mt-1 flex-shrink-0"></i>
                                                            <span>{{ $feature }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            <p class="text-xs md:text-sm text-gray-400">
                                                <i class="fas fa-ticket-alt mr-1"></i>
                                                {{ $ticketType->remaining_quantity }} available
                                            </p>
                                        </div>
                                        <div class="mt-3 md:mt-0 md:ml-6 text-left md:text-right">
                                            @if($ticketType->price == 0)
                                                <div class="text-3xl md:text-4xl font-bold text-green-400 mb-1 md:mb-2">FREE</div>
                                            @else
                                                <div class="text-3xl md:text-4xl font-bold text-teal-400 mb-1 md:mb-2">
                                                    ₦{{ number_format($ticketType->price, 2) }}
                                                </div>
                                            @endif
                                            <div class="text-xs md:text-sm text-gray-400">per ticket</div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 border-t border-gray-700 gap-3">
                                        <label class="text-sm font-medium text-gray-300">Quantity:</label>
                                        <div class="flex items-center justify-end sm:justify-start space-x-3">
                                            <button type="button" 
                                                    class="quantity-btn w-11 h-11 md:w-10 md:h-10 rounded-lg bg-gray-700 text-white hover:bg-teal-600 active:bg-teal-700 flex items-center justify-center"
                                                    onclick="decreaseQuantity({{ $loop->index }})">
                                                <i class="fas fa-minus text-sm"></i>
                                            </button>
                                            <input type="number" 
                                                   id="quantity-{{ $loop->index }}"
                                                   name="tickets[{{ $loop->index }}][quantity]" 
                                                   value="0" 
                                                   min="0" 
                                                   max="{{ min($ticketType->remaining_quantity, $event->max_tickets_per_customer ?? 100) }}"
                                                   class="w-16 md:w-16 px-2 md:px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-center text-sm md:text-base"
                                                   onchange="calculateTotal()"
                                                   readonly>
                                            <button type="button" 
                                                    class="quantity-btn w-11 h-11 md:w-10 md:h-10 rounded-lg bg-gray-700 text-white hover:bg-teal-600 active:bg-teal-700 flex items-center justify-center"
                                                    onclick="increaseQuantity({{ $loop->index }})">
                                                <i class="fas fa-plus text-sm"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="tickets[{{ $loop->index }}][ticket_type_id]" value="{{ $ticketType->id }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Customer Information -->
                        <div class="bg-gray-800 rounded-xl md:rounded-2xl p-4 md:p-8 mb-6 md:mb-8 border border-gray-700">
                            <h3 class="text-xl md:text-2xl font-bold text-white mb-4 md:mb-6">Your Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Full Name *</label>
                                    <input type="text" 
                                           name="customer_name" 
                                           required 
                                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Email *</label>
                                    <input type="email" 
                                           name="customer_email" 
                                           required 
                                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
                                    <input type="tel" 
                                           name="customer_phone" 
                                           class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500">
                                </div>
                            </div>
                        </div>

                        <!-- Coupon Code -->
                        @if($event->activeCoupons->count() > 0)
                        <div class="bg-gray-800 rounded-xl md:rounded-2xl p-4 md:p-8 mb-6 md:mb-8 border border-gray-700">
                            <h3 class="text-base md:text-lg font-semibold text-white mb-3 md:mb-4">Have a Coupon Code?</h3>
                            <div class="flex flex-col sm:flex-row gap-2 md:gap-3">
                                <input type="text" 
                                       id="coupon-code" 
                                       name="coupon_code" 
                                       placeholder="Enter coupon code"
                                       class="flex-1 px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 uppercase focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm md:text-base"
                                       onchange="applyCoupon()">
                                <button type="button" 
                                        onclick="applyCoupon()" 
                                        class="px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 active:bg-gray-500 transition min-h-[44px]">
                                    Apply
                                </button>
                            </div>
                            <div id="coupon-message" class="mt-3 text-sm hidden"></div>
                            <input type="hidden" id="applied-coupon-id" name="applied_coupon_id" value="">
                        </div>
                        @endif
                    @else
                        <div class="bg-gray-800 rounded-xl md:rounded-2xl p-8 md:p-12 text-center border border-gray-700">
                            <i class="fas fa-ticket-alt text-4xl md:text-6xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400 text-lg md:text-xl">No tickets available for this event</p>
                        </div>
                    @endif
                </form>
            </div>

            <!-- Right Column: Order Summary -->
            <div class="lg:col-span-1 order-1 lg:order-2">
                <div class="bg-gray-800 rounded-xl md:rounded-2xl p-4 md:p-6 border border-gray-700 lg:sticky lg:top-24">
                    <div class="flex items-center mb-4 md:mb-6">
                        <h3 class="text-lg md:text-xl font-bold text-white">Order Summary</h3>
                    </div>
                    
                    <div id="order-items" class="space-y-2 md:space-y-3 mb-4 md:mb-6 min-h-[80px] md:min-h-[100px]">
                        <p class="text-gray-400 text-xs md:text-sm text-center py-6 md:py-8">Select tickets to see order summary</p>
                    </div>
                    
                    <div class="border-t border-gray-700 pt-3 md:pt-4 space-y-2 md:space-y-3">
                        <div id="subtotal-section" class="flex justify-between items-center text-sm md:text-base text-gray-300 hidden">
                            <span>Subtotal:</span>
                            <span id="subtotal-amount">₦0.00</span>
                        </div>
                        <div id="discount-section" class="flex justify-between items-center text-sm md:text-base text-green-400 hidden">
                            <span>Discount:</span>
                            <span id="discount-amount">-₦0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-xl md:text-2xl font-bold border-t border-gray-700 pt-3 md:pt-4">
                            <span class="text-white">Total:</span>
                            <span id="total-amount" class="text-teal-400">₦0.00</span>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            form="ticket-form"
                            id="submit-btn" 
                            class="w-full mt-4 md:mt-6 bg-gradient-primary text-white py-3 md:py-4 rounded-lg font-semibold hover:opacity-90 active:opacity-80 transition text-base md:text-lg min-h-[44px] md:min-h-[52px]"
                            onclick="return validateForm()">
                        <span id="submit-text">Get Tickets →</span>
                    </button>
                    
                    <p class="text-xs text-gray-500 text-center mt-3 md:mt-4">
                        <i class="fas fa-lock mr-1"></i>
                        Secure checkout powered by our payment system
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ticketTypes = @json($ticketTypesData);
        const coupons = @json($couponsData);
        let appliedCoupon = null;
        
        function formatCurrency(amount) {
            return '₦' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        function increaseQuantity(index) {
            const input = document.getElementById(`quantity-${index}`);
            const max = parseInt(input.getAttribute('max'));
            const current = parseInt(input.value) || 0;
            if (current < max) {
                input.value = current + 1;
                input.dispatchEvent(new Event('change'));
            }
        }
        
        function decreaseQuantity(index) {
            const input = document.getElementById(`quantity-${index}`);
            const current = parseInt(input.value) || 0;
            if (current > 0) {
                input.value = current - 1;
                input.dispatchEvent(new Event('change'));
            }
        }
        
        function calculateTotal() {
            let subtotal = 0;
            const orderItems = document.getElementById('order-items');
            orderItems.innerHTML = '';
            
            document.querySelectorAll('input[name^="tickets"][name$="[quantity]"]').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                if (quantity > 0) {
                    const ticketContainer = input.closest('.ticket-card');
                    if (ticketContainer) {
                        const ticketTypeIdInput = ticketContainer.querySelector('input[type="hidden"][name*="[ticket_type_id]"]');
                        if (ticketTypeIdInput) {
                            const ticketTypeId = parseInt(ticketTypeIdInput.value);
                            const ticketType = ticketTypes.find(t => t.id == ticketTypeId);
                            if (ticketType) {
                                const itemTotal = quantity * ticketType.price;
                                subtotal += itemTotal;
                                
                                // Add to order summary
                                const itemDiv = document.createElement('div');
                                itemDiv.className = 'flex justify-between items-center text-sm';
                                itemDiv.innerHTML = `
                                    <span class="text-gray-300">${ticketType.name || 'Ticket'} x ${quantity}</span>
                                    <span class="text-white font-semibold">${formatCurrency(itemTotal)}</span>
                                `;
                                orderItems.appendChild(itemDiv);
                            }
                        }
                    }
                }
            });
            
            if (orderItems.children.length === 0) {
                orderItems.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">Select tickets to see order summary</p>';
            }
            
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
            
            // Update button text
            const submitText = document.getElementById('submit-text');
            const totalTickets = Array.from(document.querySelectorAll('input[name^="tickets"][name$="[quantity]"]'))
                .reduce((sum, input) => sum + (parseInt(input.value) || 0), 0);
            
            if (total === 0 && totalTickets > 0) {
                submitText.textContent = 'Confirm Free Tickets →';
            } else if (totalTickets > 0) {
                submitText.textContent = `Get ${totalTickets} Ticket${totalTickets > 1 ? 's' : ''} →`;
            } else {
                submitText.textContent = 'Get Tickets →';
            }
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
                messageEl.classList.remove('text-red-400');
                messageEl.classList.add('text-green-400');
                messageEl.textContent = '✓ Coupon applied successfully!';
                calculateTotal();
            } else {
                appliedCoupon = null;
                couponIdInput.value = '';
                messageEl.classList.remove('hidden');
                messageEl.classList.remove('text-green-400');
                messageEl.classList.add('text-red-400');
                messageEl.textContent = '✗ Invalid coupon code';
                calculateTotal();
            }
        }
        
        function validateForm() {
            let hasTickets = false;
            let totalAmount = 0;
            
            document.querySelectorAll('input[name^="tickets"][name$="[quantity]"]').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                if (quantity > 0) {
                    hasTickets = true;
                    const ticketContainer = input.closest('.ticket-card');
                    if (ticketContainer) {
                        const ticketTypeIdInput = ticketContainer.querySelector('input[type="hidden"][name*="[ticket_type_id]"]');
                        if (ticketTypeIdInput) {
                            const ticketTypeId = parseInt(ticketTypeIdInput.value);
                            const ticketType = ticketTypes.find(t => t.id == ticketTypeId);
                            if (ticketType) {
                                totalAmount += quantity * ticketType.price;
                            }
                        }
                    }
                }
            });
            
            if (!hasTickets) {
                alert('Please select at least one ticket');
                return false;
            }
            
            if (appliedCoupon && totalAmount > 0) {
                let discount = 0;
                if (appliedCoupon.discount_type === 'percentage') {
                    discount = (totalAmount * appliedCoupon.discount_value) / 100;
                } else {
                    discount = Math.min(appliedCoupon.discount_value, totalAmount);
                }
                totalAmount = Math.max(0, totalAmount - discount);
            }
            
            return true;
        }
        
        // Calculate total on page load
        calculateTotal();
    </script>
    
    @include('partials.footer')
</body>
</html>
