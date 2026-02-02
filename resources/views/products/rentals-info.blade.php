<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rentals - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
<body class="bg-white">
    @include('partials.nav')

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary/10 rounded-full mb-6">
                    <i class="fas fa-box text-primary text-3xl"></i>
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">
                    Equipment Rentals Made Easy
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    A flexible platform for businesses to rent out equipment, vehicles, properties, and more to customers on a short-term or long-term basis.
                </p>
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="{{ route('rentals.index') }}" class="w-full sm:w-auto bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                        Browse Rentals
                    </a>
                    <a href="#features" class="w-full sm:w-auto bg-white text-primary border-2 border-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/5 font-medium text-base sm:text-lg transition-colors">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Everything You Need for Rental Management</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Powerful rental features with flexible pricing and availability tracking</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Feature 1 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-alt text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Flexible Duration</h3>
                    <p class="text-gray-600">
                        Rent items for days, weeks, or months based on customer needs. Set daily, weekly, and monthly rates.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Availability Management</h3>
                    <p class="text-gray-600">
                        Track item availability and prevent double bookings. Automatic calendar management for seamless rentals.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-tags text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Category Organization</h3>
                    <p class="text-gray-600">
                        Organize rentals by category (Camera, Lighting, Apartments, Cars, etc.) for easy browsing and discovery.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-map-marker-alt text-orange-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Location-Based</h3>
                    <p class="text-gray-600">
                        Filter rentals by city to find items near you. Perfect for local equipment and property rentals.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shopping-cart text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Cart System</h3>
                    <p class="text-gray-600">
                        Add multiple items to cart and checkout together. Streamlined rental process for customers.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Secure Payments</h3>
                    <p class="text-gray-600">
                        Integrated payment processing with KYC verification. Secure transactions for both businesses and renters.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">How It Works</h2>
                <p class="text-lg text-gray-600">Simple rental process in 4 steps</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        1
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Browse & Select</h3>
                    <p class="text-gray-600">
                        Search and filter available items by category, city, and price. Add items to your cart.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        2
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Choose Dates</h3>
                    <p class="text-gray-600">
                        Select start and end dates for your rental period. Create account and complete KYC verification.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        3
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Complete Payment</h3>
                    <p class="text-gray-600">
                        Pay securely through our payment gateway. Receive rental confirmation with business contact details.
                    </p>
                </div>

                <!-- Step 4 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        4
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Pickup & Return</h3>
                    <p class="text-gray-600">
                        Coordinate with the business for item pickup and return. Enjoy your rental period.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Perfect For Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Perfect For</h2>
                <p class="text-lg text-gray-600">Whether you're renting equipment, vehicles, or properties</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-camera text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Camera & Equipment</h3>
                    <p class="text-sm text-gray-600">Professional cameras, lenses, lighting equipment, and video gear</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-car text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Vehicles</h3>
                    <p class="text-sm text-gray-600">Cars, motorcycles, trucks, and other vehicles for short-term use</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-building text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Properties</h3>
                    <p class="text-sm text-gray-600">Apartments, houses, event spaces, and commercial properties</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-tools text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Tools & Equipment</h3>
                    <p class="text-sm text-gray-600">Construction tools, party equipment, furniture, and more</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">Ready to Start Renting?</h2>
            <p class="text-lg md:text-xl text-primary-100 mb-8">List your items or browse available rentals today!</p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    Create Free Account
                </a>
                <a href="{{ route('rentals.index') }}" class="w-full sm:w-auto bg-transparent text-white border-2 border-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-white/10 font-medium text-base sm:text-lg transition-colors">
                    Browse Rentals
                </a>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
