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
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            {{ $event->venue }}
                        </div>
                    </div>
                    @if($event->description)
                        <p class="text-gray-700 mb-4">{{ $event->description }}</p>
                    @endif
                </div>
            </div>

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
                                        <div class="text-2xl font-bold text-primary">₦{{ number_format($ticketType->price, 2) }}</div>
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

                    <!-- Total -->
                    <div class="border-t border-gray-200 pt-4 mb-6">
                        <div class="flex justify-between items-center text-xl font-bold">
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
        
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('input[type="number"]').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const ticketTypeId = input.closest('.border').querySelector('input[type="hidden"]').value;
                const ticketType = ticketTypes.find(t => t.id == ticketTypeId);
                if (ticketType) {
                    total += quantity * ticketType.price;
                }
            });
            document.getElementById('total-amount').textContent = '₦' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
    </script>
</body>
</html>
