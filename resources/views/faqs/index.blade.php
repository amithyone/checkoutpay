<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.marketing-head', ['seoPath' => '/faqs', 'seo' => $seo, 'jsonLdExtra' => $jsonLdExtra])
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
<body class="bg-white">
    @include('partials.nav')

    <section class="py-12 sm:py-16 bg-gradient-to-br from-primary/10 via-white to-primary/5">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">{{ $page->title ?? 'Frequently asked questions' }}</h1>
            <p class="text-gray-600 mb-8 max-w-2xl mx-auto">
                Payment gateway, WordPress plugin, API, developer program, WhatsApp Wallet, and more — built for Nigeria.
            </p>
            <form action="{{ route('faqs.index') }}" method="get" class="max-w-xl mx-auto flex gap-2" role="search">
                <label for="faq-search" class="sr-only">Search FAQs</label>
                <input
                    type="search"
                    name="q"
                    id="faq-search"
                    value="{{ $query }}"
                    placeholder="Search: WooCommerce, API, developer program, WhatsApp…"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                    autocomplete="off"
                >
                <button type="submit" class="px-5 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 text-sm">
                    Search
                </button>
            </form>
            @if($query !== '')
                <p class="mt-4 text-sm text-gray-500">{{ $totalCount }} result(s) for &ldquo;{{ $query }}&rdquo;
                    · <a href="{{ route('faqs.index') }}" class="text-primary hover:underline">Clear</a>
                </p>
            @endif
        </div>
    </section>

    <section class="py-8 border-b border-gray-100 sticky top-0 bg-white/95 backdrop-blur z-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 overflow-x-auto">
            <nav class="flex flex-wrap gap-2 text-sm" aria-label="FAQ categories">
                @foreach($categories as $slug => $meta)
                    <a href="#{{ $slug }}" class="whitespace-nowrap px-3 py-1.5 rounded-full border border-gray-200 text-gray-700 hover:border-primary hover:text-primary transition-colors">
                        {{ $meta['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-14">
        @forelse($grouped as $slug => $group)
            <section id="{{ $slug }}">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ $group['meta']['label'] }}</h2>
                <div class="space-y-3">
                    @foreach($group['items'] as $item)
                        <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                            <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center gap-4">
                                <span>{{ $item['q'] }}</span>
                                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm shrink-0"></i>
                            </summary>
                            <p class="mt-3 text-gray-600 text-sm leading-relaxed">{!! nl2br(e(\App\Support\FaqCatalog::formatAnswer($item))) !!}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-center text-gray-600">No FAQs match your search. Try &ldquo;WordPress&rdquo;, &ldquo;API&rdquo;, or &ldquo;developer&rdquo;.</p>
        @endforelse
    </div>

    <section class="py-12 bg-gray-50 border-t border-gray-100">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <p class="text-gray-600 mb-4">Still need help?</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('support.index') }}" class="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary/90">Support</a>
                <a href="{{ route('contact') }}" class="px-6 py-3 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-white">Contact</a>
                <a href="{{ route('developers.program') }}" class="px-6 py-3 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-white">Developer program</a>
            </div>
        </div>
    </section>

    @include('partials.footer')
</body>
</html>
