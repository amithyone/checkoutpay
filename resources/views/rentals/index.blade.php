<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rentals - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                        rental: { DEFAULT: '#BE845D', light: '#E8D5C4' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .group:hover .rentals-card-btn { background-color: var(--rentals-accent) !important; color: white !important; }
    </style>
</head>
<body class="bg-gray-50">
    @php $rentalsColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 pb-24 sm:pb-8">
        {{-- Search + Filter (reference: warm brown bar with search + filter icon) --}}
        <form method="GET" action="{{ route('rentals.index') }}" class="space-y-4 mb-6" id="rentals-filter-form">
            {{-- Search bar --}}
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-700 pointer-events-none">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search rentals, items or businesses..."
                    class="w-full pl-11 pr-12 py-3 rounded-2xl border-0 bg-gray-200 text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-gray-400 shadow-sm">
                <button type="button" id="open-filter-modal" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 text-gray-600 hover:text-gray-900 rounded-lg transition" title="Filter">
                    <i class="fas fa-filter"></i>
                </button>
            </div>

            {{-- Hidden filter fields (synced from modal or desktop row) --}}
            <input type="hidden" name="category" id="form-category" value="{{ request('category') }}">
            <input type="hidden" name="city" id="form-city" value="{{ request('city') }}">
            <input type="hidden" name="sort" id="form-sort" value="{{ request('sort', 'featured') }}">

            {{-- Desktop filter row --}}
            <div class="hidden sm:flex flex-wrap items-center gap-3" id="desktop-filters">
                <select id="desktop-category" class="flex-1 min-w-[120px] px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 focus:ring-2 focus:ring-gray-800">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->slug }}" {{ request('category') == $category->slug ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select id="desktop-city" class="flex-1 min-w-[120px] px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 focus:ring-2 focus:ring-gray-800">
                    <option value="">All cities</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>{{ $city }}</option>
                    @endforeach
                </select>
                <select id="desktop-sort" class="flex-1 min-w-[120px] px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 focus:ring-2 focus:ring-gray-800">
                    <option value="featured" {{ request('sort', 'featured') == 'featured' ? 'selected' : '' }}>Featured</option>
                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                    <option value="most_rented" {{ request('sort') == 'most_rented' ? 'selected' : '' }}>Most rented</option>
                    <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-white font-medium transition shadow-sm" style="background-color: {{ $rentalsColor }};">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>

            {{-- Category pills (no section title) --}}
            @if($categories->count() > 0)
                <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width: thin;">
                    <a href="{{ route('rentals.index', array_filter(['search' => request('search'), 'city' => request('city'), 'sort' => request('sort')])) }}"
                        class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ !request('category') ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                        @if(!request('category')) style="background-color: {{ $rentalsColor }};" @endif>
                        All
                    </a>
                    @foreach($categories as $category)
                        <a href="{{ route('rentals.index', array_filter(['category' => $category->slug, 'search' => request('search'), 'city' => request('city'), 'sort' => request('sort')])) }}"
                            class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ request('category') == $category->slug ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                            @if(request('category') == $category->slug) style="background-color: {{ $rentalsColor }};" @endif>
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </form>

        {{-- Filter modal (opens from search bar filter button) --}}
        <div id="filter-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
            <div class="absolute inset-0 bg-black/50" id="filter-modal-backdrop"></div>
            <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl shadow-xl max-h-[85vh] overflow-hidden flex flex-col animate-slide-up">
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Filter & sort</h3>
                    <button type="button" id="close-filter-modal" class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-4 space-y-5 overflow-y-auto flex-1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select id="modal-category" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 focus:ring-2 focus:ring-gray-700 focus:border-gray-700">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->slug }}" {{ request('category') == $category->slug ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <select id="modal-city" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 focus:ring-2 focus:ring-gray-700 focus:border-gray-700">
                            <option value="">All cities</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort by</label>
                        <div class="space-y-2">
                            @foreach([
                                'featured' => ['Featured', 'fa-star'],
                                'newest' => ['Newest', 'fa-clock'],
                                'most_rented' => ['Most rented', 'fa-fire'],
                                'price_low' => ['Price: Low to High', 'fa-arrow-up'],
                                'price_high' => ['Price: High to Low', 'fa-arrow-down'],
                            ] as $value => $label)
                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer hover:bg-gray-50 {{ request('sort', 'featured') == $value ? 'border-2' : 'border-gray-200' }}"
                                    @if(request('sort', 'featured') == $value) style="border-color: {{ $rentalsColor }}; background-color: {{ $rentalsColor }}15;" @endif>
                                    <input type="radio" name="modal-sort" value="{{ $value }}" {{ request('sort', 'featured') == $value ? 'checked' : '' }} class="focus:ring-gray-700">
                                    <i class="fas {{ $label[1] }} text-gray-500 w-5"></i>
                                    <span class="text-gray-900">{{ $label[0] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-200 flex gap-3">
                    <button type="button" id="filter-modal-clear" class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">Clear</button>
                    <button type="button" id="filter-modal-apply" class="flex-1 py-3 rounded-xl text-white font-medium" style="background-color: {{ $rentalsColor }};">Show results</button>
                </div>
            </div>
        </div>
        <style>@keyframes slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } } .animate-slide-up { animation: slide-up 0.25s ease-out; }</style>

        {{-- Items grid: 2 cols mobile, 2 sm, 3 lg --}}
        @if($items->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-6">
                @foreach($items as $item)
                    <a href="{{ route('rentals.show', $item->slug) }}" class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-primary/20 transition-all duration-200 flex flex-col">
                        @if($item->images && count($item->images) > 0)
                            <img src="{{ asset('storage/' . $item->images[0]) }}" alt="{{ $item->name }}" class="w-full h-36 sm:h-48 object-cover group-hover:scale-[1.02] transition-transform duration-200">
                        @else
                            <div class="w-full h-36 sm:h-48 bg-gray-100 flex items-center justify-center">
                                <i class="fas fa-image text-gray-300 text-3xl sm:text-4xl"></i>
                            </div>
                        @endif
                        <div class="p-3 sm:p-4 flex-1 flex flex-col">
                            @if($item->category)
                                <span class="text-xs font-medium text-primary">{{ $item->category->name }}</span>
                            @endif
                            <h3 class="font-semibold text-gray-900 mt-0.5 sm:mt-1 group-hover:text-primary transition text-sm sm:text-base line-clamp-2">{{ $item->name }}</h3>
                            <p class="text-gray-600 text-xs sm:text-sm mt-1 line-clamp-2 hidden sm:block">{{ Str::limit($item->description, 90) }}</p>
                            <div class="flex justify-between items-center mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-gray-100 gap-1">
                                <span class="text-primary font-bold text-sm sm:text-base">₦{{ number_format($item->daily_rate, 0) }}<span class="text-gray-500 font-normal text-xs sm:text-sm">/day</span></span>
                                @if($item->city)
                                    <span class="text-xs text-gray-500 flex items-center gap-0.5 sm:gap-1 truncate max-w-[50%]"><i class="fas fa-map-marker-alt flex-shrink-0"></i> <span class="truncate">{{ $item->city }}</span></span>
                                @endif
                            </div>
                            <span class="rentals-card-btn mt-2 sm:mt-3 inline-flex items-center justify-center gap-1.5 w-full py-2 rounded-xl text-xs sm:text-sm font-medium transition"
                                style="--rentals-accent: {{ $rentalsColor }}; background-color: {{ $rentalsColor }}22; color: {{ $rentalsColor }};">
                                <i class="fas fa-cart-plus sm:hidden"></i>
                                <span class="sm:hidden">Add to cart</span>
                                <span class="hidden sm:inline">View details</span>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $items->withQueryString()->links() }}
            </div>
        @else
            <div class="rounded-2xl bg-white border border-gray-200 p-10 sm:p-12 text-center">
                <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-600 font-medium">No rental items found</p>
                <p class="text-sm text-gray-500 mt-1">Try changing your search or filters.</p>
                <a href="{{ route('rentals.index') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl text-white text-sm font-medium" style="background-color: {{ $rentalsColor }};">Clear filters</a>
            </div>
        @endif
    </div>

    {{-- Bottom nav (reference: Home, Purchases, Support, Profile, More) --}}
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40 sm:hidden safe-area-pb">
        <div class="flex justify-around items-center h-16">
            <a href="{{ route('rentals.index') }}" class="flex flex-col items-center justify-center flex-1 py-2 {{ request()->routeIs('rentals.index') ? 'text-primary' : 'text-gray-600 hover:text-primary' }} transition">
                <i class="fas fa-home text-lg mb-0.5"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="{{ auth()->check() ? route('user.purchases') : route('account.login') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition {{ request()->routeIs('user.purchases') ? 'text-primary' : '' }}">
                <i class="fas fa-shopping-bag text-lg mb-0.5"></i>
                <span class="text-xs">Purchases</span>
            </a>
            <a href="{{ route('support.index') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition {{ request()->routeIs('support.*') ? 'text-primary' : '' }}">
                <i class="fas fa-headset text-lg mb-0.5"></i>
                <span class="text-xs">Support</span>
            </a>
            <a href="{{ auth()->check() ? route('user.profile') : route('account.login') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition {{ request()->routeIs('user.profile') ? 'text-primary' : '' }}">
                <i class="fas fa-user text-lg mb-0.5"></i>
                <span class="text-xs">Profile</span>
            </a>
            <a href="{{ auth()->check() ? route('user.dashboard') : route('account.login') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition {{ request()->routeIs('user.dashboard') ? 'text-primary' : '' }}">
                <i class="fas fa-bars text-lg mb-0.5"></i>
                <span class="text-xs">More</span>
            </a>
        </div>
    </nav>

    {{-- Floating cart (above bottom nav on mobile) --}}
    @php $cartCount = count(session('rental_cart', [])); @endphp
    <a href="{{ route('rentals.checkout') }}" class="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 text-white rounded-full p-4 shadow-lg transition-all z-50 group" style="background-color: {{ $rentalsColor }};">
        <i class="fas fa-shopping-cart text-xl"></i>
        @if($cartCount > 0)
            <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center">{{ $cartCount }}</span>
        @else
            <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center hidden">0</span>
        @endif
        <span class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-sm px-3 py-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">View Cart</span>
    </a>

    <div id="toast-container" class="fixed top-20 right-4 z-50 space-y-2"></div>

    {{-- Filter modal script --}}
    <script>
        (function() {
            var form = document.getElementById('rentals-filter-form');
            var modal = document.getElementById('filter-modal');
            var openBtn = document.getElementById('open-filter-modal');
            var closeBtn = document.getElementById('close-filter-modal');
            var backdrop = document.getElementById('filter-modal-backdrop');
            var applyBtn = document.getElementById('filter-modal-apply');
            var clearBtn = document.getElementById('filter-modal-clear');

            var formCategory = document.getElementById('form-category');
            var formCity = document.getElementById('form-city');
            var formSort = document.getElementById('form-sort');
            var desktopCategory = document.getElementById('desktop-category');
            var desktopCity = document.getElementById('desktop-city');
            var desktopSort = document.getElementById('desktop-sort');

            function openModal() {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                document.getElementById('modal-category').value = formCategory.value;
                document.getElementById('modal-city').value = formCity.value;
                var sortRadio = document.querySelector('input[name="modal-sort"][value="' + formSort.value + '"]');
                if (sortRadio) sortRadio.checked = true;
            }
            function closeModal() {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
            function applyModalAndSubmit() {
                formCategory.value = document.getElementById('modal-category').value;
                formCity.value = document.getElementById('modal-city').value;
                var sortChecked = document.querySelector('input[name="modal-sort"]:checked');
                formSort.value = sortChecked ? sortChecked.value : 'featured';
                if (desktopCategory) desktopCategory.value = formCategory.value;
                if (desktopCity) desktopCity.value = formCity.value;
                if (desktopSort) desktopSort.value = formSort.value;
                closeModal();
                form.requestSubmit();
            }
            function clearFilters() {
                formCategory.value = '';
                formCity.value = '';
                formSort.value = 'featured';
                document.getElementById('modal-category').value = '';
                document.getElementById('modal-city').value = '';
                var featuredRadio = document.querySelector('input[name="modal-sort"][value="featured"]');
                if (featuredRadio) featuredRadio.checked = true;
                if (desktopCategory) desktopCategory.value = '';
                if (desktopCity) desktopCity.value = '';
                if (desktopSort) desktopSort.value = 'featured';
                closeModal();
                form.requestSubmit();
            }

            if (openBtn) openBtn.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);
            if (applyBtn) applyBtn.addEventListener('click', applyModalAndSubmit);
            if (clearBtn) clearBtn.addEventListener('click', clearFilters);

            if (desktopCategory) desktopCategory.addEventListener('change', function() { formCategory.value = this.value; });
            if (desktopCity) desktopCity.addEventListener('change', function() { formCity.value = this.value; });
            if (desktopSort) desktopSort.addEventListener('change', function() { formSort.value = this.value; });

            form.addEventListener('submit', function() {
                if (window.getComputedStyle(desktopCategory).display !== 'none') {
                    formCategory.value = desktopCategory.value;
                    formCity.value = desktopCity.value;
                    formSort.value = desktopSort.value;
                }
            });
        })();
    </script>

    @if(session('success') || session('error'))
    <script>
        (function(){
            const container = document.getElementById('toast-container');
            const msg = @json(session('success') ?? session('error'));
            const type = @json(session('success') ? 'success' : 'error');
            const toast = document.createElement('div');
            toast.className = (type === 'success' ? 'bg-green-500' : 'bg-red-500') + ' text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-3';
            toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span>' + msg + '</span>';
            container.appendChild(toast);
            setTimeout(function(){ toast.remove(); }, 3000);
        })();
    </script>
    @endif
</body>
</html>
