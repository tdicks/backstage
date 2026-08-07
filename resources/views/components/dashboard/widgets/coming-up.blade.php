@props([
    'widgetId' => 'coming-up',
    'context' => [],
])

<x-dashboard.widget-card :widget-id="$widgetId" panel-classes="border-sky-200 bg-white/95">
    @include('dashboard.layout-preview.widgets.coming-up', [
        'nextNonLiveSession' => $context['nextNonLiveSession'] ?? null,
        'nextNonLiveSets' => $context['nextNonLiveSets'] ?? collect(),
        'slotLabels' => $context['slotLabels'] ?? [],
    ])
</x-dashboard.widget-card>
