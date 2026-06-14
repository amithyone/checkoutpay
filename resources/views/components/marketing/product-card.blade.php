@props([
    'icon',
    'iconBg' => 'bg-brand-primary/10',
    'iconColor' => 'text-brand-primary',
    'title',
    'description' => null,
    'href' => null,
    'featured' => false,
    'badge' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $baseClass = $featured
        ? 'card-marketing p-6 sm:p-8 border-brand-primary/20 bg-gradient-to-br from-brand-primary/5 to-white'
        : 'card-marketing p-6 sm:p-8 hover:shadow-brand transition-shadow';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => $baseClass . ($href ? ' block group' : '')]) }}
>
    @if($badge)
        <span class="badge-brand mb-4">{{ $badge }}</span>
    @endif
    <div class="w-12 h-12 {{ $iconBg }} rounded-xl flex items-center justify-center mb-4">
        <i class="fas {{ $icon }} {{ $iconColor }} text-2xl" aria-hidden="true"></i>
    </div>
    <h3 class="text-xl sm:text-2xl font-bold text-midnight-deep mb-3">{{ $title }}</h3>
    @if($description)
        <p class="text-slate-600 font-medium mb-4">{{ $description }}</p>
    @endif
    @if(isset($bullets))
        <ul class="space-y-2 mb-6 text-sm text-slate-600">
            {{ $bullets }}
        </ul>
    @endif
    @if(isset($footer))
        <div class="mt-auto">{{ $footer }}</div>
    @endif
</{{ $tag }}>
