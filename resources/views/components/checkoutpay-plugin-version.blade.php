@php
    use App\Support\CheckoutPayWordPressPlugin;
@endphp
<p {{ $attributes->merge(['class' => '']) }}>
    <i class="fas fa-info-circle mr-1"></i>
    <strong>Version:</strong> {{ CheckoutPayWordPressPlugin::version() }}
    | <strong>Requires:</strong> {{ CheckoutPayWordPressPlugin::requirementsLabel() }}
</p>
