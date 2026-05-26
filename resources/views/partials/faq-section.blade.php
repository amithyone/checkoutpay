@php
    use App\Support\FaqCatalog;

    $category = $category ?? null;
    $categories = $categories ?? [];
    if ($category) {
        $categories = array_merge([$category], $categories);
    }
    $categories = array_values(array_unique($categories));
    $title = $title ?? 'Frequently asked questions';
    $limit = $limit ?? null;
    $showAllLink = $showAllLink ?? true;
    $sectionId = $sectionId ?? 'faq';

    $items = [];
    foreach ($categories as $cat) {
        $items = array_merge($items, FaqCatalog::forCategory($cat));
    }
    if ($limit !== null && is_numeric($limit)) {
        $items = array_slice($items, 0, (int) $limit);
    }
    $anchorSlug = $category ?? ($categories[0] ?? 'faqs');
@endphp

@if(count($items) > 0)
<section class="py-12 sm:py-16 bg-white border-t border-gray-100" id="{{ $sectionId }}">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2 text-center">{{ $title }}</h2>
        @if($showAllLink)
            <p class="text-center text-sm text-gray-500 mb-8">
                <a href="{{ route('faqs.index') }}#{{ $anchorSlug }}" class="text-primary font-medium hover:underline">See all FAQs</a>
                including search for WordPress, API, and developer program topics.
            </p>
        @else
            <div class="mb-8"></div>
        @endif
        <div class="space-y-3">
            @foreach($items as $item)
                <details class="group border border-gray-200 rounded-lg p-4 open:bg-gray-50">
                    <summary class="font-semibold text-gray-900 cursor-pointer list-none flex justify-between items-center gap-4">
                        <span>{{ $item['q'] }}</span>
                        <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform text-sm shrink-0"></i>
                    </summary>
                    <p class="mt-3 text-gray-600 text-sm leading-relaxed">{!! nl2br(e(FaqCatalog::formatAnswer($item))) !!}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif
