@props([
    'features' => [],
])

@if(count($features) > 0)
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
    @foreach($features as $feature)
        <x-marketing.product-card
            :icon="$feature['icon'] ?? 'fa-check'"
            :icon-bg="$feature['iconBg'] ?? 'bg-brand-primary/10'"
            :icon-color="$feature['iconColor'] ?? 'text-brand-primary'"
            :title="$feature['title']"
            :description="$feature['description'] ?? null"
        />
    @endforeach
</div>
@endif
