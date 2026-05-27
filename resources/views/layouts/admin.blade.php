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
    @include('partials.pwa-meta')
<style>
        @media (max-width: 1023px) {
            #sidebar.sidebar-closed {
                transform: translateX(-100%);
            }
            #sidebar.sidebar-open {
                transform: translateX(0);
            }
        }
        #admin-sidebar-menu.sidebar-editing .sidebar-drag-handle {
            display: inline-block !important;
        }
        #admin-sidebar-menu.sidebar-editing .sidebar-menu-link {
            cursor: default;
        }
        #admin-sidebar-menu .sortable-ghost {
            opacity: 0.45;
            background: #eef2ff;
            border-radius: 0.5rem;
        }
    </style>
    @include('partials.tailwind-assets')
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

            <!-- Navigation (order customizable per admin) -->
            <div class="flex flex-col flex-1 min-h-0">
                @include('admin.partials.sidebar-menu')
            </div>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                        {{ substr(auth('admin')->user()->name, 0, 1) }}
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth('admin')->user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth('admin')->user()->email }}</p>
                        @if(auth('admin')->user()->isSuperAdmin())
                        <a href="{{ route('admin.profile.index') }}" class="text-xs text-primary font-medium capitalize hover:underline">{{ str_replace('_', ' ', auth('admin')->user()->role) }}</a>
                        @else
                        <p class="text-xs text-primary font-medium capitalize">{{ str_replace('_', ' ', auth('admin')->user()->role) }}</p>
                        @endif
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
                    @if(auth('admin')->user()->isSuperAdmin())
                    <a href="{{ route('admin.profile.index') }}" class="flex items-center space-x-2 px-3 py-2 text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors" title="Profile Settings">
                        <i class="fas fa-user-circle text-xl"></i>
                    </a>
                    @endif
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
                    $needsReviewCount = \App\Models\Payment::query()
                    ->where('status', \App\Models\Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->has('statusChecks', '>=', 3)
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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        (function () {
            const menu = document.getElementById('admin-sidebar-menu');
            const toggleBtn = document.getElementById('sidebar-toggle-edit');
            const saveBtn = document.getElementById('sidebar-save-order');
            const resetBtn = document.getElementById('sidebar-reset-order');
            const cancelBtn = document.getElementById('sidebar-cancel-edit');
            const editActions = document.getElementById('sidebar-edit-actions');
            const editHint = document.getElementById('sidebar-edit-hint');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (!menu || !toggleBtn || typeof Sortable === 'undefined') {
                return;
            }

            let sortable = null;
            let initialOrder = [];

            function collectOrder() {
                return Array.from(menu.querySelectorAll('.admin-sidebar-item[data-menu-key]'))
                    .map(function (li) { return li.getAttribute('data-menu-key'); });
            }

            function restoreOrder(order) {
                const nodes = {};
                menu.querySelectorAll('.admin-sidebar-item').forEach(function (li) {
                    nodes[li.getAttribute('data-menu-key')] = li;
                });
                order.forEach(function (key) {
                    if (nodes[key]) {
                        menu.appendChild(nodes[key]);
                    }
                });
            }

            function enterEditMode() {
                initialOrder = collectOrder();
                menu.classList.add('sidebar-editing');
                editActions.classList.remove('hidden');
                editActions.classList.add('flex');
                editHint.classList.remove('hidden');
                toggleBtn.classList.add('hidden');
                sortable = Sortable.create(menu, {
                    animation: 150,
                    handle: '.sidebar-drag-handle',
                    draggable: '.admin-sidebar-item',
                    ghostClass: 'sortable-ghost',
                });
            }

            function exitEditMode() {
                menu.classList.remove('sidebar-editing');
                editActions.classList.add('hidden');
                editActions.classList.remove('flex');
                editHint.classList.add('hidden');
                toggleBtn.classList.remove('hidden');
                if (sortable) {
                    sortable.destroy();
                    sortable = null;
                }
            }

            toggleBtn.addEventListener('click', enterEditMode);

            cancelBtn?.addEventListener('click', function () {
                restoreOrder(initialOrder);
                exitEditMode();
            });

            saveBtn?.addEventListener('click', function () {
                saveBtn.disabled = true;
                fetch('{{ route('admin.sidebar-menu-order.update') }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ order: collectOrder() }),
                })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('Save failed');
                        }
                        return res.json();
                    })
                    .then(function () {
                        exitEditMode();
                        saveBtn.disabled = false;
                    })
                    .catch(function () {
                        alert('Could not save menu order. Please try again.');
                        saveBtn.disabled = false;
                    });
            });

            resetBtn?.addEventListener('click', function () {
                if (!confirm('Reset sidebar menu to the default order?')) {
                    return;
                }
                resetBtn.disabled = true;
                fetch('{{ route('admin.sidebar-menu-order.reset') }}', {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('Reset failed');
                        }
                        window.location.reload();
                    })
                    .catch(function () {
                        alert('Could not reset menu order.');
                        resetBtn.disabled = false;
                    });
            });
        })();
    </script>
    @include('partials.admin-support-sound')
    @stack('scripts')
    @include('partials.pwa-sw')
    @includeIf('components.beta-badge')
</body>
</html>
