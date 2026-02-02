<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Rentals</title>
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
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-gray-200">
                <h1 class="text-xl font-bold text-primary">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
            </div>

            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('renter.dashboard') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('renter.dashboard') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-home w-5 mr-3"></i>
                    <span>My Rentals</span>
                </a>
                <a href="{{ route('rentals.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-search w-5 mr-3"></i>
                    <span>Browse Rentals</span>
                </a>
                <a href="{{ route('business.register') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 border-t border-gray-200 mt-4 pt-4">
                    <i class="fas fa-building w-5 mr-3"></i>
                    <span>Upgrade to Business</span>
                </a>
            </nav>

            <div class="p-4 border-t border-gray-200">
                <form action="{{ route('renter.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="p-6">
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
    @includeIf('components.beta-badge')
</body>
</html>
