<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Demo - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
    <section class="py-12 sm:py-16 md:py-20 bg-gradient-to-br from-primary/10 via-white to-primary/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 text-center">Checkout Demo</h1>
            <p class="text-center text-gray-600 mb-8">Experience our payment checkout flow</p>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 max-w-2xl mx-auto">
                <a href="{{ route('checkout.show') }}" class="block w-full text-center px-6 py-4 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
                    Try Checkout Demo
                </a>
            </div>
        </div>
    </section>
    @include('partials.footer')
</body>
</html>
