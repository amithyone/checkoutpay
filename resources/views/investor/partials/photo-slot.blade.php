@php
    $aspect = $aspect ?? ($slot['aspect'] ?? '16 / 9');
@endphp
<div class="photo-slot {{ $slot['url'] ? 'has-image' : '' }}" style="aspect-ratio: {{ $aspect }};">
    @if ($slot['url'])
        <img src="{{ $slot['url'] }}" alt="{{ $slot['label'] }}">
    @endif
</div>
