<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->title }} - {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script> tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } } </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('charity.index') }}" class="text-primary hover:underline text-sm mb-6 inline-block">← Back to campaigns</a>

        <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
            @if($campaign->image)
                <img src="{{ asset('storage/' . $campaign->image) }}" alt="{{ $campaign->title }}" class="w-full h-64 sm:h-80 object-cover">
            @else
                <div class="w-full h-64 bg-primary/10 flex items-center justify-center">
                    <i class="fas fa-heart text-primary text-6xl"></i>
                </div>
            @endif
            <div class="p-6 sm:p-8">
                @if($campaign->is_featured)
                    <span class="text-xs font-medium text-primary bg-primary/10 px-2 py-1 rounded">Featured</span>
                @endif
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2">{{ $campaign->title }}</h1>
                @if($campaign->business)
                    <p class="text-gray-600 mt-1">By {{ $campaign->business->name }}</p>
                @endif
                <div class="mt-4 w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-primary h-3 rounded-full" style="width: {{ $campaign->progress_percent }}%"></div>
                </div>
                <p class="text-gray-700 mt-2">{{ $campaign->currency }} {{ number_format($campaign->raised_amount, 0) }} raised of {{ number_format($campaign->goal_amount, 0) }}</p>
                @if($campaign->end_date)
                    <p class="text-sm text-gray-500 mt-1">Ends {{ $campaign->end_date->format('M d, Y') }}</p>
                @endif
                <div class="mt-6 prose prose-gray max-w-none">
                    {!! nl2br(e($campaign->story)) !!}
                </div>
                <p class="mt-6 text-sm text-gray-500">Donations can be enabled via the same payment gateway. Contact the campaign owner or site admin for payment details.</p>
            </div>
        </div>
    </div>

    @include('partials.footer')
</body>
</html>
