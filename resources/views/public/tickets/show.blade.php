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
        .floating-cart {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .floating-cart[style*="display: none"] {
            opacity: 0;
            pointer-events: none;
            transform: translateY(20px);
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .cart-summary {
            position: absolute;
            bottom: 70px;
            right: 0;
            width: 320px;
            max-width: calc(100vw - 40px);
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: none;
        }
        .cart-summary.show {
            display: block;
        }
        @media (max-width: 768px) {
            .floating-cart {
                bottom: 15px;
                right: 15px;
            }
            .cart-summary {
                width: calc(100vw - 30px);
                right: -15px;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    @include('partials.nav')

    <!-- Hero Section - Compact on Mobile -->
    <div class="relative min-h-[35vh] md:min-h-[60vh] flex items-center justify-center overflow-hidden">
        @if($event->cover_image)
            <img src="{{ asset('storage/' . $event->cover_image) }}" 
                 alt="{{ $event->title }}" 
                 class="absolute inset-0 w-full h-full object-cover">
        @elseif($event->background_color)
            <div class="absolute inset-0" style="background: {{ $event->background_color }};"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-purple-900 via-blue-900 to-teal-900"></div>
        @endif
        <div class="hero-overlay absolute inset-0"></div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-16 text-center">
            @if($event->status === 'published')
                <span class="inline-block px-2 py-1 md:px-4 md:py-2 mb-2 md:mb-4 bg-teal-500 text-white text-xs font-semibold rounded-full">
                    LIVE EVENT
                </span>
            @endif
            
            <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-6xl font-bold mb-2 md:mb-4 text-white px-2 leading-tight">
                {{ $event->title }}
            </h1>
            
            <p class="text-sm sm:text-base md:text-lg lg:text-xl text-gray-200 mb-3 md:mb-6 max-w-3xl mx-auto px-4 line-clamp-2 md:line-clamp-none">
                {{ $event->description ?? 'Join us for an unforgettable experience' }}
            </p>
            
            <div class="flex flex-wrap justify-center gap-2 md:gap-4 text-xs md:text-sm text-gray-200 px-2">
                <div class="flex items-center space-x-1">
                    <i class="fas fa-calendar text-teal-400 text-xs"></i>
                    <span>{{ $event->start_date->format('M d, Y') }}</span>
                </div>
                <div class="flex items-center space-x-1">
                    <i class="fas fa-clock text-teal-400 text-xs"></i>
                    <span>{{ $event->start_date->format('h:i A') }}</span>
                </div>
                <div class="flex items-center space-x-1">
                    @if(($event->event_type ?? 'offline') === 'online')
                        <i class="fas fa-video text-teal-400 text-xs"></i>
                        <span class="px-1.5 py-0.5 md:px-3 md:py-1 bg-blue-500/20 text-blue-300 rounded-full text-xs">Online</span>
                    @else
                        <i class="fas fa-map-marker-alt text-teal-400 text-xs"></i>
                        <span class="break-words text-xs">{{ Str::limit($event->venue, 20) }}</span>
                    @endif
                </div>
            </div>
            
            @if(($event->event_type ?? 'offline') === 'offline' && $event->address)
                <div class="mt-2 md:mt-4 px-4">
                    <i class="fas fa-location-dot text-teal-400 mr-1 text-xs"></i>
                    <span class="text-gray-300 text-xs md:text-sm break-words">{{ Str::limit($event->address, 40) }}</span>
                </div>
            @elseif(($event->event_type ?? 'offline') === 'online' && $event->address)
                <div class="mt-2 md:mt-4 px-4">
                    <a href="{{ $event->address }}" target="_blank" class="text-teal-400 hover:text-teal-300 inline-flex items-center text-xs md:text-sm">
                        <i class="fas fa-link mr-1"></i>
                        <span>Join Online</span>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
            <!-- Left Column: Ticket Selection -->
            <div class="lg:col-span-2 order-2">
                <!-- Ticket Selection Form -->
                <form action="{{ route('tickets.purchase', $event) }}" method="POST" id="ticket-form">
                    @csrf
                    
                    <!-- Ticket Selection Dropdown -->
                    <div class="bg-gray-800 rounded-lg md:rounded-xl border border-gray-700 mb-4 md:mb-6">
                        <button type="button" 
                                onclick="toggleTickets()" 
                                class="w-full flex items-center justify-between p-4 md:p-6 text-left focus:outline-none">
                            <div>
                                <h2 class="text-lg md:text-xl font-bold text-white">Select Your Tickets</h2>
                                <p class="text-xs md:text-sm text-gray-400 mt-1 hidden md:block">Choose from our ticket options</p>
                            </div>
                            <i id="tickets-icon" class="fas fa-chevron-down text-teal-400 transition-transform duration-200"></i>
                        </button>
                        <div id="tickets-content" class="hidden px-4 md:px-6 pb-4 md:pb-6">
                    @if($event->ticketTypes->count() > 0)
                        <div class="space-y-3 md:space-y-4 mb-4 md:mb-6">
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
                                <div class="ticket-card bg-gray-800 rounded-lg md:rounded-xl p-3 md:p-5 border border-gray-700 {{ $isPopular ? 'popular' : '' }}">
                                    @if($isPopular)
                                        <div class="mb-2 md:mb-3">
                                            <span class="inline-block px-2 py-0.5 md:px-3 md:py-1 bg-gradient-primary text-white text-xs font-semibold rounded-full">
                                                MOST POPULAR
                                            </span>
                                        </div>
                                    @endif
                                    
                                    <div class="flex flex-col md:flex-row md:justify-between md:items-start mb-3 md:mb-4">
                                        <div class="flex-1">
                                            <div class="flex items-start justify-between mb-2">
                                                <h3 class="text-lg md:text-xl font-bold text-white">{{ $ticketType->name }}</h3>
                                                <div class="md:hidden text-right ml-2">
                                                    @if($ticketType->price == 0)
                                                        <div class="text-xl font-bold text-green-400">FREE</div>
                                                    @else
                                                        <div class="text-xl font-bold text-teal-400">
                                                            ₦{{ number_format($ticketType->price, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($ticketType->description && empty($features))
                                                <p class="text-xs md:text-sm text-gray-300 mb-2 md:mb-3 line-clamp-2">{{ $ticketType->description }}</p>
                                            @endif
                                            @if(!empty($features))
                                                <ul class="space-y-1 md:space-y-1.5 mb-2 md:mb-3 hidden md:block">
                                                    @foreach(array_slice($features, 0, 3) as $feature)
                                                        <li class="flex items-start text-xs md:text-sm text-gray-300">
                                                            <i class="fas fa-check-circle text-teal-400 mr-1.5 mt-0.5 flex-shrink-0 text-xs"></i>
                                                            <span>{{ $feature }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            <p class="text-xs text-gray-400">
                                                <i class="fas fa-ticket-alt mr-1"></i>
                                                {{ $ticketType->remaining_quantity }} available
                                            </p>
                                        </div>
                                        <div class="hidden md:block md:ml-6 text-right">
                                            @if($ticketType->price == 0)
                                                <div class="text-2xl md:text-3xl font-bold text-green-400 mb-1">FREE</div>
                                            @else
                                                <div class="text-2xl md:text-3xl font-bold text-teal-400 mb-1">
                                                    ₦{{ number_format($ticketType->price, 2) }}
                                                </div>
                                            @endif
                                            <div class="text-xs md:text-sm text-gray-400">per ticket</div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between pt-3 border-t border-gray-700">
                                        <label class="text-xs md:text-sm font-medium text-gray-300">Quantity:</label>
                                        <div class="flex items-center space-x-2 md:space-x-3">
                                            <button type="button" 
                                                    class="quantity-btn w-10 h-10 md:w-10 md:h-10 rounded-lg bg-gray-700 text-white hover:bg-teal-600 active:bg-teal-700 flex items-center justify-center"
                                                    onclick="decreaseQuantity({{ $loop->index }})">
                                                <i class="fas fa-minus text-xs"></i>
                                            </button>
                                            <input type="number" 
                                                   id="quantity-{{ $loop->index }}"
                                                   name="tickets[{{ $loop->index }}][quantity]" 
                                                   value="0" 
                                                   min="0" 
                                                   max="{{ min($ticketType->remaining_quantity, $event->max_tickets_per_customer ?? 100) }}"
                                                   class="w-14 md:w-16 px-2 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-center text-sm"
                                                   onchange="calculateTotal()"
                                                   readonly>
                                            <button type="button" 
                                                    class="quantity-btn w-10 h-10 md:w-10 md:h-10 rounded-lg bg-gray-700 text-white hover:bg-teal-600 active:bg-teal-700 flex items-center justify-center"
                                                    onclick="increaseQuantity({{ $loop->index }})">
                                                <i class="fas fa-plus text-xs"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="tickets[{{ $loop->index }}][ticket_type_id]" value="{{ $ticketType->id }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Customer Information -->
                        <div class="bg-gray-800 rounded-lg md:rounded-xl p-4 md:p-6 mb-4 md:mb-6 border border-gray-700">
                            <h3 class="text-lg md:text-xl font-bold text-white mb-3 md:mb-4">Your Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
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
                        <div class="bg-gray-800 rounded-lg md:rounded-xl p-3 md:p-6 mb-4 md:mb-6 border border-gray-700">
                            <h3 class="text-sm md:text-base font-semibold text-white mb-2 md:mb-3">Have a Coupon Code?</h3>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" 
                                       id="coupon-code" 
                                       name="coupon_code" 
                                       placeholder="Enter coupon code"
                                       class="flex-1 px-3 py-2 md:px-4 md:py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 uppercase focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm"
                                       onchange="applyCoupon()">
                                <button type="button" 
                                        onclick="applyCoupon()" 
                                        class="px-4 py-2 md:px-6 md:py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 active:bg-gray-500 transition min-h-[44px] text-sm">
                                    Apply
                                </button>
                            </div>
                            <div id="coupon-message" class="mt-2 text-xs md:text-sm hidden"></div>
                            <input type="hidden" id="applied-coupon-id" name="applied_coupon_id" value="">
                        </div>
                        @endif
                    @else
                        <div class="bg-gray-800 rounded-lg md:rounded-xl p-6 md:p-12 text-center border border-gray-700">
                            <i class="fas fa-ticket-alt text-3xl md:text-6xl text-gray-600 mb-3 md:mb-4"></i>
                            <p class="text-gray-400 text-base md:text-xl">No tickets available for this event</p>
                        </div>
                    @endif
                        </div>
                    </div>

                <!-- Speakers/Artists Section -->
                @if($event->speakers->count() > 0)
                    <div class="bg-gray-800 rounded-lg md:rounded-xl p-4 md:p-6 mb-4 md:mb-6 border border-gray-700">
                        <h2 class="text-lg md:text-xl font-bold mb-3 md:mb-4 text-white">
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
                                             class="w-20 h-20 md:w-24 md:h-24 rounded-full object-cover mx-auto mb-2 md:mb-3 border-2 border-teal-500">
                                    @else
                                        <div class="w-20 h-20 md:w-24 md:h-24 rounded-full bg-gradient-primary mx-auto mb-2 md:mb-3 flex items-center justify-center">
                                            <i class="fas fa-user text-white text-2xl md:text-3xl"></i>
                                        </div>
                                    @endif
                                    <h3 class="font-semibold text-white mb-1 text-sm md:text-base">{{ $speaker->name }}</h3>
                                    @if($speaker->topic)
                                        <p class="text-xs md:text-sm text-teal-400 mb-1 md:mb-2">{{ $speaker->topic }}</p>
                                    @endif
                                    @if($speaker->bio)
                                        <p class="text-xs text-gray-400">{{ Str::limit($speaker->bio, 50) }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                </form>
            </div>
        </div>
    </div>

    <!-- Floating Cart Button -->
    <div id="floating-cart" class="floating-cart" style="display: none;">
        <button type="button" 
                onclick="toggleCartSummary()" 
                class="bg-gradient-primary text-white w-16 h-16 rounded-full shadow-lg hover:shadow-xl flex items-center justify-center relative transition-all">
            <i class="fas fa-shopping-cart text-xl"></i>
            <span id="cart-badge" class="cart-badge">0</span>
        </button>
        <div id="cart-summary" class="cart-summary">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-white font-bold">Order Summary</h3>
                <button type="button" onclick="toggleCartSummary()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="cart-items" class="space-y-2 mb-3 max-h-48 overflow-y-auto">
                <p class="text-gray-400 text-sm text-center py-4">No tickets selected</p>
            </div>
            <div class="border-t border-gray-700 pt-3 space-y-2">
                <div id="cart-subtotal" class="flex justify-between text-sm text-gray-300 hidden">
                    <span>Subtotal:</span>
                    <span id="cart-subtotal-amount">₦0.00</span>
                </div>
                <div id="cart-discount" class="flex justify-between text-sm text-green-400 hidden">
                    <span>Discount:</span>
                    <span id="cart-discount-amount">-₦0.00</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t border-gray-700 pt-2">
                    <span class="text-white">Total:</span>
                    <span id="cart-total-amount" class="text-teal-400">₦0.00</span>
                </div>
            </div>
            <button type="submit" 
                    form="ticket-form"
                    onclick="return validateForm()"
                    class="w-full mt-4 bg-gradient-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 active:opacity-80 transition">
                <span id="cart-submit-text">Checkout</span>
            </button>
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
            let totalTickets = 0;
            const orderItems = document.getElementById('order-items');
            const cartItems = document.getElementById('cart-items');
            
            if (orderItems) orderItems.innerHTML = '';
            if (cartItems) cartItems.innerHTML = '';
            
            document.querySelectorAll('input[name^="tickets"][name$="[quantity]"]').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                if (quantity > 0) {
                    totalTickets += quantity;
                    const ticketContainer = input.closest('.ticket-card');
                    if (ticketContainer) {
                        const ticketTypeIdInput = ticketContainer.querySelector('input[type="hidden"][name*="[ticket_type_id]"]');
                        if (ticketTypeIdInput) {
                            const ticketTypeId = parseInt(ticketTypeIdInput.value);
                            const ticketType = ticketTypes.find(t => t.id == ticketTypeId);
                            if (ticketType) {
                                const itemTotal = quantity * ticketType.price;
                                subtotal += itemTotal;
                                
                                // Add to order summary sidebar
                                const itemDiv = document.createElement('div');
                                itemDiv.className = 'flex justify-between items-center text-sm';
                                itemDiv.innerHTML = `
                                    <span class="text-gray-300">${ticketType.name || 'Ticket'} x ${quantity}</span>
                                    <span class="text-white font-semibold">${formatCurrency(itemTotal)}</span>
                                `;
                                orderItems.appendChild(itemDiv);
                                
                                // Add to floating cart summary
                                const cartItemDiv = document.createElement('div');
                                cartItemDiv.className = 'flex justify-between items-center text-sm';
                                cartItemDiv.innerHTML = `
                                    <span class="text-gray-300">${ticketType.name || 'Ticket'} x ${quantity}</span>
                                    <span class="text-white font-semibold">${formatCurrency(itemTotal)}</span>
                                `;
                                cartItems.appendChild(cartItemDiv);
                            }
                        }
                    }
                }
            });
            
            if (orderItems && orderItems.children.length === 0) {
                orderItems.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">Select tickets to see order summary</p>';
            }
            
            if (cartItems && cartItems.children.length === 0) {
                cartItems.innerHTML = '<p class="text-gray-400 text-sm text-center py-4">No tickets selected</p>';
            }
            
            // Update floating cart badge
            const floatingCart = document.getElementById('floating-cart');
            const cartBadge = document.getElementById('cart-badge');
            if (totalTickets > 0) {
                if (floatingCart) {
                    floatingCart.style.display = 'block';
                }
                if (cartBadge) {
                    cartBadge.textContent = totalTickets;
                }
            } else {
                if (floatingCart) {
                    floatingCart.style.display = 'none';
                }
                if (cartBadge) {
                    cartBadge.textContent = '0';
                }
            }
            
            // Cart summary elements only (order summary sidebar removed)
            const cartSubtotalEl = document.getElementById('cart-subtotal-amount');
            const cartDiscountEl = document.getElementById('cart-discount-amount');
            const cartTotalEl = document.getElementById('cart-total-amount');
            const cartSubtotalSection = document.getElementById('cart-subtotal');
            const cartDiscountSection = document.getElementById('cart-discount');
            
            if (subtotal > 0) {
                if (cartSubtotalSection) cartSubtotalSection.classList.remove('hidden');
                if (cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(subtotal);
            } else {
                if (cartSubtotalSection) cartSubtotalSection.classList.add('hidden');
            }
            
            let discount = 0;
            if (appliedCoupon && subtotal > 0) {
                if (appliedCoupon.discount_type === 'percentage') {
                    discount = (subtotal * appliedCoupon.discount_value) / 100;
                } else {
                    discount = Math.min(appliedCoupon.discount_value, subtotal);
                }
                
                if (discount > 0) {
                    if (cartDiscountSection) cartDiscountSection.classList.remove('hidden');
                    if (cartDiscountEl) cartDiscountEl.textContent = '-' + formatCurrency(discount);
                } else {
                    if (cartDiscountSection) cartDiscountSection.classList.add('hidden');
                }
            } else {
                if (cartDiscountSection) cartDiscountSection.classList.add('hidden');
            }
            
            const total = Math.max(0, subtotal - discount);
            if (cartTotalEl) cartTotalEl.textContent = formatCurrency(total);
            
            // Update button text
            const cartSubmitText = document.getElementById('cart-submit-text');
            
            if (total === 0 && totalTickets > 0) {
                if (cartSubmitText) cartSubmitText.textContent = 'Confirm Free Tickets';
            } else if (totalTickets > 0) {
                if (cartSubmitText) cartSubmitText.textContent = `Pay ${formatCurrency(total)}`;
            } else {
                if (cartSubmitText) cartSubmitText.textContent = 'Checkout';
            }
        }
        
        function toggleCartSummary() {
            const cartSummary = document.getElementById('cart-summary');
            cartSummary.classList.toggle('show');
        }
        
        // Close cart summary when clicking outside
        document.addEventListener('click', function(event) {
            const floatingCart = document.getElementById('floating-cart');
            const cartSummary = document.getElementById('cart-summary');
            if (!floatingCart.contains(event.target) && cartSummary.classList.contains('show')) {
                cartSummary.classList.remove('show');
            }
        });
        
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
        
        // Toggle tickets dropdown
        function toggleTickets() {
            const content = document.getElementById('tickets-content');
            const icon = document.getElementById('tickets-icon');
            const isHidden = content.classList.contains('hidden');
            
            if (isHidden) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }
    </script>
    
    @include('partials.footer')
</body>
</html>
