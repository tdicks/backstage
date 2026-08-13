<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-slate-100">Dashboard</h2>
        </div>
    </x-slot>

    <div class="py-10" data-dashboard-gridstack x-data="dashboardGridstackPersistence()" x-init="init()" x-bind:data-initial-layout-json="initialLayoutJson">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600" data-gridstack-summary>Layout locked: click Unlock layout to move widgets.</p>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                        data-gridstack-toggle
                    >Unlock layout</button>
                </div>
            </section>

            <section class="grid-stack dashboard-gridstack" data-gridstack-canvas>
                <div class="grid-stack-item" gs-id="getting-started" gs-x="0" gs-y="0" gs-w="4" gs-h="3">
                    <div class="grid-stack-item-content">
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

                <div class="grid-stack-item" gs-id="action-inbox" gs-x="4" gs-y="0" gs-w="4" gs-h="3">
                    <div class="grid-stack-item-content">
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

                <div class="grid-stack-item" gs-id="coming-up" gs-x="8" gs-y="0" gs-w="4" gs-h="3">
                    <div class="grid-stack-item-content">
                        @include('dashboard.widgets.coming-up', [
                            'nextNonLiveSession' => $nextNonLiveSession,
                            'nextNonLiveSets' => $nextNonLiveSets,
                            'slotLabels' => $slotLabels,
                        ])
                    </div>
                </div>

                <div class="grid-stack-item" gs-id="quick-moves" gs-x="0" gs-y="3" gs-w="6" gs-h="2">
                    <div class="grid-stack-item-content">
                        @include('dashboard.widgets.quick-moves')
                    </div>
                </div>

                <div class="grid-stack-item" gs-id="looking-around" gs-x="6" gs-y="3" gs-w="6" gs-h="2">
                    <div class="grid-stack-item-content">
                        @include('dashboard.widgets.looking-around')
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
