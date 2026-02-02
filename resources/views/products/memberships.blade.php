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
                    Membership Management Made Simple
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Create and manage membership programs for gyms, fitness classes, and more. Perfect for businesses offering recurring memberships with flexible pricing and duration options.
                </p>
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="{{ route('business.register') }}" class="w-full sm:w-auto bg-primary text-white px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/90 font-medium text-base sm:text-lg transition-colors shadow-lg">
                        Get Started Free
                    </a>
                    <a href="{{ route('memberships.index') }}" class="w-full sm:w-auto bg-white text-primary border-2 border-primary px-6 sm:px-8 py-3 sm:py-4 rounded-lg hover:bg-primary/5 font-medium text-base sm:text-lg transition-colors">
                        Browse Memberships
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-center mb-8 sm:mb-12">Why Choose Our Membership System?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Member Management</h3>
                    <p class="text-gray-600">Track members, set limits, and manage capacity with ease.</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-alt text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Flexible Duration</h3>
                    <p class="text-gray-600">Set memberships for days, weeks, months, or years.</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-tags text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Target Audience</h3>
                    <p class="text-gray-600">Specify who your membership is for with our "Who is it for?" feature.</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-star text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Featured Listings</h3>
                    <p class="text-gray-600">Highlight your best memberships to attract more customers.</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-images text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Rich Media</h3>
                    <p class="text-gray-600">Upload multiple images to showcase your facilities and services.</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-list-check text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Features & Benefits</h3>
                    <p class="text-gray-600">List all the features and benefits included in each membership.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Who is it for Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-center mb-8 sm:mb-12">Perfect For</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-4">
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-dumbbell text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">Gyms</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-running text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">Fitness Classes</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-spa text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">Yoga Studios</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-swimming-pool text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">Swimming Pools</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-music text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">Dance Classes</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-fist-raised text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">Martial Arts</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow text-center">
                    <i class="fas fa-ellipsis-h text-primary text-2xl mb-2"></i>
                    <p class="text-sm font-medium">And More</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20 bg-primary">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4">Ready to Get Started?</h2>
            <p class="text-primary-100 mb-8 max-w-2xl mx-auto">
                Create your first membership plan today and start attracting members to your business.
            </p>
            <a href="{{ route('business.register') }}" class="inline-block bg-white text-primary px-8 py-3 rounded-lg hover:bg-gray-100 font-medium text-lg transition-colors shadow-lg">
                Create Free Account
            </a>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
