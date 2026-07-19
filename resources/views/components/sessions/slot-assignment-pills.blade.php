@props([
    'slotModel',
    'set',
    'setLocked' => false,
])

@foreach ($slotModel->assignments->whereIn('status', [\App\Models\SlotAssignment::STATUS_AWAITING_TARGET_CONSENT, \App\Models\SlotAssignment::STATUS_PENDING]) as $assignment)
    <div
        class="inline-flex max-w-full flex-wrap items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50/80 px-2 py-1 text-left text-xs text-amber-900 shadow-sm"
        x-data="{
            hidden: false,
            busy: false,
            error: '',
            async respond(status, targetName = null, targetIsCurrentUser = false) {
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

                        if (response.status === 422) {
                            window.dispatchEvent(new CustomEvent('slot-conflict-toast', {
                                detail: {
                                    slotId: {{ $slotModel->id }},
                                    message,
                                },
                            }));

                            return;
                        }

                        throw new Error(message);
                    }

                    if (status === 'accepted' && targetName) {
                        assignedUserName = targetIsCurrentUser ? 'You' : targetName;
                        slotIsOpen = false;
                        assignedToCurrentUser = targetIsCurrentUser;
                        assignmentIsManual = false;
                    }

                    this.hidden = true;
                    this.refreshSessionSets();
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
        @php
            $requestorName = $assignment->actor->name;
            $targetName = $assignment->target->name;
            $awaitingTargetConsent = $assignment->status === \App\Models\SlotAssignment::STATUS_AWAITING_TARGET_CONSENT;
            if (auth()->user() == $assignment->actor)
            {
                $requestorName = 'you';
            }
            if (auth()->user() == $assignment->target)
            {
                $targetName = 'you';
            }
            if ($assignment->actor == auth()->user())
            {
                $canRespond = false;
                $canCancel = $assignment->type === \App\Models\SlotAssignment::TYPE_REQUEST || $awaitingTargetConsent;
            }
            elseif ($awaitingTargetConsent)
            {
                $canRespond = auth()->user()->is_admin || $assignment->target == auth()->user();
                $canCancel = false;
            }
            else
            {
                $canRespond = auth()->user()->is_admin || $set->owner == auth()->user();
                $canCancel = false;
            }
        @endphp
        <span class="inline-flex items-center gap-1 font-medium">
            @if ($assignment->actor == $assignment->target)
                <x-heroicon-m-hand-raised class="h-3.5 w-3.5 text-amber-700" aria-hidden="true" />
                <span>{{ ucfirst($requestorName) }} requested this</span>
            @else
                <x-heroicon-m-user-plus class="h-3.5 w-3.5 text-amber-700" aria-hidden="true" />
                <span>{{ ucfirst($requestorName) }} recommended {{ $targetName }}</span>
            @endif
        </span>
        @if ($assignment->message)
            <span class="max-w-48 truncate text-amber-800" title="{{ $assignment->message }}">"{{ $assignment->message }}"</span>
        @endif
        <p x-show="error" x-text="error" class="basis-full text-xs text-red-700"></p>
        <div class="inline-flex gap-1">
            @if ($canRespond && ! $setLocked)
                <button
                    type="button"
                    @click="respond('accepted', @js($awaitingTargetConsent ? null : $assignment->target->name), @js(! $awaitingTargetConsent && $assignment->target_user_id === auth()->id()))"
                    x-bind:disabled="busy"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/80 text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40"
                    aria-label="Accept assignment"
                    title="Accept this assignment"
                >
                    <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    @click="respond('rejected')"
                    x-bind:disabled="busy"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/80 text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                    aria-label="Reject assignment"
                    title="Reject this assignment"
                >
                    <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
            @endif
            @if ($canCancel && ! $setLocked)
                <button
                    type="button"
                    @click="respond('rejected')"
                    x-bind:disabled="busy"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/80 text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                    aria-label="Cancel assignment"
                    title="Cancel this assignment"
                >
                    <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
            @endif
        </div>
    </div>
@endforeach
