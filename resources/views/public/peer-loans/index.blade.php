<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business loans marketplace — {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#3C50E0' } } } } }</script>
</head>
<body class="bg-gray-50">
@include('partials.nav')
<main class="max-w-5xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-900">Get a business loan</h1>
    <p class="text-gray-600 mt-2 text-sm">Peer offers from approved businesses. Sign in to your business dashboard to apply.</p>
    <div class="mt-8 space-y-4">
        @forelse($offers as $offer)
            <a href="{{ route('peer-loans.show', $offer->public_slug) }}" class="block bg-white rounded-lg border border-gray-200 p-4 hover:border-primary/40 transition">
                <div class="flex flex-wrap justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $offer->lender->name }}</p>
                        <p class="text-xs text-gray-500 mt-1">Repayment: {{ $offer->repayment_type === 'lump' ? 'One-time' : 'Split ('.ucfirst($offer->repayment_frequency ?? 'weekly').')' }} · {{ $offer->term_days }} days</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-primary">₦{{ number_format($offer->amount, 2) }}</p>
                        <p class="text-sm text-gray-600">{{ number_format($offer->interest_rate_percent, 2) }}% interest (flat term estimate)</p>
                    </div>
                </div>
            </a>
        @empty
            <p class="text-gray-600">No public loan offers at the moment.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $offers->links() }}</div>
</main>
</body>
</html>
