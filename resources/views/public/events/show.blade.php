<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }} - CheckoutPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <a href="{{ route('public.events.index') }}" class="text-blue-600 hover:text-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Events
                </a>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            @if(isset($unavailable) && $unavailable)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                    <p class="text-yellow-800">This event is no longer available for registration.</p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Event Details -->
                <div class="lg:col-span-2 space-y-6">
                    @if($event->event_banner)
                        <img src="{{ asset('storage/' . $event->event_banner) }}" alt="{{ $event->title }}" class="w-full h-64 object-cover rounded-xl">
                    @endif

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $event->title }}</h1>
                        <div class="prose max-w-none">
                            <p class="text-gray-700 whitespace-pre-line">{{ $event->description }}</p>
                        </div>
                    </div>

                    <!-- Event Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Event Details</h2>
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <i class="fas fa-calendar-alt text-blue-600 mr-3 mt-1"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Date & Time</p>
                                    <p class="text-gray-600">{{ $event->start_date->format('l, F d, Y') }} at {{ $event->start_date->format('h:i A') }}</p>
                                </div>
                            </div>
                            @if($event->venue_name)
                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt text-blue-600 mr-3 mt-1"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">Venue</p>
                                        <p class="text-gray-600">{{ $event->venue_name }}</p>
                                        @if($event->venue_address)
                                            <p class="text-gray-600">{{ $event->venue_address }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Ticket Selection -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-4">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Get Tickets</h2>
                        
                        @if($event->isAvailableForRegistration() && $event->activeTicketTypes->count() > 0)
                            <form id="ticket-form" class="space-y-4">
                                @foreach($event->activeTicketTypes as $ticketType)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <h3 class="font-semibold text-gray-900">{{ $ticketType->name }}</h3>
                                                <p class="text-sm text-gray-600">{{ $ticketType->description }}</p>
                                            </div>
                                            <span class="text-lg font-bold text-gray-900">₦{{ number_format($ticketType->price, 2) }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-3">
                                            <input type="number" 
                                                   name="ticket_types[{{ $ticketType->id }}]" 
                                                   min="{{ $ticketType->min_per_order }}" 
                                                   max="{{ min($ticketType->max_per_order, $ticketType->available_quantity) }}"
                                                   value="0"
                                                   class="w-20 px-2 py-1 border border-gray-300 rounded text-center ticket-quantity"
                                                   data-price="{{ $ticketType->price }}"
                                                   data-type-id="{{ $ticketType->id }}">
                                            <span class="text-sm text-gray-600">{{ $ticketType->available_quantity }} available</span>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="pt-4 border-t border-gray-200">
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-semibold text-gray-900">Total:</span>
                                        <span class="text-xl font-bold text-gray-900" id="total-amount">₦0.00</span>
                                    </div>
                                    <button type="button" onclick="proceedToCheckout()" class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                                        Continue to Checkout
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-gray-600 text-center py-8">
                                @if($event->isSoldOut())
                                    This event is sold out
                                @else
                                    Tickets are not available at this time
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Calculate total
        document.querySelectorAll('.ticket-quantity').forEach(input => {
            input.addEventListener('change', calculateTotal);
        });

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.ticket-quantity').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const price = parseFloat(input.dataset.price) || 0;
                total += quantity * price;
            });
            document.getElementById('total-amount').textContent = '₦' + total.toFixed(2);
        }

        function proceedToCheckout() {
            const quantities = {};
            let hasTickets = false;
            
            document.querySelectorAll('.ticket-quantity').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                if (quantity > 0) {
                    quantities[input.dataset.typeId] = quantity;
                    hasTickets = true;
                }
            });

            if (!hasTickets) {
                alert('Please select at least one ticket');
                return;
            }

            // Redirect to checkout page with selected tickets
            const params = new URLSearchParams();
            Object.keys(quantities).forEach(typeId => {
                params.append('ticket_types[' + typeId + ']', quantities[typeId]);
            });
            window.location.href = '{{ route("public.tickets.payment", ["order" => "new"]) }}?' + params.toString();
        }
    </script>
</body>
</html>
