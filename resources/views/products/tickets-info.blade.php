<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
                    <i class="fas fa-ticket-alt text-primary text-3xl"></i>
                </div>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">
                    Event Ticketing Made Simple
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    A comprehensive event ticketing solution for businesses to sell tickets, manage events, and verify attendees with QR codes.
                </p>
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="{{ route('tickets.index') }}" class="w-full sm:w-auto bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                        Browse Events
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
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">Everything You Need for Event Ticketing</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Powerful ticketing features with QR code verification and digital delivery</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Feature 1 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-alt text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Event Management</h3>
                    <p class="text-gray-600">
                        Create and manage multiple events with detailed information, dates, venues, and descriptions. Full event lifecycle management.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-ticket-alt text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Multiple Ticket Types</h3>
                    <p class="text-gray-600">
                        Offer different ticket tiers (VIP, General Admission, Early Bird, etc.) with varying prices and benefits.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-qrcode text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">QR Code Verification</h3>
                    <p class="text-gray-600">
                        Each ticket includes a unique QR code for quick and secure entry verification. Mobile scanner for easy check-ins.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-file-pdf text-orange-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Digital Tickets</h3>
                    <p class="text-gray-600">
                        Tickets are delivered as professional PDF files via email after payment confirmation. Print or store on mobile devices.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Real-time Inventory</h3>
                    <p class="text-gray-600">
                        Track available tickets and automatically stop sales when capacity is reached. Prevent overselling.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Secure Payments</h3>
                    <p class="text-gray-600">
                        Integrated payment processing with automatic verification. Tickets delivered instantly after payment confirmation.
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
                <p class="text-lg text-gray-600">Get started in minutes</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        1
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Create Event</h3>
                    <p class="text-gray-600">
                        Business creates an event with details, dates, venue, and ticket types with pricing.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        2
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Publish & Browse</h3>
                    <p class="text-gray-600">
                        Event goes live and customers can browse and select ticket types and quantities.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        3
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Purchase & Receive</h3>
                    <p class="text-gray-600">
                        Customers complete payment and receive digital tickets via email with QR codes.
                    </p>
                </div>

                <!-- Step 4 -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary text-white rounded-full text-2xl font-bold mb-4">
                        4
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Verify Entry</h3>
                    <p class="text-gray-600">
                        On event day, customers present QR codes. Business scans codes for quick verification.
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
                <p class="text-lg text-gray-600">Whether you're hosting concerts, conferences, or any event</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-music text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Concerts & Music</h3>
                    <p class="text-sm text-gray-600">Sell tickets for concerts, festivals, and live performances</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-users text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Conferences</h3>
                    <p class="text-sm text-gray-600">Manage professional conferences, workshops, and training sessions</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-theater-masks text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Theater & Shows</h3>
                    <p class="text-sm text-gray-600">Sell tickets for plays, comedy shows, and theatrical performances</p>
                </div>
                <div class="bg-white rounded-lg p-6 text-center shadow-sm border border-gray-200">
                    <i class="fas fa-running text-primary text-3xl mb-4"></i>
                    <h3 class="font-bold text-gray-900 mb-2">Sports Events</h3>
                    <p class="text-sm text-gray-600">Manage tickets for sports matches, tournaments, and competitions</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-r from-primary to-primary/90">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">Ready to Start Selling Tickets?</h2>
            <p class="text-lg md:text-xl text-primary-100 mb-8">Create your first event and start selling tickets today!</p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-white text-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-gray-100 font-medium text-base sm:text-lg transition-colors shadow-lg">
                    Create Free Account
                </a>
                <a href="{{ route('tickets.index') }}" class="w-full sm:w-auto bg-transparent text-white border-2 border-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-white/10 font-medium text-base sm:text-lg transition-colors">
                    Browse Events
                </a>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
