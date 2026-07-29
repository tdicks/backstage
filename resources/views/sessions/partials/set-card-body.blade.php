@php
    $setLocked = $set->performed;
    $sessionLocked = (bool) ($set->session?->is_closed ?? false);
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->is_admin;
    $isSetOwner = $set->owner_id === auth()->id();
    $isCollaborator = ! $isSetOwner && $set->isCollaborator($currentUser);
    $canManageSet = $isAdmin || $isSetOwner || $isCollaborator;
    $canEditSet = $isAdmin || $isSetOwner;
    $canManageCollaborators = $isAdmin || $isSetOwner;
    $isAdminManagingOtherSet = $isAdmin && ! $isSetOwner && ! $isCollaborator;
    $summarySlotNames = collect(array_keys($slotOptions))
        ->filter(fn (string $slotName) => $set->songs->contains(fn ($song) => $song->slots->contains('name', $slotName)))
        ->values();
    $pendingSlotAssignments = $set->songs
        ->flatMap(function ($song) {
            return $song->slots->flatMap(function ($slot) use ($song) {
                return $slot->assignments
                    ->whereIn('status', [
                        \App\Models\SlotAssignment::STATUS_AWAITING_TARGET_CONSENT,
                        \App\Models\SlotAssignment::STATUS_PENDING,
                    ])
                    ->map(fn ($assignment) => [
                        'song' => $song,
                        'slot' => $slot,
                        'assignment' => $assignment,
                    ]);
            });
        })
        ->values();
@endphp

<div class="mt-5 space-y-4" x-show="!setCollapsed" x-transition.opacity.duration.150ms>
    <p x-show="reorderError" x-text="reorderError" class="text-sm text-red-700"></p>
    @if ($isSetOwner && ! $setLocked)
        <p class="hidden text-xs text-slate-500 md:block">Tip: drag songs and slots to reorder them.</p>
    @endif

    <div
        class="space-y-4"
        x-ref="songsContainer"
        @dragstart="onSongDragStart($event, Number($event.target.closest('[data-song-id]')?.dataset.songId))"
        @dragover="onSongDragOver($event, Number($event.target.closest('[data-song-id]')?.dataset.songId) || null)"
        @drop="onSongDrop($event)"
        @dragend="onSongDragEnd()"
    >
        @forelse ($set->songs as $song)
            <x-sessions.song-card
                :song="$song"
                :set="$set"
                :users="$users"
                :templates="$templates"
                :slot-options="$slotOptions"
                :pending-slot-assignments="$pendingSlotAssignments"
                :is-set-owner="$isSetOwner"
                :can-manage-set="$canManageSet"
                :can-reorder-songs="$isSetOwner && ! $setLocked"
                :can-move-song-up="! $loop->first"
                :can-move-song-down="! $loop->last"
                :jam-session-closed="$sessionLocked"
            />
        @empty
            <p data-empty-songs-state class="rounded border border-dashed border-slate-300 bg-white/80 p-4 text-sm text-slate-500">No songs in this set yet.</p>
        @endforelse
    </div>

    <x-sessions.song-requests-panel
        :set="$set"
        :templates="$templates"
        :can-manage-set="$canManageSet"
        :is-set-owner="$isSetOwner"
        :set-locked="$setLocked"
    />

    @if ($pendingSlotAssignments->isNotEmpty())
        <div
            class="hidden"
            x-data="{ slotActivityCollapsed: false, pendingSlotActivityCount: {{ $pendingSlotAssignments->count() }}, slotActivityKey: 'backstage:u{{ auth()->id() }}:set:{{ $set->id }}:slot-activity' }"
            x-init="slotActivityCollapsed = localStorage.getItem(slotActivityKey) === '1'"
            x-effect="localStorage.setItem(slotActivityKey, slotActivityCollapsed ? '1' : '0')"
            x-show="pendingSlotActivityCount > 0"
            x-transition
        >
            <div
                class="flex cursor-pointer items-center justify-between gap-2"
                role="button"
                tabindex="0"
                @click="slotActivityCollapsed = !slotActivityCollapsed"
                @keydown.enter.prevent="slotActivityCollapsed = !slotActivityCollapsed"
                @keydown.space.prevent="slotActivityCollapsed = !slotActivityCollapsed"
                x-bind:aria-expanded="(!slotActivityCollapsed).toString()"
                x-bind:title="slotActivityCollapsed ? 'Click to show slot activity' : 'Click to hide slot activity'"
            >
                <h4 class="text-sm font-semibold text-amber-900">Slot requests &amp; recommendations</h4>
                <x-heroicon-m-chevron-down class="h-4 w-4 text-amber-700 transition" x-bind:class="slotActivityCollapsed ? '' : 'rotate-180'" aria-hidden="true" />
            </div>

            <div class="mt-3 space-y-3" x-show="!slotActivityCollapsed" x-transition>
                @foreach ($pendingSlotAssignments as $pendingSlotAssignment)
                    @php
                        $assignment = $pendingSlotAssignment['assignment'];
                        $slot = $pendingSlotAssignment['slot'];
                        $song = $pendingSlotAssignment['song'];
                        $slotLabel = $slotOptions[$slot->name] ?? str($slot->name)->replace('_', ' ')->title();
                        $requestorName = $assignment->actor->name;
                        $targetName = $assignment->target->name;
                        $awaitingTargetConsent = $assignment->status === \App\Models\SlotAssignment::STATUS_AWAITING_TARGET_CONSENT;
                        if (auth()->user() == $assignment->actor) {
                            $requestorName = 'you';
                        }
                        if (auth()->user() == $assignment->target) {
                            $targetName = 'you';
                        }
                        if ($assignment->actor == auth()->user()) {
                            $canRespond = false;
                            $canCancel = $assignment->type === \App\Models\SlotAssignment::TYPE_REQUEST || $awaitingTargetConsent;
                        } elseif ($awaitingTargetConsent) {
                            $canRespond = auth()->user()->is_admin || $assignment->target == auth()->user();
                            $canCancel = false;
                        } else {
                            $canRespond = auth()->user()->is_admin || $set->owner == auth()->user();
                            $canCancel = false;
                        }
                    @endphp
                    <div
                        class="rounded-lg border border-amber-200 bg-white/90 p-4 shadow-sm"
                        x-data="{
                            hidden: false,
                            busy: false,
                            error: '',
                            async respond(status) {
                                this.busy = true;
                                this.error = '';

                                try {
                                    const response = await fetch('{{ route('slot-assignments.respond', $assignment) }}', {
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
                                        let message = 'Could not update assignment. Try again.';

                                        try {
                                            const payload = await response.json();
                                            const validationErrors = Object.values(payload.errors || {}).flat();
                                            message = validationErrors[0] || payload.message || message;
                                        } catch (e) {
                                            message = 'Could not update assignment. Try again.';
                                        }

                                        throw new Error(message);
                                    }

                                    const payload = await response.json();
                                    this.hidden = true;
                                    pendingSlotActivityCount = Math.max(0, pendingSlotActivityCount - 1);
                                    window.dispatchEvent(new CustomEvent('slot-updated', { detail: { slot: payload.slot } }));
                                } catch (e) {
                                    this.error = e.message || 'Could not update assignment. Try again.';
                                } finally {
                                    this.busy = false;
                                }
                            },
                        }"
                        x-show="!hidden"
                        x-transition
                    >
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $song->artist }} - {{ $song->title }}</p>
                            <p class="text-xs text-slate-600">{{ $slotLabel }}</p>
                            @if ($assignment->actor == $assignment->target)
                                <p class="text-sm text-slate-700">{{ ucfirst($requestorName) }} requested this slot.</p>
                            @else
                                <p class="text-sm text-slate-700">{{ ucfirst($requestorName) }} recommended {{ $targetName }} for this slot.</p>
                            @endif
                            @if ($assignment->message)
                                <p class="text-sm text-slate-600">"{{ $assignment->message }}"</p>
                            @endif
                            <p x-show="error" x-text="error" class="text-sm text-rose-700"></p>
                        </div>

                        <div class="mt-3 flex gap-2">
                            @if ($canRespond && ! $setLocked)
                                <button
                                    type="button"
                                    @click="respond('accepted')"
                                    x-bind:disabled="busy"
                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40"
                                    aria-label="Accept assignment"
                                    title="Accept this assignment"
                                >
                                    <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Accept</span>
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
                            @endif
                            @if ($canCancel && ! $setLocked)
                                <button
                                    type="button"
                                    @click="respond('cancelled')"
                                    x-bind:disabled="busy"
                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300 disabled:opacity-40"
                                    aria-label="Cancel assignment"
                                    title="Cancel this assignment"
                                >
                                    <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Cancel</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@if (! $setLocked)
    <x-sessions.set-summary-modal :set="$set" />
@endif

@if ($canEditSet)
    <x-sessions.set-edit-modal
        :set="$set"
        :sessions="$sessions"
        :users="$users"
        :is-admin="$isAdmin"
        :is-admin-managing-other-set="$isAdminManagingOtherSet"
    />
@endif

@if ($canManageSet && ! $setLocked)
    <x-sessions.add-song-modal
        :set="$set"
        :templates="$templates"
        :slot-options="$slotOptions"
        :is-admin-managing-other-set="$isAdminManagingOtherSet"
    />
@endif

@if ($canManageCollaborators)
    <x-sessions.manage-collaborators-modal :set="$set" />
@endif