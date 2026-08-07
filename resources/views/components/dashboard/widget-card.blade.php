@props([
    'widgetId',
    'panelClasses' => 'border-slate-200 bg-white/95',
])

<x-dashboard.widget-shell
    :widget-id="$widgetId"
    :panel-classes="$panelClasses"
    x-sort:item="'{{ $widgetId }}'"
    x-bind:class="[widgetContainerClasses('{{ $widgetId }}'), widgetStackClasses('{{ $widgetId }}'), widgetDragClasses('{{ $widgetId }}')]"
    x-bind:style="widgetOrderStyle('{{ $widgetId }}')"
>
    {{ $slot }}
</x-dashboard.widget-shell>
