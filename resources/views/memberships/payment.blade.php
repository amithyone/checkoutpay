<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join {{ $membership->name }} - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
</head>
<body class="bg-gray-50 min-h-screen">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('memberships.show', $membership->slug) }}" class="text-primary hover:underline mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Membership
        </a>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 p-6">
                <!-- Membership Info -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Membership Details</h2>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-gray-600">Membership</p>
                                <p class="font-medium text-gray-900">{{ $membership->name }}</p>
                            </div>
                            @if($membership->category)
                            <div>
                                <p class="text-gray-600">Category</p>
                                <p class="font-medium text-gray-900">{{ $membership->category->name }}</p>
                            </div>
                            @endif
                            <div>
                                <p class="text-gray-600">Duration</p>
                                <p class="font-medium text-gray-900">{{ $membership->formatted_duration }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Price</p>
                                <p class="text-2xl font-bold text-primary">{{ $membership->currency }} {{ number_format($membership->price, 2) }}</p>
                            </div>
                            @if($membership->max_members)
                            <div>
                                <p class="text-gray-600">Available Slots</p>
                                <p class="font-medium text-gray-900">{{ $membership->max_members - $membership->current_members }} / {{ $membership->max_members }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    @if($membership->features && count($membership->features) > 0)
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="font-semibold mb-3">Features Included</h3>
                            <ul class="space-y-2 text-sm">
                                @foreach($membership->features as $feature)
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                        <span class="text-gray-700">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <!-- Payment Form -->
                <div class="lg:col-span-2">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Join Membership</h2>
                    
                    <form action="{{ route('memberships.payment.process', $membership->slug) }}" method="POST" class="space-y-6">
                        @csrf

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold mb-4">Your Information</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="member_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                    <input type="text" name="member_name" id="member_name" required value="{{ old('member_name') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    @error('member_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="member_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                    <input type="email" name="member_email" id="member_email" required value="{{ old('member_email') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                    @error('member_email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Your membership card will be sent to this email</p>
                                </div>

                                <div>
                                    <label for="member_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <input type="text" name="member_phone" id="member_phone" value="{{ old('member_phone') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                                </div>
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold mb-4">Payment Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Membership</span>
                                    <span class="font-medium">{{ $membership->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Duration</span>
                                    <span class="font-medium">{{ $membership->formatted_duration }}</span>
                                </div>
                                <div class="border-t border-gray-300 pt-2 mt-2">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-semibold">Total</span>
                                        <span class="text-2xl font-bold text-primary">{{ $membership->currency }} {{ number_format($membership->price, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-primary/90 font-medium text-lg">
                            Proceed to Payment
                        </button>

                        <p class="text-xs text-gray-500 text-center">
                            By proceeding, you agree to the membership terms and conditions. Your membership card will be generated after payment confirmation.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>
