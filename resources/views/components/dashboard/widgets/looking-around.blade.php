@props([
    'widgetId' => 'looking-around',
])

<x-dashboard.widget-card :widget-id="$widgetId" panel-classes="border-purple-200 bg-white/95">
    @include('dashboard.layout-preview.widgets.looking-around')
</x-dashboard.widget-card>
