@props([
    'widgetId' => 'quick-moves',
])

<x-dashboard.widget-card :widget-id="$widgetId" panel-classes="border-emerald-200 bg-white/95">
    @include('dashboard.layout-preview.widgets.quick-moves')
</x-dashboard.widget-card>
