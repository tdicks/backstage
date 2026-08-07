<div class="flex items-center justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700">
            <x-heroicon-m-hand-raised class="h-6 w-6" aria-hidden="true" />
        </span>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Action inbox</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">Approvals and Requests</h3>
        </div>
    </div>
    <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $approvalsTotal }} waiting</span>
</div>

<div class="mt-4 space-y-3">
    @if ($approvalSessions->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-600">
            No approvals are waiting right now.
        </div>
    @else
        <x-my-sets.approval-hierarchy
            :approval-sessions="$approvalSessions"
            :band-templates="$bandTemplates"
            :slot-options="$slotOptions"
            :slot-conflicts="$slotConflicts"
        />
    @endif
</div>

<div class="mt-6 space-y-3">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Pending for You</h3>

    @if ($pendingForUser->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-600">No pending requests.</p>
    @else
        @foreach ($pendingForUser as $pendingItem)
            @php
                $set = $pendingItem->slot->song->set;
                $session = $set->session;
                $actorName = $pendingItem->actor?->name ?? 'Someone';
                $targetName = $pendingItem->target?->name ?? 'Someone';
                $actionLabel = $pendingItem->type === \App\Models\SlotAssignment::TYPE_PROPOSAL
                    ? $actorName.' recommended '.$targetName
                    : $actorName.' requested a slot';
            @endphp
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">{{ $pendingItem->slot->song->artist }} - {{ $pendingItem->slot->song->title }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ $actionLabel }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $set->name }} · {{ $session?->name ?? 'Planned set' }}</p>
                @if ($session)
                    <a href="{{ route('sessions.show', $session) }}#set-{{ $set->id }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-sky-700 underline decoration-sky-300 underline-offset-2 hover:decoration-sky-700">
                        Open set
                    </a>
                @endif
            </article>
        @endforeach
    @endif
</div>
