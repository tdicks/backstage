<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-100">Dashboard</h2>
                <p class="mt-1 text-sm text-slate-300 hidden lg:block">Hover over widget corners to resize them</p>
            </div>
        </div>
    </x-slot>

    <div
        class="py-10"
        data-dashboard-layout-preview
        x-data="dashboardLayoutPreviewPage({
            csrfToken: @js(csrf_token()),
            debugEnabled: @js((bool) config('app.debug')),
            currentUserId: @js((int) auth()->id()),
            liveDataUrl: @js($liveSession ? route('sessions.live.data', $liveSession) : null),
            liveSessionName: @js($liveSession?->name),
            liveSessionDashboardUrl: @js($liveSession ? route('sessions.live.dashboard', $liveSession) : null),
            widgetOrderUpdateUrl: @js(route('dashboard.layout-preview.widget-order.update')),
            widgetOrderIds: @js($widgetOrderIds),
            widgetDefinitions: @js($widgetDefinitionState),
            widgetSizes: @js($widgetSizeMap),
            widgetHeights: @js($widgetHeightMap),
            widgetPositions: @js($widgetPositionMap),
            setAttachmentsUrlTemplate: @js(route('sets.attachments.index', '__ID__')),
            songAttachmentsUrlTemplate: @js(route('songs.attachments.index', '__ID__')),
            slotAttachmentsUrlTemplate: @js(route('slots.attachments.index', '__ID__')),
        })"
        x-init="init()"
    >
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="space-y-6">
                <div class="relative" x-show="!layoutReady" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <div class="rounded-xl border border-slate-200 bg-white/95 p-8 shadow-sm">
                        <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Preparing your widget layout</p>
                                <p class="mt-1 text-sm text-slate-600">Loading your dashboard preview</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="h-2.5 w-24 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full w-1/2 animate-pulse rounded-full bg-sky-500"></div>
                                </div>
                                <span class="text-sm font-semibold text-slate-500">Just a moment</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative" x-show="layoutReady" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <x-dashboard.widget-canvas
                        class="dashboard-widget-grid"
                        x-bind:class="widgetGridClasses()"
                        data-widget-grid
                        x-sort.ghost="reorderWidget($item, $position)"
                        x-sort:config="widgetSortConfig()"
                    >
                    <div
                        class="widget-drop-placeholder dashboard-widget-canvas-drop-target hidden lg:block"
                        x-show="activeMove && activeMove.dragging"
                        x-cloak
                        x-bind:style="widgetCanvasPlaceholderStyle()"
                    ></div>
                    @foreach ($widgetDefinitions as $widgetDefinition)
                        <x-dynamic-component
                            :component="$widgetDefinition['component']"
                            :widget-id="$widgetDefinition['id']"
                            :context="$widgetContext"
                        />
                    @endforeach

                    </x-dashboard.widget-canvas>
                </div>

                <div class="fixed bottom-4 right-4 z-40 hidden lg:block" x-show="layoutDebugEnabled" x-cloak>
                    <button
                        type="button"
                        class="rounded-md border border-slate-400 bg-slate-900/90 px-3 py-2 text-xs font-semibold text-slate-100 shadow-sm transition hover:bg-slate-800"
                        @click="toggleLayoutDebugPanel()"
                    >
                        Layout Debug
                    </button>

                    <section
                        class="mt-2 w-80 rounded-lg border border-slate-300 bg-white/95 p-3 text-xs text-slate-700 shadow-lg"
                        x-show="layoutDebugPanelOpen"
                        x-transition
                    >
                        <p class="font-semibold text-slate-900">Drag Diagnostics</p>
                        <div class="mt-2 grid grid-cols-2 gap-y-1">
                            <span class="text-slate-500">Visible widgets</span>
                            <span x-text="visibleWidgetIds().length"></span>
                            <span class="text-slate-500">Visible row range</span>
                            <span x-text="`${visibleLayoutRowRange().minRow}..${visibleLayoutRowRange().maxRow}`"></span>
                            <span class="text-slate-500">Active widget</span>
                            <span x-text="activeMove?.widgetId || layoutDebugLastMove?.widgetId || 'none'"></span>
                            <span class="text-slate-500">Preview cell</span>
                            <span x-text="activeMove ? `C${activeMove.previewColumn} R${activeMove.previewRow}` : 'n/a'"></span>
                            <span class="text-slate-500">Row limit</span>
                            <span x-text="layoutDebugLastMove?.maxTargetRow ?? 'n/a'"></span>
                            <span class="text-slate-500">Delta (x, y)</span>
                            <span x-text="layoutDebugLastMove ? `${layoutDebugLastMove.deltaX}, ${layoutDebugLastMove.deltaY}` : 'n/a'"></span>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        @include('components.sessions.attachments-modal')
    </div>
</x-app-layout>
