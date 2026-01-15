<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - Email Payment Gateway</title>
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
    <div class="flex h-screen overflow-hidden">
        <!-- Mobile Sidebar Overlay -->
        <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col z-50 transform transition-transform duration-300 ease-in-out lg:transform-none sidebar-closed">
            <!-- Logo -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200">
                @php
                    $adminLogo = \App\Models\Setting::get('admin_logo');
                    $adminLogoPath = $adminLogo ? storage_path('app/public/' . $adminLogo) : null;
                    $adminLogoExists = $adminLogo && $adminLogoPath && file_exists($adminLogoPath);
                    
                    // Fallback to site logo if admin logo not set
                    if (!$adminLogoExists) {
                        $logo = \App\Models\Setting::get('site_logo');
                        $logoPath = $logo ? storage_path('app/public/' . $logo) : null;
                        $logoExists = $logo && $logoPath && file_exists($logoPath);
                    } else {
                        $logoExists = false;
                    }
                @endphp
                @if($adminLogoExists)
                    <img src="{{ asset('storage/' . $adminLogo) }}" alt="Logo" class="h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <h1 class="text-xl font-bold text-primary" style="display: none;">{{ \App\Models\Setting::get('site_name', 'Payment Gateway') }}</h1>
                @elseif($logoExists)
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-10 object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <h1 class="text-xl font-bold text-primary" style="display: none;">{{ \App\Models\Setting::get('site_name', 'Payment Gateway') }}</h1>
                @else
                    <h1 class="text-xl font-bold text-primary">{{ \App\Models\Setting::get('site_name', 'Payment Gateway') }}</h1>
                @endif
                <button onclick="closeSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('admin.dashboard') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.dashboard') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-chart-line w-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('admin.processed-emails.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.processed-emails.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-inbox w-5 mr-3"></i>
                    <span>Inbox</span>
                </a>

                @if(auth('admin')->user()->canManageEmailAccounts())
                <a href="{{ route('admin.email-accounts.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.email-accounts.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-envelope w-5 mr-3"></i>
                    <span>Email Accounts</span>
                </a>
                @endif

                @if(auth('admin')->user()->canManageAccountNumbers())
                <a href="{{ route('admin.account-numbers.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.account-numbers.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-credit-card w-5 mr-3"></i>
                    <span>Account Numbers</span>
                </a>
                @endif

                <a href="{{ route('admin.businesses.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.businesses.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-building w-5 mr-3"></i>
                    <span>Businesses</span>
                </a>

                <a href="{{ route('admin.payments.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.payments.*') && !request()->routeIs('admin.payments.needs-review') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-money-bill-wave w-5 mr-3"></i>
                    <span>Payments</span>
                </a>

                <a href="{{ route('admin.payments.needs-review') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.payments.needs-review') ? 'bg-red-50 text-red-700 border-l-4 border-red-600' : '' }}">
                    <i class="fas fa-exclamation-triangle w-5 mr-3"></i>
                    <span>Needs Review</span>
                    @php
                        $needsReviewCount = \App\Models\Payment::withCount('statusChecks')
                        ->where('status', \App\Models\Payment::STATUS_PENDING)
                        ->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->having('status_checks_count', '>=', 3)
                        ->count();
                    @endphp
                    @if($needsReviewCount > 0)
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5">{{ $needsReviewCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.withdrawals.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.withdrawals.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-hand-holding-usd w-5 mr-3"></i>
                    <span>Withdrawals</span>
                </a>

                <a href="{{ route('admin.transaction-logs.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.transaction-logs.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-history w-5 mr-3"></i>
                    <span>Transaction Logs</span>
                </a>

                <a href="{{ route('admin.test-transaction.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.test-transaction.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-flask w-5 mr-3"></i>
                    <span>Test Transaction</span>
                </a>

                <a href="{{ route('admin.bank-email-templates.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.bank-email-templates.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-university w-5 mr-3"></i>
                    <span>Bank Templates</span>
                </a>

                <a href="{{ route('admin.match-attempts.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.match-attempts.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-search-dollar w-5 mr-3"></i>
                    <span>Match Logs</span>
                </a>

                @if(auth('admin')->user()->canManageSettings())
                <a href="{{ route('admin.whitelisted-emails.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.whitelisted-emails.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-shield-alt w-5 mr-3"></i>
                    <span>Whitelisted Emails</span>
                </a>
                @endif

                @if(auth('admin')->user()->canManageSettings())
                <a href="{{ route('admin.pages.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.pages.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-file-alt w-5 mr-3"></i>
                    <span>Pages</span>
                </a>
                @endif

                @if(auth('admin')->user()->canManageSupportTickets())
                <a href="{{ route('admin.support.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.support.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-comments w-5 mr-3"></i>
                    <span>Support Tickets</span>
                    @php
                        $openTickets = \App\Models\SupportTicket::where('status', \App\Models\SupportTicket::STATUS_OPEN)->count();
                    @endphp
                    @if($openTickets > 0)
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5">{{ $openTickets }}</span>
                    @endif
                </a>
                @endif

                @if(auth('admin')->user()->canManageSettings())
                <a href="{{ route('admin.settings.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.settings.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-cog w-5 mr-3"></i>
                    <span>Settings</span>
                </a>
                
                <a href="{{ route('admin.email-templates.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.email-templates.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-envelope-open-text w-5 mr-3"></i>
                    <span>Email Templates</span>
                </a>
                @endif

                @if(auth('admin')->user()->canManageAdmins())
                <a href="{{ route('admin.staff.index') }}" onclick="closeSidebar()" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.staff.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-users-cog w-5 mr-3"></i>
                    <span>Staff Management</span>
                </a>
                @endif
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                        {{ substr(auth('admin')->user()->name, 0, 1) }}
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth('admin')->user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth('admin')->user()->email }}</p>
                        <p class="text-xs text-primary font-medium capitalize">{{ str_replace('_', ' ', auth('admin')->user()->role) }}</p>
                    </div>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST">
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
            <main class="flex-1 overflow-y-auto p-4 lg:p-6 pb-20 lg:pb-6">
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
            <a href="{{ route('admin.dashboard') }}" class="flex flex-col items-center px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-chart-line text-lg mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="flex flex-col items-center px-3 py-2 {{ request()->routeIs('admin.payments.*') && !request()->routeIs('admin.payments.needs-review') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-money-bill-wave text-lg mb-1"></i>
                <span class="text-xs">Payments</span>
            </a>
            <a href="{{ route('admin.payments.needs-review') }}" class="flex flex-col items-center px-3 py-2 relative {{ request()->routeIs('admin.payments.needs-review') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-exclamation-triangle text-lg mb-1"></i>
                <span class="text-xs">Review</span>
                @php
                    $needsReviewCount = \App\Models\Payment::withCount('statusChecks')
                    ->where('status', \App\Models\Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->having('status_checks_count', '>=', 3)
                    ->count();
                @endphp
                @if($needsReviewCount > 0)
                    <span class="absolute top-0 right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ $needsReviewCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.businesses.index') }}" class="flex flex-col items-center px-3 py-2 {{ request()->routeIs('admin.businesses.*') ? 'text-primary' : 'text-gray-500' }}">
                <i class="fas fa-building text-lg mb-1"></i>
                <span class="text-xs">Businesses</span>
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
</body>
</html>
