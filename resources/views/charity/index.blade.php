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
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">GoFund & Charity</h1>
            <p class="text-gray-600">Support campaigns. Donations use the same secure payment flow.</p>
        </div>

        <form method="GET" action="{{ route('charity.index') }}" class="flex flex-wrap gap-4 items-end mb-6">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search campaigns..." class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Search</button>
        </form>

        @if($campaigns->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                <p class="text-gray-600">No campaigns yet. Check back later.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($campaigns as $campaign)
                    <a href="{{ route('charity.show', $campaign->slug) }}" class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow block">
                        @if($campaign->image)
                            <img src="{{ asset('storage/' . $campaign->image) }}" alt="{{ $campaign->title }}" class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-primary/10 flex items-center justify-center">
                                <i class="fas fa-heart text-primary text-4xl"></i>
                            </div>
                        @endif
                        <div class="p-4">
                            @if($campaign->is_featured)
                                <span class="text-xs font-medium text-primary bg-primary/10 px-2 py-0.5 rounded">Featured</span>
                            @endif
                            <h2 class="text-lg font-semibold text-gray-900 mt-2">{{ $campaign->title }}</h2>
                            <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ Str::limit(strip_tags($campaign->story), 100) }}</p>
                            <div class="mt-3 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-primary h-2 rounded-full" style="width: {{ $campaign->progress_percent }}%"></div>
                            </div>
                            <p class="text-sm text-gray-700 mt-2">{{ $campaign->currency }} {{ number_format($campaign->raised_amount, 0) }} raised of {{ number_format($campaign->goal_amount, 0) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $campaigns->withQueryString()->links() }}</div>
        @endif
    </div>

    @include('partials.footer')
</body>
</html>
