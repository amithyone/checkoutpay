<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoFund & Charity - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script> tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } } </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .group:hover .charity-card-btn { background-color: var(--charity-accent) !important; color: white !important; }
    </style>
</head>
<body class="bg-gray-50">
    @php $charityColor = \App\Models\Setting::get('rentals_accent_color', '#000000'); @endphp
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 pb-24 sm:pb-8">
        <form method="GET" action="{{ route('charity.index') }}" class="space-y-4 mb-6" id="charity-filter-form">
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-700 pointer-events-none">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search campaigns..."
                    class="w-full pl-11 pr-12 py-3 rounded-2xl border-0 bg-gray-200 text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-gray-400 shadow-sm">
                <button type="button" id="open-filter-modal" class="absolute right-3 top-1/2 -translate-y-1/2 p-2 text-gray-600 hover:text-gray-900 rounded-lg transition" title="Filter">
                    <i class="fas fa-filter"></i>
                </button>
            </div>

            <input type="hidden" name="sort" id="form-sort" value="{{ request('sort', 'featured') }}">

            <div class="hidden sm:flex flex-wrap items-center gap-3" id="desktop-filters">
                <select id="desktop-sort" class="flex-1 min-w-[140px] px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 focus:ring-2 focus:ring-gray-800">
                    <option value="featured" {{ request('sort', 'featured') == 'featured' ? 'selected' : '' }}>Featured</option>
                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                    <option value="most_funded" {{ request('sort') == 'most_funded' ? 'selected' : '' }}>Most funded</option>
                    <option value="ending_soon" {{ request('sort') == 'ending_soon' ? 'selected' : '' }}>Ending soon</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-white font-medium transition shadow-sm" style="background-color: {{ $charityColor }};">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width: thin;">
                <a href="{{ route('charity.index', array_filter(['search' => request('search')])) }}"
                    class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ !request('sort') || request('sort') == 'featured' ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                    @if(!request('sort') || request('sort') == 'featured') style="background-color: {{ $charityColor }};" @endif>All</a>
                <a href="{{ route('charity.index', array_filter(['sort' => 'featured', 'search' => request('search')])) }}"
                    class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ request('sort') == 'featured' ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                    @if(request('sort') == 'featured') style="background-color: {{ $charityColor }};" @endif>Featured</a>
                <a href="{{ route('charity.index', array_filter(['sort' => 'newest', 'search' => request('search')])) }}"
                    class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ request('sort') == 'newest' ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                    @if(request('sort') == 'newest') style="background-color: {{ $charityColor }};" @endif>Newest</a>
                <a href="{{ route('charity.index', array_filter(['sort' => 'most_funded', 'search' => request('search')])) }}"
                    class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ request('sort') == 'most_funded' ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                    @if(request('sort') == 'most_funded') style="background-color: {{ $charityColor }};" @endif>Most funded</a>
                <a href="{{ route('charity.index', array_filter(['sort' => 'ending_soon', 'search' => request('search')])) }}"
                    class="flex-shrink-0 px-4 py-2 rounded-2xl text-sm font-medium transition {{ request('sort') == 'ending_soon' ? 'text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                    @if(request('sort') == 'ending_soon') style="background-color: {{ $charityColor }};" @endif>Ending soon</a>
            </div>
        </form>

        <div id="filter-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
            <div class="absolute inset-0 bg-black/50" id="filter-modal-backdrop"></div>
            <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl shadow-xl max-h-[85vh] overflow-hidden flex flex-col animate-slide-up">
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Sort</h3>
                    <button type="button" id="close-filter-modal" class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"><i class="fas fa-times text-xl"></i></button>
                </div>
                <div class="p-4 space-y-2">
                    @foreach(['featured' => ['Featured', 'fa-star'], 'newest' => ['Newest', 'fa-clock'], 'most_funded' => ['Most funded', 'fa-coins'], 'ending_soon' => ['Ending soon', 'fa-calendar']] as $value => $label)
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer hover:bg-gray-50 {{ request('sort', 'featured') == $value ? 'border-2' : 'border-gray-200' }}"
                            @if(request('sort', 'featured') == $value) style="border-color: {{ $charityColor }}; background-color: {{ $charityColor }}15;" @endif>
                            <input type="radio" name="modal-sort" value="{{ $value }}" {{ request('sort', 'featured') == $value ? 'checked' : '' }} class="focus:ring-gray-700">
                            <i class="fas {{ $label[1] }} text-gray-500 w-5"></i>
                            <span class="text-gray-900">{{ $label[0] }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="p-4 border-t border-gray-200 flex gap-3">
                    <button type="button" id="filter-modal-clear" class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">Clear</button>
                    <button type="button" id="filter-modal-apply" class="flex-1 py-3 rounded-xl text-white font-medium" style="background-color: {{ $charityColor }};">Show results</button>
                </div>
            </div>
        </div>
        <style>@keyframes slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } } .animate-slide-up { animation: slide-up 0.25s ease-out; }</style>

        @if($campaigns->isEmpty())
            <div class="rounded-2xl bg-white border border-gray-200 p-10 sm:p-12 text-center">
                <i class="fas fa-heart text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-600 font-medium">No campaigns found</p>
                <p class="text-sm text-gray-500 mt-1">Try changing your search or filters.</p>
                <a href="{{ route('charity.index') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl text-white text-sm font-medium" style="background-color: {{ $charityColor }};">Clear filters</a>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-6">
                @foreach($campaigns as $campaign)
                    <a href="{{ route('charity.show', $campaign->slug) }}" class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-primary/20 transition-all duration-200 flex flex-col">
                        @if($campaign->image)
                            <img src="{{ asset('storage/' . $campaign->image) }}" alt="{{ $campaign->title }}" class="w-full h-36 sm:h-48 object-cover group-hover:scale-[1.02] transition-transform duration-200">
                        @else
                            <div class="w-full h-36 sm:h-48 flex items-center justify-center" style="background-color: {{ $charityColor }}20;">
                                <i class="fas fa-heart text-3xl sm:text-4xl" style="color: {{ $charityColor }};"></i>
                            </div>
                        @endif
                        <div class="p-3 sm:p-4 flex-1 flex flex-col">
                            @if($campaign->is_featured)
                                <span class="text-xs font-medium px-2 py-0.5 rounded" style="background-color: {{ $charityColor }}22; color: {{ $charityColor }};">Featured</span>
                            @endif
                            <h3 class="font-semibold text-gray-900 mt-0.5 sm:mt-1 group-hover:text-primary transition text-sm sm:text-base line-clamp-2">{{ $campaign->title }}</h3>
                            <p class="text-gray-600 text-xs sm:text-sm mt-1 line-clamp-2 hidden sm:block">{{ Str::limit(strip_tags($campaign->story), 90) }}</p>
                            <div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-gray-100">
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full transition-all" style="width: {{ $campaign->progress_percent }}%; background-color: {{ $charityColor }};"></div>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">{{ $campaign->currency }} {{ number_format($campaign->raised_amount, 0) }} / {{ number_format($campaign->goal_amount, 0) }}</p>
                            </div>
                            <span class="charity-card-btn mt-2 sm:mt-3 inline-flex items-center justify-center gap-1.5 w-full py-2 rounded-xl text-xs sm:text-sm font-medium transition"
                                style="--charity-accent: {{ $charityColor }}; background-color: {{ $charityColor }}22; color: {{ $charityColor }};">
                                <i class="fas fa-heart sm:hidden"></i>
                                <span class="sm:hidden">Support</span>
                                <span class="hidden sm:inline">View & support</span>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $campaigns->withQueryString()->links() }}</div>
        @endif
    </div>

    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40 sm:hidden safe-area-pb">
        <div class="flex justify-around items-center h-16">
            <a href="{{ route('charity.index') }}" class="flex flex-col items-center justify-center flex-1 py-2 {{ request()->routeIs('charity.index') ? 'text-primary' : 'text-gray-600 hover:text-primary' }} transition"><i class="fas fa-home text-lg mb-0.5"></i><span class="text-xs">Home</span></a>
            <a href="{{ auth()->check() ? route('user.purchases') : route('account.login') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition"><i class="fas fa-shopping-bag text-lg mb-0.5"></i><span class="text-xs">Purchases</span></a>
            <a href="{{ route('support.index') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition"><i class="fas fa-headset text-lg mb-0.5"></i><span class="text-xs">Support</span></a>
            <a href="{{ auth()->check() ? route('user.profile') : route('account.login') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition"><i class="fas fa-user text-lg mb-0.5"></i><span class="text-xs">Profile</span></a>
            <a href="{{ auth()->check() ? route('user.dashboard') : route('account.login') }}" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:text-primary transition"><i class="fas fa-bars text-lg mb-0.5"></i><span class="text-xs">More</span></a>
        </div>
    </nav>

    <script>
        (function() {
            var form = document.getElementById('charity-filter-form');
            var modal = document.getElementById('filter-modal');
            var openBtn = document.getElementById('open-filter-modal');
            var closeBtn = document.getElementById('close-filter-modal');
            var backdrop = document.getElementById('filter-modal-backdrop');
            var applyBtn = document.getElementById('filter-modal-apply');
            var clearBtn = document.getElementById('filter-modal-clear');
            var formSort = document.getElementById('form-sort');
            var desktopSort = document.getElementById('desktop-sort');
            function openModal() { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; var r = document.querySelector('input[name="modal-sort"][value="' + formSort.value + '"]'); if (r) r.checked = true; }
            function closeModal() { modal.classList.add('hidden'); document.body.style.overflow = ''; }
            function applyModalAndSubmit() { var r = document.querySelector('input[name="modal-sort"]:checked'); formSort.value = r ? r.value : 'featured'; if (desktopSort) desktopSort.value = formSort.value; closeModal(); form.requestSubmit(); }
            function clearFilters() { formSort.value = 'featured'; var r = document.querySelector('input[name="modal-sort"][value="featured"]'); if (r) r.checked = true; if (desktopSort) desktopSort.value = 'featured'; closeModal(); form.requestSubmit(); }
            if (openBtn) openBtn.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);
            if (applyBtn) applyBtn.addEventListener('click', applyModalAndSubmit);
            if (clearBtn) clearBtn.addEventListener('click', clearFilters);
            if (desktopSort) desktopSort.addEventListener('change', function() { formSort.value = this.value; });
            form.addEventListener('submit', function() { if (desktopSort && window.getComputedStyle(desktopSort).display !== 'none') formSort.value = desktopSort.value; });
        })();
    </script>
</body>
</html>
