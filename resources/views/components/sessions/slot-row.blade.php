@props([
    'slotModel',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
])

@php
    $setLocked = $set->performed;
    $noProposableUsersMessage = 'No users are currently available for slot proposals.';
    $proposalUsers = $users
        ->where('id', '!=', auth()->id())
        ->where('hide_from_slot_proposals', false);
@endphp

<tr
    class="border-t border-slate-100 align-top transition hover:bg-slate-50/70"
    x-data="{
        openPropose: false,
        openEditSlot: false,
        assignedUserName: @js($slotModel->assignedPerformerName()),
        slotLabel: @js($slotOptions[$slotModel->name] ?? $slotModel->name),
        slotIsOpen: @js($slotModel->isOpen()),
        assignedToCurrentUser: @js($slotModel->user_id === auth()->id()),
        hasPendingOwnRequest: @js($slotModel->assignments->contains(fn ($a) => $a->status === 'pending' && $a->type === 'request' && $a->actor_user_id === auth()->id())),
        busyAction: false,
        actionError: '',
        actionFeedback: '',
        proposeTargetUserId: @js($proposalUsers->first()?->id),
        proposeMessage: '',
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets'));
        },
        async requestSlot() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch('{{ route('slot-assignments.request', $slotModel) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.actionFeedback = 'Request sent.';
                this.hasPendingOwnRequest = true;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not send request. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async takeSlot() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch('{{ route('slots.take', $slotModel) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not take slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitProposal() {
            if (this.setLocked) {
                return;
            }

            if (!this.proposeTargetUserId) {
                this.actionError = @js($noProposableUsersMessage);
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch('{{ route('slot-assignments.propose', $slotModel) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        target_user_id: this.proposeTargetUserId,
                        message: this.proposeMessage,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.actionFeedback = 'Recommendation sent.';
                this.openPropose = false;
                this.proposeMessage = '';
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not send recommendation. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async releaseSlot() {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch('{{ route('slots.release', $slotModel) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not release slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitEditSlot(event) {
            if (this.setLocked) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch('{{ route('slots.update', $slotModel) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: new FormData(event.target),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.openEditSlot = false;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not save slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async deleteSlot(event) {
            if (this.setLocked) {
                return;
            }

            const confirmed = window.confirm('Delete this slot?');
            if (!confirmed) {
                return;
            }

            this.busyAction = true;
            this.actionError = '';
            this.actionFeedback = '';

            try {
                const response = await fetch('{{ route('slots.destroy', $slotModel) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: new FormData(event.target),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not delete slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        setLocked: @js($setLocked),
    }"
    @keydown.escape.window="openPropose = false; openEditSlot = false"
>
    <td class="px-3 py-3 font-medium text-slate-700" x-text="slotLabel">{{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</td>
    <td class="px-3 py-3">
        <span
            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold shadow-sm"
            x-bind:class="assignedToCurrentUser ? 'border-sky-200 bg-sky-50/90 text-sky-800' : (slotIsOpen ? 'border-amber-200 bg-amber-50/80 text-amber-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800')"
            x-text="assignedUserName"
        >{{ $slotModel->assignedPerformerName() }}</span>
    </td>
    <td class="px-3 py-3">
        <div class="flex flex-wrap gap-2">
            @if ($canManageSet)
                @if (! $setLocked)
                <button
                    type="button"
                    @click="openEditSlot = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md transition focus:outline-none focus:ring-2 {{ auth()->user()->is_admin && ! $isSetOwner ? 'text-rose-600 hover:text-rose-700 focus:ring-rose-400' : 'text-slate-500 hover:text-slate-800 focus:ring-amber-400' }}"
                    aria-label="Edit Slot"
                    title="{{ auth()->user()->is_admin && ! $isSetOwner ? '(ADMIN) Edit Slot' : 'Edit Slot' }}"
                >
                    <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Edit Slot</span>
                </button>
                @endif
            @endif

            @if ($slotModel->user_id === auth()->id() && ! $setLocked)
                <button
                    type="button"
                    @click="releaseSlot()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 opacity-60 transition hover:text-slate-800 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:opacity-100"
                    x-show="assignedToCurrentUser"
                    title="Give up this slot and make it available for others"
                    aria-label="Remove Me"
                    x-bind:disabled="busyAction"
                >
                    <x-heroicon-m-arrow-left-on-rectangle class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Remove Me</span>
                </button>
            @endif

            @if ($set->signups_open && $isSetOwner && $slotModel->user_id !== auth()->id() && ! $setLocked)
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-show="slotIsOpen && !assignedToCurrentUser"
                    @click="takeSlot()"
                    x-bind:disabled="busyAction"
                    aria-label="Take slot"
                    title="Take this slot as set owner"
                >
                    <x-heroicon-m-arrow-down-on-square class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Take Slot</span>
                </button>
            @elseif ($set->signups_open && $slotModel->user_id !== auth()->id() && ! $setLocked)
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-show="slotIsOpen && !assignedToCurrentUser && !hasPendingOwnRequest"
                    @click="requestSlot()"
                    x-bind:disabled="busyAction"
                    aria-label="Request slot"
                    title="Request this slot to be assigned to you. The session owner will need to approve your request."
                >
                    <x-heroicon-m-hand-raised class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Request</span>
                </button>
            @endif

            @if ($set->signups_open && $slotModel->isOpen() && ! $setLocked)
                <button
                    type="button"
                    @click="openPropose = true"
                    x-show="slotIsOpen"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-bind:disabled="busyAction || !proposeTargetUserId"
                    aria-label="Recommend"
                    title="Recommend someone for this slot"
                >
                    <x-heroicon-m-user-plus class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Recommend</span>
                </button>
            @endif
        </div>

        @if (! $setLocked)
        <div x-show="openPropose" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openPropose = false"></div>
        <div x-show="openPropose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                <h6 class="text-base font-semibold text-slate-900">Propose someone for {{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</h6>
                <form @submit.prevent="submitProposal()" class="mt-4 space-y-4">
                    @if ($proposalUsers->isNotEmpty())
                        <div>
                            <x-input-label :value="'User'" />
                            <select x-model="proposeTargetUserId" class="mt-1 w-full rounded-md border-gray-300" required>
                                @foreach ($proposalUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <p class="text-sm text-gray-600">{{ $noProposableUsersMessage }}</p>
                    @endif
                    <div>
                        <x-input-label :value="'Message (optional)'" />
                        <textarea x-model="proposeMessage" rows="3" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openPropose = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button x-bind:disabled="busyAction || !proposeTargetUserId">Send Proposal</x-modal-primary-button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        @if ($canManageSet && ! $setLocked)
            <div x-show="openEditSlot" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSlot = false"></div>
            <div x-show="openEditSlot" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
                    <h6 class="text-base font-semibold text-slate-900">Edit Slot</h6>
                    <form id="edit_slot_form_{{ $slotModel->id }}" method="POST" action="{{ route('slots.update', $slotModel) }}" class="mt-4 space-y-4" @submit.prevent="submitEditSlot($event)">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label :value="'Slot Name'" />
                            <select name="name" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                                @foreach ($slotOptions as $slotValue => $slotLabel)
                                    <option value="{{ $slotValue }}" @selected($slotModel->name === $slotValue)>{{ $slotLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :value="'Assigned User (optional)'" />
                            <select name="user_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                                <option value="">Open</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($slotModel->user_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :value="'Manual Performer Name (optional)'" />
                            <x-text-input name="manual_performer_name" :value="$slotModel->manual_performer_name" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" />
                            <p class="mt-1 text-xs text-slate-500">Use this when the performer does not have an account. If an assigned user is selected, this value is ignored.</p>
                        </div>
                    </form>
                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <form method="POST" action="{{ route('slots.destroy', $slotModel) }}" @submit.prevent="deleteSlot($event)">
                            @csrf
                            @method('DELETE')
                            <x-danger-button type="submit" x-bind:disabled="busyAction">Delete Slot</x-danger-button>
                        </form>
                        <div class="flex justify-end gap-2">
                            <x-modal-secondary-button type="button" @click="openEditSlot = false">Cancel</x-modal-secondary-button>
                            <x-modal-primary-button type="submit" form="edit_slot_form_{{ $slotModel->id }}" x-bind:disabled="busyAction">Save</x-modal-primary-button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-2 space-y-2">
            <p x-show="actionError" x-text="actionError" class="text-xs text-red-700"></p>
            <p x-show="actionFeedback" x-text="actionFeedback" class="text-xs text-emerald-700"></p>
            @foreach ($slotModel->assignments->where('status', 'pending') as $assignment)
                <div
                    class="rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-900"
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
                                    throw new Error('Request failed');
                                }

                                if (status === 'accepted' && targetName) {
                                    assignedUserName = targetName;
                                    slotIsOpen = false;
                                    assignedToCurrentUser = targetIsCurrentUser;
                                }

                                this.hidden = true;
                            } catch (e) {
                                this.error = 'Could not update assignment. Try again.';
                            } finally {
                                this.busy = false;
                            }
                        },
                    }"
                    x-show="!hidden"
                    x-transition
                >
                    <p>
                        @php
                            $requestorName = $assignment->actor->name;
                            $targetName = $assignment->target->name;
                            if (auth()->user() == $assignment->actor)
                            {
                                $requestorName = 'you';
                            }
                            if (auth()->user() == $assignment->target)
                            {
                                $targetName = 'you';
                            }
                        @endphp
                        @if ($assignment->actor == $assignment->target)
                            {{ ucfirst($requestorName) }} requested this slot
                        @else
                            {{ ucfirst($requestorName) }} recommended {{ $targetName }} for this slot
                        @endif
                    </p>
                    @if ($assignment->message)
                        <p class="mt-1">"{{ $assignment->message }}"</p>
                    @endif
                    <p x-show="error" x-text="error" class="mt-2 text-xs text-red-700"></p>
                    <div class="mt-2 flex gap-2">
                        @php
                            // Users who proposed someone else should not respond to assignments, but they can cancel the assignment
                            if ($assignment->actor == auth()->user())
                            {
                                $canRespond = false;
                                $canCancel = true;
                            }
                            else
                            {
                                // Otherwise, admins can do everything, the target user can respond to the proposal.
                                $canRespond = auth()->user()->is_admin || $assignment->target == auth()->user() || $set->owner == auth()->user();
                                $canCancel = false;
                            }
                        @endphp
                        @if ($canRespond && ! $setLocked)
                            <button
                                type="button"
                                @click="respond('accepted', @js($assignment->target->name), @js($assignment->target_user_id === auth()->id()))"
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
                                @click="respond('rejected')"
                                x-bind:disabled="busy"
                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
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
    </td>
</tr>
