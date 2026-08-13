@props([
    'widgetId' => 'getting-started',
    'context' => [],
])

@if ($context['showGetStartedQuest'] ?? false)
    @php
        $panelClasses = ($context['allGetStartedItemsCompleted'] ?? false)
            ? 'border-2 border-emerald-200 bg-white/95'
            : 'border-2 border-amber-200 bg-white/95';
    @endphp

    <x-dashboard.widget-card :widget-id="$widgetId" :panel-classes="$panelClasses">
                @include('dashboard.widgets.getting-started', [
            'getStartedItems' => $context['getStartedItems'] ?? [],
            'allGetStartedItemsCompleted' => $context['allGetStartedItemsCompleted'] ?? false,
        ])
    </x-dashboard.widget-card>
@endif
