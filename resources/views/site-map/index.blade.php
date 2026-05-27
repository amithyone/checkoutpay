<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', ['seoPath' => '/site-map', 'seo' => $seo])
@include('partials.tailwind-assets')
</head>
<body class="bg-white">
    @include('partials.nav')
    <section class="py-12 sm:py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Site map</h1>

            <h2 class="text-lg font-semibold text-gray-900 mb-3">Pages</h2>
            <ul class="grid sm:grid-cols-2 gap-2 text-sm mb-10">
                @foreach($paths as $path)
                    <li>
                        <a href="{{ url($path) }}" class="text-primary hover:underline">
                            {{ $labels[$path] ?? $path }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <h2 class="text-lg font-semibold text-gray-900 mb-3">FAQ categories</h2>
            <ul class="grid sm:grid-cols-2 gap-2 text-sm">
                @foreach($faqCategories as $slug => $meta)
                    <li>
                        <a href="{{ route('faqs.index') }}#{{ $slug }}" class="text-primary hover:underline">{{ $meta['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    @include('partials.footer')
</body>
</html>
