<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memberships - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
                    <i class="fas fa-id-card text-primary text-3xl"></i>
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">
                    Memberships
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Subscription memberships with digital cards. QR codes included.
                </p>
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="{{ route('memberships.index') }}" class="w-full sm:w-auto bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                        Browse Memberships
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">How It Works</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Create membership plans. Issue digital cards. Verify with QR codes.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Feature 1 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-id-card text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Digital Cards</h3>
                    <p class="text-gray-600">
                        PDF membership cards with QR codes.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-qrcode text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">QR Verification</h3>
                    <p class="text-gray-600">
                        Scan QR codes to verify membership status.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-alt text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Flexible Duration</h3>
                    <p class="text-gray-600">
                        Set membership duration in days, weeks, months, or years.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-users text-orange-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Member Management</h3>
                    <p class="text-gray-600">
                        Track active members, set capacity limits, and manage renewals. Automatic expiration tracking.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-paint-brush text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Custom Branding</h3>
                    <p class="text-gray-600">
                        Upload your logo and background graphics to create branded membership cards that reflect your business identity.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Secure Payments</h3>
                    <p class="text-gray-600">
                        Integrated payment processing with automatic verification. Members receive cards immediately after payment confirmation.
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
                <p class="text-lg text-gray-600">Create membership plans. Issue digital cards. Verify with QR codes.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        1
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Create Membership</h3>
                    <p class="text-gray-600">
                        Set up your membership plan with pricing, duration, features, and custom card design.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        2
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Customer Joins</h3>
                    <p class="text-gray-600">
                        Customers browse memberships and select the plan that fits their needs.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        3
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Payment & Card</h3>
                    <p class="text-gray-600">
                        Secure payment processing. Member receives digital membership card with QR code instantly.
                    </p>
                </div>

                <!-- Step 4 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        4
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Access Granted</h3>
                    <p class="text-gray-600">
                        Member uses their card to access facilities or services. QR code verification for check-ins.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Perfect For Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Use Cases</h2>
                <p class="text-lg text-gray-600">Whether you're a gym, fitness center, or membership-based business</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-dumbbell text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Gyms & Fitness Centers</h3>
                    <p class="text-sm text-gray-600">Gym memberships</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-running text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Fitness Classes</h3>
                    <p class="text-sm text-gray-600">Yoga, pilates, dance, martial arts, and other class-based memberships</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-swimming-pool text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Swimming Pools</h3>
                    <p class="text-sm text-gray-600">Pool access memberships for individuals and families</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-users text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Clubs & Communities</h3>
                    <p class="text-sm text-gray-600">Clubs and communities</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">Get Started</h2>
            <p class="text-lg md:text-xl text-primary-100 mb-8">Create an account.</p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    Create Account
                </a>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
