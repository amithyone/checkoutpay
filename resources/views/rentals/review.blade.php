<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Rental - Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold mb-6">Review Your Rental Request</h1>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Renter Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Your Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Name</p>
                    <p class="font-semibold">{{ $renter->name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Email</p>
                    <p class="font-semibold">{{ $renter->email }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Phone</p>
                    <p class="font-semibold">{{ $renter->phone ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Verified Account</p>
                    <p class="font-semibold">{{ $renter->verified_account_name }} - {{ $renter->verified_account_number }}</p>
                </div>
            </div>
        </div>

        <!-- Rental Items by Business -->
        @foreach($businesses as $businessId => $businessData)
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">{{ $businessData['business']->name }}</h2>
                @if($businessData['business']->phone)
                    <p class="text-gray-600 mb-4"><i class="fas fa-phone"></i> {{ $businessData['business']->phone }}</p>
                @endif

                <div class="space-y-4 mb-4">
                    @foreach($businessData['items'] as $itemData)
                        <div class="border-b pb-4">
                            <div class="flex justify-between">
                                <div>
                                    <h3 class="font-semibold">{{ $itemData['item']->name }}</h3>
                                    <p class="text-sm text-gray-600">
                                        {{ $itemData['start_date']->format('M d') }} - {{ $itemData['end_date']->format('M d, Y') }}
                                        ({{ $itemData['days'] }} days)
                                    </p>
                                    <p class="text-sm text-gray-600">Quantity: {{ $itemData['quantity'] }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold">₦{{ number_format($itemData['total'], 2) }}</p>
                                    <p class="text-sm text-gray-600">₦{{ number_format($itemData['rate'], 2) }}/day</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t pt-4">
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total:</span>
                        <span>₦{{ number_format($businessData['total'], 2) }}</span>
                    </div>
                </div>
            </div>
        @endforeach

        <!-- Submit Form -->
        <form action="{{ route('rentals.review') }}" method="POST" class="bg-white rounded-lg shadow p-6">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Additional Notes (Optional)</label>
                <textarea name="renter_notes" rows="3" class="w-full border-gray-300 rounded-md" placeholder="Any special requests or notes..."></textarea>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium">
                Submit Rental Request
            </button>
        </form>
    </div>
</body>
</html>
