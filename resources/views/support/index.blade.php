<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
    <!-- Navigation -->
    @include('partials.nav')

    <!-- Hero -->
    <section class="bg-gradient-to-br from-primary/10 via-white to-primary/5 py-12 sm:py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-4 sm:mb-6">Support Center</h1>
                <p class="text-base sm:text-lg md:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                    Get help when you need it. Find answers to common questions or contact our support team.
                </p>
            </div>
        </div>
    </section>

    <!-- Help Sections -->
    <section class="py-12 sm:py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-book text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Documentation</h3>
                    <p class="text-gray-600 mb-4">Comprehensive guides and API documentation to help you integrate.</p>
                    <a href="{{ route('business.api-documentation.index') }}" class="text-primary hover:text-primary/80 font-medium">
                        View Documentation <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8">
                    <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-envelope text-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Contact Support</h3>
                    <p class="text-gray-600 mb-4">Get in touch with our support team for assistance.</p>
                    <a href="{{ route('contact') }}" class="text-primary hover:text-primary/80 font-medium">
                        Contact Us <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
