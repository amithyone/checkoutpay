<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', [
        'seoPath' => '/about-us',
        'seoOverrides' => array_filter([
            'title' => $page->meta_title ?? null,
            'description' => $page->meta_description ?? null,
        ]),
    ])
    @include('partials.tailwind-assets')
</head>
<body class="bg-white">
    @include('partials.nav')

    <div class="about-page-content">
        {!! $page->content !!}
    </div>

    @include('partials.footer')
</body>
</html>
