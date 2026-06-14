@props([
    'badge' => null,
    'icon' => null,
    'title',
    'subtitle' => null,
    'align' => 'center',
])

@php
    $alignClass = $align === 'left' ? 'text-left' : 'text-center';
    $containerClass = $align === 'left' ? '' : 'mx-auto';
@endphp

<section class="py-14 sm:py-20">
    <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8 {{ $alignClass }}">
        @if($badge)
            <div class="badge-brand {{ $align === 'center' ? 'mx-auto' : '' }} mb-6">{{ $badge }}</div>
        @endif
        @if($icon)
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-primary/10 text-brand-primary mb-6">
                <i class="fas {{ $icon }} text-3xl" aria-hidden="true"></i>
            </div>
        @endif
        <h1 class="section-heading mb-4 sm:mb-6 {{ $containerClass }} max-w-4xl">{{ $title }}</h1>
        @if($subtitle)
            <p class="section-subheading {{ $containerClass }} mb-8 max-w-3xl">{{ $subtitle }}</p>
        @endif
        @if(isset($actions))
            <div class="flex flex-col sm:flex-row flex-wrap gap-3 {{ $align === 'center' ? 'justify-center items-center' : '' }}">
                {{ $actions }}
            </div>
        @endif
    </div>
</section>
