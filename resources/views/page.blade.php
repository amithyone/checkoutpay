<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $seoPath = \App\Support\Seo::publicPathForPageSlug((string) ($page->slug ?? ''));
        $seo = \App\Support\Seo::forPath($seoPath);
        if ($page->meta_title ?? null) {
            $seo['title'] = $page->meta_title;
        }
        if ($page->meta_description ?? null) {
            $seo['description'] = $page->meta_description;
        }
        $legalSlugs = ['terms-and-conditions', 'privacy-policy', 'security', 'fraud-awareness', 'esg-policy'];
        $isLegalPage = in_array($page->slug ?? '', $legalSlugs, true);

        $rawContent = (string) ($page->content ?? '');
        $legalToc = '';
        $legalBody = $rawContent;
        if ($isLegalPage && preg_match('/<nav\s+class="legal-toc[^"]*"[^>]*>[\s\S]*?<\/nav>/i', $rawContent, $tocMatch)) {
            $legalToc = $tocMatch[0];
            $legalBody = preg_replace('/<nav\s+class="legal-toc[^"]*"[^>]*>[\s\S]*?<\/nav>/i', '', $rawContent, 1) ?? $rawContent;
            $legalBody = trim($legalBody);
        }
    @endphp
    @include('partials.marketing-head', ['seoPath' => $seoPath, 'seo' => $seo])
<style>
        .legal-document h2 { scroll-margin-top: 5.5rem; }
        .legal-document h3 { margin-top: 1.25rem; margin-bottom: 0.5rem; }
        .legal-document a { text-decoration-thickness: 1px; }
        .legal-sidebar .legal-toc {
            margin-bottom: 0;
        }
        .legal-sidebar .legal-toc ol {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .legal-sidebar .legal-toc li {
            margin-bottom: 0.35rem;
            line-height: 1.4;
        }
        .legal-sidebar .legal-toc a {
            display: block;
            padding: 0.2rem 0;
            font-size: 0.8125rem;
            color: #4b5563;
        }
        .legal-sidebar .legal-toc a:hover {
            color: #3C50E0;
            text-decoration: underline;
        }
        @media (min-width: 1024px) {
            .legal-sidebar {
                position: sticky;
                top: 5.5rem;
                align-self: start;
                max-height: calc(100vh - 6.5rem);
                overflow-y: auto;
            }
        }
    </style>
    @include('partials.tailwind-assets')
</head>
<body class="bg-white">
    @include('partials.nav')
    <section class="py-12 sm:py-16">
        <div class="{{ $legalToc !== '' ? 'max-w-7xl' : 'max-w-4xl' }} mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-6 lg:mb-8">{{ $page->title }}</h1>

            @if($legalToc !== '')
                <div class="lg:grid lg:grid-cols-12 lg:gap-x-10 lg:gap-y-0 items-start">
                    <aside class="legal-sidebar lg:col-span-4 xl:col-span-3 mb-8 lg:mb-0">
                        <details class="lg:hidden bg-gray-50 border border-gray-200 rounded-lg mb-4" open>
                            <summary class="px-4 py-3 font-semibold text-gray-900 cursor-pointer text-sm">Table of contents</summary>
                            <div class="px-4 pb-4">
                                {!! $legalToc !!}
                            </div>
                        </details>
                        <div class="hidden lg:block">
                            {!! $legalToc !!}
                        </div>
                    </aside>
                    <div class="legal-document lg:col-span-8 xl:col-span-9 text-gray-700 prose prose-gray max-w-none prose-headings:text-gray-900 prose-a:text-primary">
                        {!! $legalBody !!}
                    </div>
                </div>
            @else
                <div class="legal-document text-gray-700 prose prose-gray max-w-none prose-headings:text-gray-900 prose-a:text-primary">
                    {!! $rawContent !!}
                </div>
            @endif
        </div>
    </section>
    @if(($page->slug ?? '') === 'security')
        @include('partials.faq-section', ['category' => 'security-compliance', 'title' => 'Security FAQs'])
    @endif
    @include('partials.footer')
</body>
</html>
