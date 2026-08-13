@props([
    'widgetId' => 'right-now',
    'context' => [],
])

@if ($context['liveSession'] ?? null)
    <x-dashboard.widget-card :widget-id="$widgetId" panel-classes="border-slate-700 bg-slate-900 text-slate-100">
        @include('dashboard.widgets.right-now')
    </x-dashboard.widget-card>
@endif
