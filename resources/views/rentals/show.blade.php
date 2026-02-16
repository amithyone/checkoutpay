<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $item->name }} - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @php $rentalsColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-24 sm:pb-8">
        <a href="{{ route('rentals.index') }}" class="text-gray-700 hover:text-gray-900 mb-3 sm:mb-4 inline-flex items-center gap-1 text-sm sm:text-base">
            <i class="fas fa-arrow-left"></i> Back to Rentals
        </a>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8 p-4 sm:p-6">
                <!-- Images: smaller on mobile -->
                <div class="lg:min-w-0">
                    @if($item->images && count($item->images) > 0)
                        <img src="{{ asset('storage/' . $item->images[0]) }}" alt="{{ $item->name }}" class="w-full h-48 sm:h-72 lg:h-96 object-cover rounded-xl sm:rounded-2xl">
                        @if(count($item->images) > 1)
                            <div class="grid grid-cols-4 gap-1.5 sm:gap-2 mt-2">
                                @foreach(array_slice($item->images, 1, 4) as $image)
                                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $item->name }}" class="w-full h-14 sm:h-20 object-cover rounded-lg">
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="w-full h-48 sm:h-72 lg:h-96 bg-gray-100 rounded-xl sm:rounded-2xl flex items-center justify-center">
                            <i class="fas fa-image text-gray-300 text-4xl sm:text-5xl"></i>
                        </div>
                    @endif
                </div>

                <!-- Details: smaller text on mobile -->
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-2 sm:mb-4">{{ $item->name }}</h1>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 mb-3 sm:mb-4">
                        @if($item->category)
                            <span class="text-xs sm:text-sm font-medium px-2.5 py-1 rounded-full" style="background-color: {{ $rentalsColor }}20; color: {{ $rentalsColor }};">{{ $item->category->name }}</span>
                        @endif
                        @if($item->city)
                            <span class="text-gray-600 text-xs sm:text-sm"><i class="fas fa-map-marker-alt mr-0.5"></i> {{ $item->city }}</span>
                        @endif
                    </div>

                    <div class="mb-4 sm:mb-6">
                        <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">₦{{ number_format($item->daily_rate, 2) }} <span class="text-sm sm:text-base text-gray-600 font-normal">/ day</span></p>
                        @if($item->weekly_rate || $item->monthly_rate)
                            <p class="text-gray-600 text-xs sm:text-sm mt-1">
                                @if($item->weekly_rate) Weekly: ₦{{ number_format($item->weekly_rate, 2) }}@endif
                                @if($item->weekly_rate && $item->monthly_rate) · @endif
                                @if($item->monthly_rate) Monthly: ₦{{ number_format($item->monthly_rate, 2) }}@endif
                            </p>
                        @endif
                    </div>

                    <div class="mb-4 sm:mb-6">
                        <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-1 sm:mb-2">Description</h3>
                        <p class="text-gray-700 text-sm sm:text-base leading-relaxed">{{ $item->description }}</p>
                    </div>

                    @if($item->specifications && count($item->specifications) > 0)
                        <div class="mb-4 sm:mb-6">
                            <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-1 sm:mb-2">Specifications</h3>
                            <ul class="list-disc list-inside text-gray-700 text-sm sm:text-base space-y-0.5">
                                @foreach($item->specifications as $key => $value)
                                    <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Add to cart: dates are chosen on the cart/checkout page -->
                    <form action="{{ route('rentals.cart.add') }}" method="POST" class="bg-gray-50 p-3 sm:p-4 rounded-xl" id="addToCartForm">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        <div class="mb-3 sm:mb-4">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Quantity</label>
                            <input type="number" name="quantity" value="1" min="1" max="{{ $item->quantity_available }}" required class="w-full text-sm sm:text-base border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-gray-400">
                            <p class="text-xs text-gray-500 mt-1">{{ $item->quantity_available }} available. You’ll choose start date and number of days on the cart page.</p>
                        </div>
                        <button type="submit" class="w-full text-white py-2.5 sm:py-3 rounded-xl font-medium text-sm sm:text-base hover:opacity-90 transition" style="background-color: {{ $rentalsColor }};">
                            <i class="fas fa-shopping-cart mr-2"></i> Add to Cart
                        </button>
                    </form>

                    <div class="mt-3 sm:mt-4 text-xs sm:text-sm text-gray-600 space-y-1">
                        <p><i class="fas fa-building w-4"></i> <strong>Business:</strong> {{ $item->business->name }}</p>
                        @if($item->business->phone)
                            <p><i class="fas fa-phone w-4"></i> <strong>Phone:</strong> {{ $item->business->phone }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-20 right-4 z-50 space-y-2"></div>

    <!-- Floating Cart Icon -->
    @php
        $cartCount = count(session('rental_cart', []));
    @endphp
    <a href="{{ route('rentals.checkout') }}" class="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 text-white rounded-full p-4 shadow-lg transition-all z-50 group" style="background-color: {{ $rentalsColor }};">
        <i class="fas fa-shopping-cart text-xl"></i>
        @if($cartCount > 0)
            <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">
                {{ $cartCount }}
            </span>
        @else
            <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center hidden">
                0
            </span>
        @endif
        <span class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-sm px-3 py-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
            View Cart
        </span>
    </a>

    <script>
        // Show toast notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[300px] animate-slide-in`;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Update cart count
        function updateCartCount() {
            const cartCountEl = document.getElementById('cart-count');
            if (!cartCountEl) return;
            
            // Simple increment for now (more reliable)
            const currentCount = parseInt(cartCountEl.textContent) || 0;
            const newCount = currentCount + 1;
            cartCountEl.textContent = newCount;
            cartCountEl.style.display = 'flex';
            
            // Show cart icon if hidden
            const cartIcon = cartCountEl.closest('a');
            if (cartIcon) {
                cartIcon.style.display = 'flex';
            }
        }

        // Handle form submission with AJAX
        document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Adding...';

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(response => {
                const ct = response.headers.get('content-type') || '';
                const isJson = ct.includes('application/json');
                if (isJson) return response.json().then(data => ({ ok: response.ok, data }));
                if (!response.ok) throw new Error('Request failed');
                return response.text().then(() => ({ ok: false, data: { success: false, message: 'Invalid response' } }));
            })
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    showToast(data.message || 'Item added to cart!', 'success');
                    updateCartCount();
                } else {
                    const msg = (data && (data.message || data.errors && Object.values(data.errors).flat().join(' '))) || 'Failed to add to cart';
                    showToast(msg, 'error');
                }
            })
            .catch(() => {
                showToast('Could not add to cart. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Show session messages as toasts
        @if(session('success'))
            showToast('{{ session('success') }}', 'success');
        @endif
        @if(session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slide-in {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            .animate-slide-in {
                animation: slide-in 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
