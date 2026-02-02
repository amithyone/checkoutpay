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
                        primary: {
                            DEFAULT: '#3C50E0',
                        },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('rentals.index') }}" class="text-primary hover:underline mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Rentals
        </a>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-6">
                <!-- Images -->
                <div>
                    @if($item->images && count($item->images) > 0)
                        <img src="{{ asset('storage/' . $item->images[0]) }}" alt="{{ $item->name }}" class="w-full h-96 object-cover rounded-lg mb-4">
                        @if(count($item->images) > 1)
                            <div class="grid grid-cols-4 gap-2">
                                @foreach(array_slice($item->images, 1, 4) as $image)
                                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $item->name }}" class="w-full h-20 object-cover rounded">
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="w-full h-96 bg-gray-200 rounded-lg flex items-center justify-center">
                            <i class="fas fa-image text-gray-400 text-6xl"></i>
                        </div>
                    @endif
                </div>

                <!-- Details -->
                <div>
                    <h1 class="text-3xl font-bold mb-4">{{ $item->name }}</h1>
                    <div class="flex items-center gap-4 mb-4">
                        <span class="bg-primary/10 text-primary px-3 py-1 rounded-full text-sm">{{ $item->category->name }}</span>
                        <span class="text-gray-600"><i class="fas fa-map-marker-alt"></i> {{ $item->city }}</span>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-primary mb-2">₦{{ number_format($item->daily_rate, 2) }} <span class="text-lg text-gray-600 font-normal">/ day</span></h2>
                        @if($item->weekly_rate)
                            <p class="text-gray-600">Weekly: ₦{{ number_format($item->weekly_rate, 2) }}</p>
                        @endif
                        @if($item->monthly_rate)
                            <p class="text-gray-600">Monthly: ₦{{ number_format($item->monthly_rate, 2) }}</p>
                        @endif
                    </div>

                    <div class="mb-6">
                        <h3 class="font-semibold mb-2">Description</h3>
                        <p class="text-gray-700">{{ $item->description }}</p>
                    </div>

                    @if($item->specifications)
                        <div class="mb-6">
                            <h3 class="font-semibold mb-2">Specifications</h3>
                            <ul class="list-disc list-inside text-gray-700">
                                @foreach($item->specifications as $key => $value)
                                    <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Rental Form -->
                    <form action="{{ route('rentals.cart.add') }}" method="POST" class="bg-gray-50 p-4 rounded-lg" id="addToCartForm">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Start Date</label>
                                <input type="date" name="start_date" required min="{{ date('Y-m-d') }}" class="w-full border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">End Date</label>
                                <input type="date" name="end_date" required min="{{ date('Y-m-d') }}" class="w-full border-gray-300 rounded-md">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Quantity</label>
                            <input type="number" name="quantity" value="1" min="1" max="{{ $item->quantity_available }}" required class="w-full border-gray-300 rounded-md">
                            <p class="text-xs text-gray-500 mt-1">{{ $item->quantity_available }} available</p>
                        </div>

                        <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                            <i class="fas fa-shopping-cart mr-2"></i> Add to Cart
                        </button>
                    </form>

                    <div class="mt-4 text-sm text-gray-600">
                        <p><i class="fas fa-building"></i> <strong>Business:</strong> {{ $item->business->name }}</p>
                        @if($item->business->phone)
                            <p><i class="fas fa-phone"></i> <strong>Phone:</strong> {{ $item->business->phone }}</p>
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
    <a href="{{ route('rentals.checkout') }}" class="fixed bottom-6 right-6 bg-primary text-white rounded-full p-4 shadow-lg hover:bg-primary/90 transition-all z-50 group">
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
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Item added to cart!', 'success');
                    updateCartCount();
                    // Optionally reset form or keep values
                } else {
                    showToast(data.message || 'Failed to add item to cart', 'error');
                }
            })
            .catch(error => {
                // Fallback to normal form submission
                form.submit();
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
