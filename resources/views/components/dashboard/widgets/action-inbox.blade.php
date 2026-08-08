@props([
    'widgetId' => 'action-inbox',
    'context' => [],
])

@php
    $approvalsTotal = (int) ($context['approvalsTotal'] ?? 0);
    $pendingForUser = $context['pendingForUser'] ?? collect();
    $approvalSessions = $context['approvalSessions'] ?? collect();
    $bandTemplates = $context['bandTemplates'] ?? collect();
    $slotOptions = $context['slotOptions'] ?? [];
    $slotConflicts = $context['slotConflicts'] ?? [];
@endphp

<x-dashboard.widget-card :widget-id="$widgetId" panel-classes="border-amber-200 bg-slate-50/95">
    <div
        x-data="dashboardActionQueues({ refreshUrl: @js(route('dashboard.action-queues')), htmlKey: 'widget_html' })"
        x-init="init()"
        @target-consent-processed.window="refresh(false)"
        @pending-approval-processed.window="refresh(false)"
        @approvals-count-refreshed.window="refresh(false)"
        class="space-y-3"
    >
        <div x-show="errorMessage" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="errorMessage"></div>
        <div x-show="busy" x-cloak class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500">Refreshing approvals...</div>

        <div x-ref="actionQueuesContent">
            @include('dashboard.layout-preview.widgets.action-inbox', [
                'approvalsTotal' => $approvalsTotal,
                'pendingForUser' => $pendingForUser,
                'approvalSessions' => $approvalSessions,
                'bandTemplates' => $bandTemplates,
                'slotOptions' => $slotOptions,
                'slotConflicts' => $slotConflicts,
            ])
        </div>
    </div>
</x-dashboard.widget-card>
