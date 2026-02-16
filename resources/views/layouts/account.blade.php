<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Account') - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' }, nude: { 100: '#f5f5f4', 200: '#e7e5e4' } } } } }
    </script>
    <style>
        @media (max-width: 1023px) {
            #account-sidebar.sidebar-closed { transform: translateX(-100%); }
            #account-sidebar.sidebar-open { transform: translateX(0); }
        }
        .dashboard-card { border-radius: 1.25rem; min-height: 7.5rem; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex min-h-screen">
        <div id="account-mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="closeAccountSidebar()"></div>
        <aside id="account-sidebar" class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col z-50 transform transition-transform duration-300 lg:transform-none sidebar-closed">
            <div class="h-16 flex items-center justify-between px-4 border-b">
                <a href="{{ route('user.dashboard') }}" class="font-bold text-primary">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</a>
                <button type="button" onclick="closeAccountSidebar()" class="lg:hidden"><i class="fas fa-times"></i></button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('user.dashboard') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('user.dashboard') ? 'bg-primary/10 text-primary' : '' }}"><i class="fas fa-home w-5 mr-3"></i><span>Dashboard</span></a>
                <a href="{{ route('user.purchases') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('user.purchases') ? 'bg-primary/10 text-primary' : '' }}"><i class="fas fa-shopping-bag w-5 mr-3"></i><span>Rentals</span></a>
                <a href="{{ route('user.invoices') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('user.invoices') ? 'bg-primary/10 text-primary' : '' }}"><i class="fas fa-file-invoice w-5 mr-3"></i><span>Invoices</span></a>
                <a href="{{ route('user.wallet') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('user.wallet*') ? 'bg-primary/10 text-primary' : '' }}"><i class="fas fa-wallet w-5 mr-3"></i><span>Wallet</span></a>
                <a href="{{ route('user.reviews.index') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100"><i class="fas fa-star w-5 mr-3"></i><span>Reviews</span></a>
                <a href="{{ route('user.profile') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100"><i class="fas fa-user w-5 mr-3"></i><span>Profile</span></a>
                <a href="{{ route('user.support.index') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100"><i class="fas fa-headset w-5 mr-3"></i><span>Support</span></a>
                @if(auth()->user()->hasBusinessProfile())
                    <div class="border-t pt-4 mt-4">
                        <a href="{{ route('user.switch-to-business') }}" onclick="closeAccountSidebar()" class="flex items-center px-4 py-3 text-primary rounded-lg hover:bg-primary/10"><i class="fas fa-briefcase w-5 mr-3"></i><span>Open Business dashboard</span></a>
                    </div>
                @endif
            </nav>
            <div class="p-4 border-t">
                <p class="text-sm text-gray-600 truncate">{{ auth()->user()->email }}</p>
                <form action="{{ route('logout') }}" method="POST" class="mt-2">@csrf<button type="submit" class="text-sm text-gray-700 hover:underline"><i class="fas fa-sign-out-alt mr-1"></i>Log out</button></form>
            </div>
        </aside>
        <div class="flex-1 flex flex-col min-h-screen">
            <header class="sticky top-0 z-30 h-14 lg:h-16 bg-white border-b flex items-center px-4 shrink-0">
                <button type="button" onclick="openAccountSidebar()" class="lg:hidden w-10 h-10 -ml-2"><i class="fas fa-bars text-xl"></i></button>
                <div class="flex-1 flex justify-center lg:justify-start min-w-0">
                    <div class="lg:hidden flex justify-center w-full">
                        @php $logo = \App\Models\Setting::get('email_logo') ?: \App\Models\Setting::get('site_logo'); @endphp
                        <a href="{{ route('user.dashboard') }}">@if($logo)<img src="{{ asset('storage/' . $logo) }}" alt="{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}" class="h-8 max-h-10 w-auto object-contain">@else<span class="font-bold text-primary">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</span>@endif</a>
                    </div>
                    <h1 class="hidden lg:block text-lg font-semibold text-gray-900">@yield('page-title', 'My Account')</h1>
                </div>
                <div class="w-10 lg:hidden"></div>
            </header>
            <main class="flex-1 p-4 lg:p-6 pb-24 lg:pb-6">
                @if(session('success'))<div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>@endif
                @if(isset($errors) && $errors->any())<div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-2 py-2 z-30 flex justify-around safe-area-pb">
        <a href="{{ route('user.dashboard') }}" class="flex flex-col items-center py-2 min-w-0 {{ request()->routeIs('user.dashboard') ? 'text-primary' : 'text-gray-500' }}"><i class="fas fa-home text-lg mb-0.5"></i><span class="text-xs truncate w-full text-center">Home</span></a>
        <a href="{{ route('user.purchases') }}" class="flex flex-col items-center py-2 min-w-0 {{ request()->routeIs('user.purchases') ? 'text-primary' : 'text-gray-500' }}"><i class="fas fa-shopping-bag text-lg mb-0.5"></i><span class="text-xs truncate w-full text-center">Purchases</span></a>
        <a href="{{ route('user.support.index') }}" class="flex flex-col items-center py-2 min-w-0 {{ request()->routeIs('user.support.*') ? 'text-primary' : 'text-gray-500' }}"><i class="fas fa-headset text-lg mb-0.5"></i><span class="text-xs truncate w-full text-center">Support</span></a>
        <a href="{{ route('user.profile') }}" class="flex flex-col items-center py-2 min-w-0 {{ request()->routeIs('user.profile') ? 'text-primary' : 'text-gray-500' }}"><i class="fas fa-user text-lg mb-0.5"></i><span class="text-xs truncate w-full text-center">Profile</span></a>
        <button type="button" onclick="openAccountSidebar()" class="flex flex-col items-center py-2 min-w-0 text-gray-500"><i class="fas fa-bars text-lg mb-0.5"></i><span class="text-xs truncate w-full text-center">More</span></button>
    </nav>
    <script>
        function openAccountSidebar() { document.getElementById('account-sidebar').classList.add('sidebar-open'); document.getElementById('account-sidebar').classList.remove('sidebar-closed'); document.getElementById('account-mobile-overlay').classList.remove('hidden'); }
        function closeAccountSidebar() { document.getElementById('account-sidebar').classList.remove('sidebar-open'); document.getElementById('account-sidebar').classList.add('sidebar-closed'); document.getElementById('account-mobile-overlay').classList.add('hidden'); }
    </script>
    @stack('scripts')
</body>
</html>
