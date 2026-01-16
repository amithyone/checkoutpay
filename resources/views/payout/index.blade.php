<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
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
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 text-center">Payout Solutions</h1>
            <p class="text-center text-gray-600 mb-8">Withdraw your funds quickly and securely to your bank account.</p>
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 sm:p-8 max-w-2xl mx-auto">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Features</h2>
                <ul class="space-y-3 text-gray-600">
                    <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i> Fast withdrawals</li>
                    <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i> Secure processing</li>
                    <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i> Auto-withdrawal options</li>
                </ul>
            </div>
        </div>
    </section>
    @include('partials.footer')
</body>
</html>
