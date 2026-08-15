@php
    $enabledWidgets = array_fill_keys($enabledWidgetIds ?? [], true);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-slate-100">Dashboard</h2>
            <div class="flex items-center gap-3">
                <button type="button" data-gridstack-toggle aria-label="Unlock layout" aria-pressed="false" title="Unlock layout" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-100 shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-900">
                    <x-heroicon-m-lock-closed class="h-4 w-4" data-gridstack-toggle-locked-icon aria-hidden="true" />
                    <x-heroicon-m-lock-open class="hidden h-4 w-4" data-gridstack-toggle-unlocked-icon aria-hidden="true" />
                    <span class="sr-only">Toggle dashboard layout lock</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div
        class="py-10"
        data-dashboard-gridstack
        x-data="dashboardGridstackPersistence()"
        x-init="init()"
        data-initial-layout-json='@json($initialWidgetLayout)'
        data-layout-save-url="{{ route('dashboard.layout.save') }}"
        data-widget-catalog-json='@json($widgetCatalog)'
    >
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($liveSession)
                <section data-live-session-widget>
                    @include('dashboard.widgets.live-session', ['liveSession' => $liveSession])
                </section>
            @endif

            <section class="grid-stack dashboard-gridstack" data-gridstack-canvas>
                @if ($enabledWidgets['getting-started'] ?? false)
                <div class="grid-stack-item" gs-id="getting-started" gs-x="0" gs-y="0" gs-w="4" gs-h="3" x-bind:data-widget-hidden="!isWidgetVisible('getting-started')">
                    <div class="grid-stack-item-content relative">
                        @if ($showGetStartedQuest)
                            @include('dashboard.widgets.getting-started', [
                                'getStartedItems' => $getStartedItems,
                                'allGetStartedItemsCompleted' => $allGetStartedItemsCompleted,
                            ])
                        @else
                            <x-dashboard.widget-frame panel-classes="border-emerald-200 bg-white/95" icon-frame-classes="border-emerald-200 bg-emerald-50 text-emerald-700">
                                <x-slot:icon>
                                    <x-heroicon-m-check-circle class="h-6 w-6" aria-hidden="true" />
                                </x-slot:icon>
                                <x-slot:kicker>Get started</x-slot:kicker>
                                <x-slot:title>All set</x-slot:title>
                                <p class="text-sm text-slate-600">Your onboarding checklist is complete.</p>
                            </x-dashboard.widget-frame>
                        @endif
                    </div>
                </div>
                @endif

                @if ($enabledWidgets['action-inbox'] ?? false)
                <div class="grid-stack-item" gs-id="action-inbox" gs-x="4" gs-y="0" gs-w="4" gs-h="3" x-bind:data-widget-hidden="!isWidgetVisible('action-inbox')">
                    <div class="grid-stack-item-content relative">
                        <div
                            x-data="dashboardActionQueues({ refreshUrl: @js(route('dashboard.action-queues')), htmlKey: 'widget_html' })"
                            x-init="init()"
                            @target-consent-processed.window="refresh(false)"
                            @pending-approval-processed.window="refresh(false)"
                            @approvals-count-refreshed.window="refresh(false)"
                            class="h-full min-h-0"
                        >
                            <div x-show="errorMessage" x-cloak class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="errorMessage"></div>
                            <div x-show="busy" x-cloak class="mb-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500">Refreshing approvals...</div>

                            <div x-ref="actionQueuesContent" class="h-full min-h-0">
                                @include('dashboard.widgets.action-inbox', [
                                    'approvalsTotal' => $approvalsTotal,
                                    'pendingForUser' => $pendingForUser,
                                    'approvalSessions' => $approvalSessions,
                                    'bandTemplates' => $bandTemplates,
                                    'slotOptions' => $slotOptions,
                                    'slotConflicts' => $slotConflicts,
                                ])
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if ($enabledWidgets['coming-up'] ?? false)
                <div class="grid-stack-item" gs-id="coming-up" gs-x="8" gs-y="0" gs-w="4" gs-h="3" x-bind:data-widget-hidden="!isWidgetVisible('coming-up')">
                    <div class="grid-stack-item-content relative">
                        @include('dashboard.widgets.coming-up', [
                            'nextNonLiveSession' => $nextNonLiveSession,
                            'nextNonLiveSets' => $nextNonLiveSets,
                            'slotLabels' => $slotLabels,
                        ])
                    </div>
                </div>
                @endif

                @if ($enabledWidgets['quick-moves'] ?? false)
                <div class="grid-stack-item" gs-id="quick-moves" gs-x="0" gs-y="3" gs-w="6" gs-h="2" x-bind:data-widget-hidden="!isWidgetVisible('quick-moves')">
                    <div class="grid-stack-item-content relative">
                        @include('dashboard.widgets.quick-moves')
                    </div>
                </div>
                @endif

                @if ($enabledWidgets['looking-around'] ?? false)
                <div class="grid-stack-item" gs-id="looking-around" gs-x="6" gs-y="3" gs-w="6" gs-h="2" x-bind:data-widget-hidden="!isWidgetVisible('looking-around')">
                    <div class="grid-stack-item-content relative">
                        @include('dashboard.widgets.looking-around')
                    </div>
                </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
