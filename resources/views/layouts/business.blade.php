<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Payment Gateway</title>
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
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <!-- Logo -->
            <div class="h-16 flex items-center px-6 border-b border-gray-200">
                <h1 class="text-xl font-bold text-primary">Payment Gateway</h1>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <a href="{{ route('business.dashboard') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.dashboard') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-chart-line w-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('business.transactions.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.transactions.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-exchange-alt w-5 mr-3"></i>
                    <span>Transactions</span>
                </a>

                <a href="{{ route('business.withdrawals.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.withdrawals.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-hand-holding-usd w-5 mr-3"></i>
                    <span>Withdrawals</span>
                </a>

                <a href="{{ route('business.statistics.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.statistics.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-chart-bar w-5 mr-3"></i>
                    <span>Statistics</span>
                </a>

                <a href="{{ route('business.profile.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.profile.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-building w-5 mr-3"></i>
                    <span>Business</span>
                </a>

                <a href="{{ route('business.keys.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.keys.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-key w-5 mr-3"></i>
                    <span>API Keys</span>
                </a>

                <a href="{{ route('business.team.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.team.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-users w-5 mr-3"></i>
                    <span>Team</span>
                </a>

                <a href="{{ route('business.verification.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.verification.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-id-card w-5 mr-3"></i>
                    <span>Verification</span>
                </a>

                <a href="{{ route('business.notifications.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.notifications.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-bell w-5 mr-3"></i>
                    <span>Notifications</span>
                    @php
                        $unreadCount = auth('business')->user()->unreadNotifications()->count();
                    @endphp
                    @if($unreadCount > 0)
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </a>

                <a href="{{ route('business.support.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.support.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-headset w-5 mr-3"></i>
                    <span>Support</span>
                </a>

                <a href="{{ route('business.activity.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.activity.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-history w-5 mr-3"></i>
                    <span>Activity Logs</span>
                </a>

                <a href="{{ route('business.settings.index') }}" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 {{ request()->routeIs('business.settings.*') ? 'bg-primary/10 text-primary' : '' }}">
                    <i class="fas fa-cog w-5 mr-3"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                        {{ substr(auth('business')->user()->name, 0, 1) }}
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ auth('business')->user()->name }}</p>
                        <p class="text-xs text-gray-500">{{ auth('business')->user()->email }}</p>
                    </div>
                </div>
                <form action="{{ route('business.logout') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h2>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        <i class="far fa-clock mr-2"></i>
                        <span id="current-time"></span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside">
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

    <script>
        // Update time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
        }
        updateTime();
        setInterval(updateTime, 1000);
    </script>
    @stack('scripts')
</body>
</html>
