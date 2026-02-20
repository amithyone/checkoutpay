<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
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
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="text-center mb-8 sm:mb-12">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">Careers</h1>
            <p class="text-base sm:text-lg text-gray-600">Join our team and help build the future of payments</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Why join us?</h2>
            <ul class="space-y-3 text-gray-600">
                <li class="flex items-start gap-3">
                    <i class="fas fa-check text-primary mt-0.5 flex-shrink-0"></i>
                    <span>Work on products that power thousands of businesses</span>
                </li>
                <li class="flex items-start gap-3">
                    <i class="fas fa-check text-primary mt-0.5 flex-shrink-0"></i>
                    <span>Inclusive, collaborative culture</span>
                </li>
                <li class="flex items-start gap-3">
                    <i class="fas fa-check text-primary mt-0.5 flex-shrink-0"></i>
                    <span>Opportunities to grow and learn</span>
                </li>
            </ul>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Open positions</h2>
            <p class="text-gray-600 mb-6">We don’t have any open roles right now. Check back later or send us your details and we’ll get in touch when something matches.</p>
            <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg hover:bg-primary/90 font-medium text-sm">
                <i class="fas fa-envelope"></i> Get in touch
            </a>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>
