@props([
    'title' => null,
    'subtitle' => null,
    'bg' => 'white',
])

@php
    $bgClass = match ($bg) {
        'muted' => 'bg-surface-container-low',
        default => 'bg-white',
    };
@endphp

<section {{ $attributes->merge(['class' => "py-16 sm:py-20 {$bgClass}"]) }}>
    <div class="max-w-container mx-auto px-4 sm:px-6 lg:px-8">
        @if($title || $subtitle)
            <div class="text-center mb-12 max-w-2xl mx-auto">
                @if($title)
                    <h2 class="section-heading mb-4">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="section-subheading mx-auto">{{ $subtitle }}</p>
                @endif
            </div>
        @endif
        {{ $slot }}
    </div>
</section>
