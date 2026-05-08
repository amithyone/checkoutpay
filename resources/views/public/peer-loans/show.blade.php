<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan offer — {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } }</script>
</head>
<body class="bg-gray-50">
@include('partials.nav')
<main class="max-w-lg mx-auto px-4 py-10">
    <a href="{{ route('peer-loans.index') }}" class="text-sm text-primary hover:underline">&larr; All offers</a>
    <div class="mt-4 bg-white rounded-lg border border-gray-200 p-6 space-y-3">
        <h1 class="text-xl font-bold text-gray-900">₦{{ number_format($offer->amount, 2) }}</h1>
        <p class="text-sm text-gray-600">Lender: <span class="font-medium text-gray-900">{{ $offer->lender->name }}</span></p>
        <p class="text-sm text-gray-600">Interest rate: <span class="font-medium">{{ number_format($offer->interest_rate_percent, 2) }}%</span> (flat over term)</p>
        <p class="text-sm text-gray-600">Term: <span class="font-medium">{{ $offer->term_days }} days</span></p>
        <p class="text-sm text-gray-600">Repayment: <span class="font-medium">{{ $offer->repayment_type === 'lump' ? 'One-time at end' : 'Split installments ('.ucfirst($offer->repayment_frequency ?? 'weekly').')' }}</span></p>
    </div>

    @if(session('error'))
        <div class="mt-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">{{ session('error') }}</div>
    @endif

    @auth('business')
        @if(auth('business')->user()->peer_lending_borrow_eligible)
            <form method="POST" action="{{ route('business.peer-loans.apply', $offer->public_slug) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700">Message to lender (optional)</label>
                    <textarea name="borrower_message" rows="3" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('borrower_message') }}</textarea>
                </div>
                <button type="submit" class="w-full py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90">Apply for this loan</button>
            </form>
        @else
            <p class="mt-6 text-sm text-amber-700">Your business is not approved to borrow yet. Contact support or check your dashboard.</p>
        @endif
    @else
        <p class="mt-6 text-sm text-gray-600">
            <a href="{{ route('business.login') }}" class="text-primary font-medium hover:underline">Sign in to your business account</a> to apply.
        </p>
    @endauth
</main>
</body>
</html>
