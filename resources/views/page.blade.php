<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $seoPath = '/'.ltrim((string) ($page->slug ?? ''), '/');
        $seo = \App\Support\Seo::forPath($seoPath);
        if ($page->meta_title ?? null) {
            $seo['title'] = $page->meta_title;
        }
        if ($page->meta_description ?? null) {
            $seo['description'] = $page->meta_description;
        }
        $legalSlugs = ['terms-and-conditions', 'privacy-policy', 'security', 'fraud-awareness', 'esg-policy'];
        $isLegalPage = in_array($page->slug ?? '', $legalSlugs, true);
    @endphp
    @include('partials.marketing-head', ['seoPath' => $seoPath, 'seo' => $seo])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .legal-document h2 { scroll-margin-top: 5.5rem; }
        .legal-document h3 { margin-top: 1.25rem; margin-bottom: 0.5rem; }
        .legal-document a { text-decoration-thickness: 1px; }
        .legal-toc a:hover { text-decoration: underline; }
        @media (min-width: 1024px) {
            .legal-toc { position: sticky; top: 5rem; max-height: calc(100vh - 6rem); overflow-y: auto; }
        }
    </style>
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
<body class="bg-white">
    @include('partials.nav')
    <section class="py-12 sm:py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-8">{{ $page->title }}</h1>
            <div class="legal-document text-gray-700 prose prose-gray max-w-none prose-headings:text-gray-900 prose-a:text-primary">
                {!! $page->content !!}
            </div>
        </div>
    </section>
    @if(($page->slug ?? '') === 'security')
        @include('partials.faq-section', ['category' => 'security-compliance', 'title' => 'Security FAQs'])
    @endif
    @include('partials.footer')
</body>
</html>
