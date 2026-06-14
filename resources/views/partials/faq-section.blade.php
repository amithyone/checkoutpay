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
<section class="{{ empty($title) ? 'py-0' : 'py-12 sm:py-16' }} bg-transparent border-0" id="{{ $sectionId }}">
    <div class="{{ empty($title) ? '' : 'max-w-3xl mx-auto px-4 sm:px-6 lg:px-8' }}">
        @if(!empty($title))
        <h2 class="section-heading text-center mb-2">{{ $title }}</h2>
        @endif
        @if($showAllLink)
            <p class="text-center text-sm text-slate-500 font-medium mb-8">
                <a href="{{ route('faqs.index') }}#{{ $anchorSlug }}" class="text-brand-primary font-semibold hover:underline">See all FAQs</a>
                including WordPress, API, and developer program topics.
            </p>
        @elseif(!empty($title))
            <div class="mb-8"></div>
        @endif
        <div class="space-y-3">
            @foreach($items as $item)
                <details class="group card-marketing p-4 open:border-brand-primary/30">
                    <summary class="font-bold text-midnight-deep cursor-pointer list-none flex justify-between items-center gap-4">
                        <span>{{ $item['q'] }}</span>
                        <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition-transform text-sm shrink-0"></i>
                    </summary>
                    <p class="mt-3 text-slate-600 text-sm leading-relaxed font-medium">{!! nl2br(e(FaqCatalog::formatAnswer($item))) !!}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif
