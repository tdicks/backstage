@props([
    'widgetId',
    'panelClasses' => 'border-slate-200 bg-white/95',
])

@php
    $sectionClasses = trim('relative flex h-full min-h-0 flex-col overflow-hidden select-none rounded-xl '.$panelClasses.' p-5 shadow-sm transition-all duration-300 ease-out will-change-transform sm:p-6');
@endphp

<section
    data-widget-card
    data-widget-id="{{ $widgetId }}"
    @pointerdown.capture="startWidgetMove('{{ $widgetId }}', $event)"
    x-sort:handle
    {{ $attributes->merge(['class' => $sectionClasses]) }}
>
    <div
        class="dashboard-widget-displacement-readout pointer-events-none hidden lg:flex"
        x-show="isWidgetDisplaced('{{ $widgetId }}')"
        x-cloak
    >
        <span x-text="widgetDisplacementSummary('{{ $widgetId }}')"></span>
    </div>

    <div
        class="dashboard-widget-resize-readout pointer-events-none hidden lg:flex"
        x-show="isWidgetResizing('{{ $widgetId }}')"
        x-cloak
    >
        <span x-text="widgetResizeSummary('{{ $widgetId }}')"></span>
    </div>

    <button
        type="button"
        class="dashboard-widget-resize-handle dashboard-widget-resize-handle-e hidden lg:block"
        data-widget-resize-handle="x"
        @pointerdown="startWidgetResize('{{ $widgetId }}', 'x', $event)"
        title="Resize width"
    ></button>
    <button
        type="button"
        class="dashboard-widget-resize-handle dashboard-widget-resize-handle-s hidden lg:block"
        data-widget-resize-handle="y"
        @pointerdown="startWidgetResize('{{ $widgetId }}', 'y', $event)"
        title="Resize height"
    ></button>
    <button
        type="button"
        class="dashboard-widget-resize-handle dashboard-widget-resize-handle-se hidden lg:block"
        data-widget-resize-handle="xy"
        @pointerdown="startWidgetResize('{{ $widgetId }}', 'xy', $event)"
        title="Resize width and height"
    ></button>

    <div class="min-h-0 flex-1" data-widget-body @pointerdown.capture="guardWidgetDragFromScrollbar('{{ $widgetId }}', $event)" x-bind:class="widgetBodyClasses('{{ $widgetId }}')">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="mt-4 border-t border-slate-200/80 pt-3">
            {{ $footer }}
        </footer>
    @endisset
</section>
