<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ __('My Sets') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3">
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm"
                    x-data="{ approvalCount: {{ $targetConsentApprovals->count() + $pendingApprovals->sum(fn ($group) => $group['assignments']->count()) }} }"
                    @target-consent-processed.window="approvalCount = Math.max(0, approvalCount - 1)"
                    @pending-approval-processed.window="approvalCount = Math.max(0, approvalCount - 1)"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approvals</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="approvalCount">{{ $targetConsentApprovals->count() + $pendingApprovals->sum(fn ($group) => $group['assignments']->count()) }}</p>
                    <p class="mt-1 text-sm text-slate-600">requests for your sets, and recommendations for you</p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm"
                    x-data="{ pendingItemCount: {{ $pendingForUser->count() }} }"
                    @pending-item-cancelled.window="pendingItemCount = Math.max(0, pendingItemCount - 1)"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending for you</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900" x-text="pendingItemCount">{{ $pendingForUser->count() }}</p>
                    <p class="mt-1 text-sm text-slate-600">your requests and recommendations awaiting a decision</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Practice list</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $practiceSets->count() }}</p>
                    <p class="mt-1 text-sm text-slate-600">sets with confirmed slots for you</p>
                </div>
            </section>

            <div
                x-data="{ pendingCount: {{ $targetConsentApprovals->count() + $pendingApprovals->sum(fn ($group) => $group['assignments']->count()) }}, approvalsCollapsed: false, approvalsKey: 'my-sets-approvals-collapsed' }"
                x-init="approvalsCollapsed = localStorage.getItem(approvalsKey) === '1'"
                x-effect="localStorage.setItem(approvalsKey, approvalsCollapsed ? '1' : '0')"
                @target-consent-processed.window="pendingCount = Math.max(0, pendingCount - 1)"
                @pending-approval-processed.window="pendingCount = Math.max(0, pendingCount - 1)"
                x-show="pendingCount > 0"
                x-transition:enter="transition-all duration-300 ease-out"
                x-transition:enter-start="grid-rows-[0fr] opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="grid-rows-[1fr] opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition-all duration-500 ease-in-out"
                x-transition:leave-start="grid-rows-[1fr] opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="grid-rows-[0fr] opacity-0 -translate-y-2 scale-[0.98]"
                class="grid grid-rows-[1fr] transform-gpu"
                x-cloak
            >
                <section class="min-h-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                    <div
                        class="flex cursor-pointer flex-wrap items-center justify-between gap-3"
                        @click="approvalsCollapsed = !approvalsCollapsed"
                        role="button"
                        tabindex="0"
                        @keydown.enter.prevent="approvalsCollapsed = !approvalsCollapsed"
                        @keydown.space.prevent="approvalsCollapsed = !approvalsCollapsed"
                        x-bind:aria-expanded="(!approvalsCollapsed).toString()"
                        x-bind:title="approvalsCollapsed ? 'Click to show approvals' : 'Click to hide approvals'"
                        aria-label="Toggle approvals"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Approvals</h3>
                            <p class="mt-1 text-sm text-slate-600">Recommendations and slot requests that need your decision.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800" x-text="`${pendingCount} pending`">
                                {{ $targetConsentApprovals->count() + $pendingApprovals->sum(fn ($group) => $group['assignments']->count()) }} pending
                            </span>
                            <x-heroicon-m-chevron-down class="h-4 w-4 text-amber-700 transition" x-bind:class="approvalsCollapsed ? '' : 'rotate-180'" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="mt-4" x-show="!approvalsCollapsed" x-transition>
                    @if ($targetConsentApprovals->isEmpty() && $pendingApprovals->isEmpty())
                        <p class="text-sm text-slate-500">No approvals need your attention right now.</p>
                    @else
                    <div class="space-y-3" x-show="pendingCount > 0">
                        @foreach ($targetConsentApprovals as $consentApproval)
                            @php
                                $set = $consentApproval->slot->song->set;
                                $session = $set->session;
                                $slotLabel = $slotOptions[$consentApproval->slot->name] ?? str($consentApproval->slot->name)->replace('_', ' ')->title();
                            @endphp
                            <article
                                class="rounded-lg border border-amber-200 bg-amber-50/70 p-4"
                                x-data="{
                                    hidden: false,
                                    busy: false,
                                    error: '',
                                    async respond(status) {
                                        this.busy = true;
                                        this.error = '';

                                        try {
                                            const response = await fetch('{{ route('slot-assignments.respond', $consentApproval) }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                },
                                                body: JSON.stringify({
                                                    _method: 'PATCH',
                                                    status,
                                                }),
                                            });

                                            if (!response.ok) {
                                                throw new Error('Request failed');
                                            }

                                            this.hidden = true;
                                            window.dispatchEvent(new CustomEvent('target-consent-processed'));
                                        } catch (e) {
                                            this.error = 'Could not update recommendation. Try again.';
                                        } finally {
                                            this.busy = false;
                                        }
                                    },
                                }"
                                x-show="!hidden"
                                x-transition
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-semibold text-slate-900">{{ $consentApproval->slot->song->artist }} - {{ $consentApproval->slot->song->title }}</h4>
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Slot Recommendation</span>
                                        </div>
                                        <p class="text-sm text-slate-700">
                                            {{ $session->name }} · {{ $session->date->format('D, M j, Y') }} · {{ $set->name }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-700">{{ $consentApproval->actor->name }} recommended you for {{ $slotLabel }}.</p>
                                        @if ($consentApproval->message)
                                            <p class="mt-2 text-sm text-slate-600">{{ $consentApproval->message }}</p>
                                        @endif
                                        <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-700"></p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            @click="respond('accepted')"
                                            x-bind:disabled="busy"
                                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40"
                                            aria-label="Accept recommendation"
                                            title="Accept this recommendation"
                                        >
                                            <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                                            <span>Accept</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="respond('rejected')"
                                            x-bind:disabled="busy"
                                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                                            aria-label="Reject recommendation"
                                            title="Reject this recommendation"
                                        >
                                            <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                            <span>Reject</span>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach

                        @foreach ($pendingApprovals as $approvalGroup)
                            <article class="rounded-lg border border-amber-200 bg-amber-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-semibold text-slate-900">{{ $approvalGroup['song']->artist }} - {{ $approvalGroup['song']->title }}</h4>
                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Set approval</span>
                                        </div>
                                        <p class="text-sm text-slate-700">
                                            {{ $approvalGroup['session']->name }} · {{ $approvalGroup['session']->date->format('D, M j, Y') }} · {{ $approvalGroup['set']->name }}
                                        </p>
                                    </div>
                                    <a href="{{ route('sessions.show', $approvalGroup['session']) }}" class="text-sm font-medium text-amber-800 underline">Open session</a>
                                </div>

                                <div class="mt-3 divide-y divide-amber-200/70">
                                    @foreach ($approvalGroup['assignments'] as $approval)
                                        @php
                                            $slotLabel = $slotOptions[$approval->slot->name] ?? str($approval->slot->name)->replace('_', ' ')->title();
                                            $isRecommendation = $approval->type === \App\Models\SlotAssignment::TYPE_PROPOSAL;
                                        @endphp
                                        <div
                                            class="{{ $isRecommendation ? 'my-3 rounded-lg border border-amber-200 bg-amber-50/70 p-4' : 'py-3' }}"
                                            x-data="{
                                                hidden: false,
                                                busy: false,
                                                error: '',
                                                async respond(status) {
                                                    this.busy = true;
                                                    this.error = '';

                                                    try {
                                                        const response = await fetch('{{ route('slot-assignments.respond', $approval) }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'Accept': 'application/json',
                                                                'X-Requested-With': 'XMLHttpRequest',
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                            },
                                                            body: JSON.stringify({
                                                                _method: 'PATCH',
                                                                status,
                                                            }),
                                                        });

                                                        if (!response.ok) {
                                                            throw new Error('Request failed');
                                                        }

                                                        this.hidden = true;
                                                        window.dispatchEvent(new CustomEvent('pending-approval-processed'));
                                                    } catch (e) {
                                                        this.error = 'Could not update approval. Try again.';
                                                    } finally {
                                                        this.busy = false;
                                                    }
                                                },
                                            }"
                                            x-show="!hidden"
                                            x-transition
                                        >
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    @if ($isRecommendation)
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="text-sm font-semibold text-slate-900">{{ $approval->target->name }}</p>
                                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Recommendation</span>
                                                        </div>
                                                        <p class="mt-1 text-sm text-slate-700">{{ $approval->actor->name }} recommended {{ $approval->target->name }} for {{ $slotLabel }}.</p>
                                                    @else
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="text-sm font-semibold text-slate-900">{{ $slotLabel }}</p>
                                                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Request</span>
                                                        </div>
                                                        <p class="mt-1 text-sm text-slate-700">{{ $approval->actor->name }} requested {{ $slotLabel }}.</p>
                                                    @endif
                                                    @if ($approval->message)
                                                        <p class="mt-1 text-sm text-slate-600">{{ $approval->message }}</p>
                                                    @endif
                                                    <p x-show="error" x-text="error" class="mt-1 text-sm text-rose-700"></p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button
                                                        type="button"
                                                        @click="respond('accepted')"
                                                        x-bind:disabled="busy"
                                                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40"
                                                        aria-label="Approve assignment"
                                                        title="Approve this assignment"
                                                    >
                                                        <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                                                        <span>Approve</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="respond('rejected')"
                                                        x-bind:disabled="busy"
                                                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                                                        aria-label="Reject assignment"
                                                        title="Reject this assignment"
                                                    >
                                                        <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                                        <span>Reject</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                    @endif
                    </div>
                </section>
            </div>

            @if ($pendingForUser->isNotEmpty())
                <section
                    class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm"
                    x-data="{ pendingCollapsed: false, pendingKey: 'my-sets-pending-collapsed', pendingItemCount: {{ $pendingForUser->count() }} }"
                    x-init="pendingCollapsed = localStorage.getItem(pendingKey) === '1'"
                    x-effect="localStorage.setItem(pendingKey, pendingCollapsed ? '1' : '0')"
                    @pending-item-cancelled.window="pendingItemCount = Math.max(0, pendingItemCount - 1)"
                    x-show="pendingItemCount > 0"
                    x-transition
                >
                    <div
                        class="flex cursor-pointer flex-wrap items-center justify-between gap-3"
                        @click="pendingCollapsed = !pendingCollapsed"
                        role="button"
                        tabindex="0"
                        @keydown.enter.prevent="pendingCollapsed = !pendingCollapsed"
                        @keydown.space.prevent="pendingCollapsed = !pendingCollapsed"
                        x-bind:aria-expanded="(!pendingCollapsed).toString()"
                        x-bind:title="pendingCollapsed ? 'Click to show pending items' : 'Click to hide pending items'"
                        aria-label="Toggle pending items"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Pending for you</h3>
                            <p class="mt-1 text-sm text-slate-600">Songs and slots you've put yourself forward for.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700" x-text="`${pendingItemCount} pending`">{{ $pendingForUser->count() }} pending</span>
                            <x-heroicon-m-chevron-down class="h-4 w-4 text-slate-600 transition" x-bind:class="pendingCollapsed ? '' : 'rotate-180'" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="mt-4" x-show="!pendingCollapsed" x-transition>
                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach ($pendingForUser as $pending)
                                @php
                                    $set = $pending->slot->song->set;
                                    $session = $set->session;
                                    $slotLabel = $slotOptions[$pending->slot->name] ?? str($pending->slot->name)->replace('_', ' ')->title();
                                @endphp
                                <article
                                    class="rounded-lg border border-slate-200 bg-white/70 p-4 text-sm text-slate-600"
                                    x-data="{
                                        hidden: false,
                                        busy: false,
                                        error: '',
                                        async cancel() {
                                            this.busy = true;
                                            this.error = '';

                                            try {
                                                const response = await fetch('{{ route('slot-assignments.respond', $pending) }}', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'Accept': 'application/json',
                                                        'X-Requested-With': 'XMLHttpRequest',
                                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                    },
                                                    body: JSON.stringify({
                                                        _method: 'PATCH',
                                                        status: 'rejected',
                                                    }),
                                                });

                                                if (!response.ok) {
                                                    throw new Error('Request failed');
                                                }

                                                this.hidden = true;
                                                window.dispatchEvent(new CustomEvent('pending-item-cancelled'));
                                            } catch (e) {
                                                this.error = 'Could not cancel this pending item. Try again.';
                                            } finally {
                                                this.busy = false;
                                            }
                                        },
                                    }"
                                    x-show="!hidden"
                                    x-transition
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-slate-800">{{ $pending->slot->song->artist }} - {{ $pending->slot->song->title }}</p>
                                            <p class="mt-1">{{ $session->name }} · {{ $session->date->format('D, M j, Y') }} · {{ $set->name }}</p>
                                            <p class="mt-1">
                                                {{ $slotLabel }} ·
                                                @if ($pending->type === \App\Models\SlotAssignment::TYPE_REQUEST)
                                                    requested by you
                                                @else
                                                    recommended for you by {{ $pending->actor->name }}
                                                @endif
                                            </p>
                                            @if ($pending->message)
                                                <p class="mt-2 text-slate-500">{{ $pending->message }}</p>
                                            @endif
                                            <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-700"></p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="cancel()"
                                            x-bind:disabled="busy"
                                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                                            aria-label="This will remove your request for this slot."
                                            title="This will remove your request for this slot."
                                        >
                                            <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                            <span>Cancel</span>
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <section class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Practice plan</h3>
                        <p class="mt-1 text-sm text-slate-600">Upcoming confirmed songs, grouped by jam and set.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-6">
                    @forelse ($practiceSets->groupBy(fn ($group) => $group['set']->session->date->format('F Y')) as $monthLabel => $monthGroups)
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $monthLabel }}</h4>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>

                            <div class="space-y-4">
                                @foreach ($monthGroups as $group)
                                    @php
                                        $set = $group['set'];
                                        $isOwned = $group['isOwned'];
                                        $isPerformed = $set->performed;
                                    @endphp

                                    <article class="rounded-xl border {{ $isPerformed ? 'border-slate-200 bg-slate-100/95 text-slate-500' : 'border-slate-200 bg-white/90 text-slate-900' }} p-5 shadow-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-4">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="text-lg font-semibold">{{ $set->name }}</h4>
                                                    @if ($isOwned)
                                                        <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-800">Owner</span>
                                                    @endif
                                                    @if ($isPerformed)
                                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Performed</span>
                                                    @else
                                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Upcoming</span>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-sm {{ $isPerformed ? 'text-slate-500' : 'text-slate-600' }}">
                                                    {{ $set->session->name }} · {{ $set->session->date->format('D, M j, Y') }}
                                                </p>
                                            </div>
                                            <a href="{{ route('sessions.show', $set->session) }}" class="text-sm font-medium text-slate-700 underline">Open session</a>
                                        </div>

                                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                            @forelse ($group['songs'] as $songGroup)
                                                @php
                                                    $song = $songGroup['song'];
                                                @endphp
                                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                                    <h5 class="font-semibold text-slate-900">{{ $song->artist }} - {{ $song->title }}</h5>
                                                    @if ($song->notes)
                                                        <p class="mt-1 text-sm text-slate-600">{{ $song->notes }}</p>
                                                    @endif
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach ($songGroup['slots'] as $slot)
                                                            @php
                                                                $slotLabel = $slotOptions[$slot->name] ?? str($slot->name)->replace('_', ' ')->title();
                                                                $isMine = $slot->user_id === auth()->id();
                                                            @endphp
                                                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold shadow-sm {{ $isMine ? 'border-sky-200 bg-sky-50 text-sky-800' : ($slot->isOpen() ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800') }}">
                                                                <span>{{ $slotLabel }}</span>
                                                                @if ($isOwned)
                                                                    <span>-</span>
                                                                    <span>{{ $slot->assignedPerformerName() }}</span>
                                                                @elseif ($isMine)
                                                                    <span class="uppercase tracking-wide opacity-70">you</span>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="rounded-lg border border-dashed border-slate-300 bg-white/80 p-4 text-sm text-slate-500">No songs to practise from this set yet.</p>
                                            @endforelse
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
                            You do not have any confirmed slots to practise yet.
                        </div>
                    @endforelse
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
