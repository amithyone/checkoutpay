<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->meta_title ?? $page->title }} - CheckoutPay</title>
    @if($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if(\App\Models\Setting::get('site_favicon'))
        <link rel="icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#3C50E0' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    @include('partials.nav')

    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <article class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4 sm:mb-6">{{ $page->title }}</h1>
            
            <div class="prose prose-sm sm:prose-base max-w-none">
                {!! $page->content !!}
            </div>
        </article>
    </div>

    @include('partials.footer')
</body>
</html>
