<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - CheckoutPay</title>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-gray-50 min-h-screen flex flex-col">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="h-9 w-9 bg-primary rounded-lg flex items-center justify-center">
                    <i class="fas fa-shield-alt text-white"></i>
                </span>
                <span>
                    <span class="block text-lg font-bold text-gray-900">CheckoutPay</span>
                    <span class="block text-xs text-gray-500 -mt-0.5">Intelligent Payment Gateway</span>
                </span>
            </a>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:text-primary font-medium">Back to home</a>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-2xl bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-10 text-center">
            <div class="mx-auto mb-6 h-16 w-16 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <i class="fas fa-circle-exclamation text-2xl"></i>
            </div>

            <p class="text-sm font-semibold tracking-wide text-primary mb-2">Error @yield('code')</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">@yield('title')</h1>
            <p class="text-gray-600 max-w-xl mx-auto">@yield('message')</p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url()->previous() }}" class="inline-flex justify-center items-center px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium">
                    Go back
                </a>
                <a href="{{ route('home') }}" class="inline-flex justify-center items-center px-5 py-2.5 rounded-lg bg-primary text-white hover:bg-primary/90 font-medium">
                    Go to homepage
                </a>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} CheckoutPay. All rights reserved.</p>
            <a href="{{ route('support.index') }}" class="hover:text-primary">Need help? Contact support</a>
        </div>
    </footer>
</body>
</html>
