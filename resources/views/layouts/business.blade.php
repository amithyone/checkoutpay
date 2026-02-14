<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Payment Gateway</title>
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
                        primary: {
                            DEFAULT: '#3C50E0',
                        },
                        secondary: '#8FD0EF',
                        dark: '#1C2434',
                    }
                }
            }
        }
    </script>
    <style>
        @media (max-width: 1023px) {
            #sidebar.sidebar-closed {
                transform: translateX(-100%);
            }
            #sidebar.sidebar-open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    @if(session()->has('admin_impersonating_business_id') && auth('business')->check())
        <div class="bg-yellow-500 text-white px-4 py-3 flex items-center justify-between z-50 relative">
            <div class="flex items-center">
                <i class="fas fa-user-secret mr-2"></i>
                <span class="font-medium">You are viewing as <strong>{{ auth('business')->user()->name }}</strong> (Admin Impersonation Mode)</span>
            </div>
            <form action="{{ route('admin.businesses.exit-impersonation') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-white text-yellow-600 px-4 py-1 rounded hover:bg-gray-100 font-medium">
                    <i class="fas fa-sign-out-alt mr-1"></i> Exit View
                </button>
            </form>
        </div>
    @endif
    <div class="flex h-screen overflow-hidden">
        <!-- Mobile Sidebar Overlay -->
        <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col z-50 transform transition-transform duration-300 ease-in-out lg:transform-none sidebar-closed">
            <!-- Logo -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200">
                @php
                    $businessLogo = \App\Models\Setting::get('business_logo');
                    $businessLogoPath = $businessLogo ? storage_path('app/public/' . $businessLogo) : null;
                    $businessLogoExists = $businessLogo && $businessLogoPath && file_exists($businessLogoPath);
                    
                    // Fallback to site logo if business logo not set
                    if (!$businessLogoExists) {
                        $logo = \App\Models\Setting::get('site_logo');
                        $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        $logoExists = $logo && $logoPath && file_exists($logoPath);
                    } else {
                        $logoExists = false;
                    }
                @endphp
                @if($businessLogoExists)
                    <img src="{{ asset('storage/' . $businessLogo) }}" alt="Logo" class="h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <h1 class="text-xl font-bold text-primary" style="display: none;">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                @elseif($logoExists)
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <h1 class="text-xl font-bold text-primary" style="display: none;">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                @else
                    <h1 class="text-xl font-bold text-primary">{{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</h1>
                @endif
                <button onclick="closeSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('business.dashboard') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.dashboard') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-chart-line w-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('business.transactions.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.transactions.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-exchange-alt w-5 mr-3"></i>
                    <span>Transactions</span>
                </a>

                <a href="{{ route('business.invoices.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.invoices.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-file-invoice w-5 mr-3"></i>
                    <span>Invoices</span>
                </a>

                <a href="{{ route('business.rentals.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.rentals.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-camera w-5 mr-3"></i>
                    <span>Rentals</span>
                </a>
                <a href="{{ route('business.memberships.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.memberships.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-id-card w-5 mr-3"></i>
                    <span>Memberships</span>
                </a>

                <a href="{{ route('business.charity.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.charity.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-hand-holding-heart w-5 mr-3"></i>
                    <span>Go Fund</span>
                </a>

                <a href="{{ route('business.tickets.events.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.tickets.*') && !request()->routeIs('business.tickets.scanner*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-ticket-alt w-5 mr-3"></i>
                    <span>Tickets</span>
                </a>
                <a href="{{ route('business.tickets.scanner') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.tickets.scanner*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-qrcode w-5 mr-3"></i>
                    <span>QR Scanner</span>
                </a>

                <a href="{{ route('business.withdrawals.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.withdrawals.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-hand-holding-usd w-5 mr-3"></i>
                    <span>Withdrawals</span>
                </a>

                @php
                    $hasRenterAccount = \App\Models\Renter::where('email', auth('business')->user()->email)->exists();
                @endphp
                @if($hasRenterAccount)
                    <a href="{{ route('renter.dashboard') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 border-t border-gray-200 mt-4 pt-4 {{ request()->routeIs('renter.*') ? 'bg-primary/10 text-primary' : '' }}">
                        <i class="fas fa-user w-5 mr-3"></i>
                        <span>My Rentals Dashboard</span>
                    </a>
                @endif

                <a href="{{ route('business.statistics.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.statistics.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-chart-bar w-5 mr-3"></i>
                    <span>Statistics</span>
                </a>

                <a href="{{ route('business.profile.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.profile.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-building w-5 mr-3"></i>
                    <span>Business</span>
                </a>

                <a href="{{ route('business.websites.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.websites.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-globe w-5 mr-3"></i>
                    <span>Websites</span>
                </a>

                <a href="{{ route('business.keys.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.keys.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-key w-5 mr-3"></i>
                    <span>API Keys</span>
                </a>

                <a href="{{ route('business.api-documentation.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.api-documentation.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-book w-5 mr-3"></i>
                    <span>API Documentation</span>
                </a>

                <a href="{{ route('business.team.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.team.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-users w-5 mr-3"></i>
                    <span>Team</span>
                </a>

                <a href="{{ route('business.verification.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.verification.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-id-card w-5 mr-3"></i>
                    <span>Verification</span>
                </a>

                <a href="{{ route('business.notifications.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.notifications.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-bell w-5 mr-3"></i>
                    <span>Notifications</span>
                    @php
                        $unreadCount = auth('business')->user()->unreadNotifications()->count();
                    @endphp
                    @if($unreadCount > 0)
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </a>

                <a href="{{ route('business.support.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.support.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-headset w-5 mr-3"></i>
                    <span>Support</span>
                </a>

                <a href="{{ route('business.activity.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.activity.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-history w-5 mr-3"></i>
                    <span>Activity Logs</span>
                </a>

                <a href="{{ route('business.settings.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.settings.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-cog w-5 mr-3"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                        {{ substr(auth('business')->user()->name, 0, 1) }}
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth('business')->user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth('business')->user()->email }}</p>
                    </div>
                </div>
                <form action="{{ route('business.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-0">
            <!-- Top Bar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-6">
                <div class="flex items-center">
                    <button onclick="openSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700 mr-3">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-lg lg:text-xl font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h2>
                </div>
                <div class="hidden sm:flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        <i class="far fa-clock mr-2"></i>
                        <span id="current-time"></span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span class="text-sm">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span class="text-sm">{{ session('error') }}</span>
                    </div>
                @endif

                @if(session('info'))
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span class="text-sm">{{ session('info') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside text-sm space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (only on mobile) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-2 py-2 z-30">
        <div class="flex justify-around items-center">
            <a href="{{ route('business.dashboard') }}" class="flex flex-col items-center px-3 py-2 {{ request()->routeIs('business.dashboard') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-chart-line text-lg mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="{{ route('business.transactions.index') }}" class="flex flex-col items-center px-3 py-2 {{ request()->routeIs('business.transactions.*') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-exchange-alt text-lg mb-1"></i>
                <span class="text-xs">Transactions</span>
            </a>
            <a href="{{ route('business.withdrawals.index') }}" class="flex flex-col items-center px-3 py-2 {{ request()->routeIs('business.withdrawals.*') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-hand-holding-usd text-lg mb-1"></i>
                <span class="text-xs">Withdrawals</span>
            </a>
            <a href="{{ route('business.notifications.index') }}" class="flex flex-col items-center px-3 py-2 relative {{ request()->routeIs('business.notifications.*') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-bell text-lg mb-1"></i>
                <span class="text-xs">Alerts</span>
                @php
                    $unreadCount = auth('business')->user()->unreadNotifications()->count();
                @endphp
                @if($unreadCount > 0)
                    <span class="absolute top-0 right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ $unreadCount }}</span>
                @endif
            </a>
            <button onclick="openSidebar()" class="flex flex-col items-center px-3 py-2 text-gray-500">
                <i class="fas fa-bars text-lg mb-1"></i>
                <span class="text-xs">More</span>
            </button>
        </div>
    </nav>

    <script>
        // Mobile sidebar functions
        function openSidebar() {
            document.getElementById('sidebar').classList.remove('sidebar-closed');
            document.getElementById('sidebar').classList.add('sidebar-open');
            document.getElementById('mobile-overlay').classList.remove('hidden');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('sidebar-open');
            document.getElementById('sidebar').classList.add('sidebar-closed');
            document.getElementById('mobile-overlay').classList.add('hidden');
        }

        // Update time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const menuButton = event.target.closest('[onclick="openSidebar()"]');
            
            if (window.innerWidth < 1024) {
                if (!sidebar.contains(event.target) && !menuButton && !overlay.classList.contains('hidden')) {
                    closeSidebar();
                }
            }
        });
    </script>
    @stack('scripts')
    @includeIf('components.beta-badge')
</body>
</html>
