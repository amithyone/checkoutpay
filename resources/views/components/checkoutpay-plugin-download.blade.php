@props([
    'label' => null,
    'icon' => true,
])
@php
    use App\Support\CheckoutPayWordPressPlugin;
    $downloadLabel = $label ?? ($slot->isEmpty() ? 'Get on WordPress.org' : null);
    $url = CheckoutPayWordPressPlugin::downloadUrl();
    $external = str_starts_with($url, 'http') && ! str_starts_with($url, url('/'));
@endphp
<a
    href="{{ $url }}"
    @if($external) target="_blank" rel="noopener noreferrer" @else download="{{ CheckoutPayWordPressPlugin::slug() }}-{{ CheckoutPayWordPressPlugin::version() }}.zip" @endif
    {{ $attributes }}
>
    @if ($icon)
        <i class="fab fa-wordpress mr-2"></i>
    @endif
    @if ($downloadLabel)
        {{ $downloadLabel }}
    @else
        {{ $slot }}
    @endif
</a>
