<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Link Expired</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } }</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
@include('partials.nav')
<div class="max-w-lg mx-auto px-4 py-12 text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-full mb-6">
        <i class="fas fa-clock text-amber-600 text-2xl"></i>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Link Expired</h1>
    <p class="text-gray-600 mb-6">This payment link for rental <strong>{{ $rental->rental_number }}</strong> has expired. Please contact {{ $rental->business->name }}@if($rental->business_phone) at {{ $rental->business_phone }}@endif for a new payment link.</p>
    <a href="{{ route('rentals.index') }}" class="inline-flex items-center px-5 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to Rentals
    </a>
</div>
</body>
</html>
