<section class="rounded-xl border border-amber-200 bg-white/95 p-5 shadow-sm ring-1 ring-amber-100/70 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Approvals and Requests</h2>
            <p class="mt-1 text-sm text-slate-600">Handle decisions for your sets and track your pending requests.</p>
        </div>
        <a href="{{ route('my-sets.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-400">
            Open My Sets
            <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
        </a>
    </div>

    @if ($approvalsTotal > 0)
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-amber-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approvals</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $approvalsTotal }}</p>
                <p class="mt-1 text-sm text-slate-600">Recommendations, slot requests, and song requests on your sets.</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending for you</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $pendingForUser->count() }}</p>
                <p class="mt-1 text-sm text-slate-600">Requests you made that are waiting on another person.</p>
            </div>
        </div>

        <div class="mt-6 space-y-4">
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
                    tone="amber"
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
    @else
        <div class="mt-5 flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-6 text-center">
            <p class="text-base font-semibold text-emerald-800">All done!</p>
        </div>
    @endif
</section>
