<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? 'Invoices - ' . \App\Models\Setting::get('site_name', 'CheckoutPay') }}</title>
    @include('partials.tailwind-assets')
    @if($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif</head>
<body class="bg-white">
    @include('partials.nav')

    <!-- Render editable content -->
    <div class="invoices-page-content">
        {!! $page->content !!}
    </div>

    @include('partials.footer')
</body>
</html>
