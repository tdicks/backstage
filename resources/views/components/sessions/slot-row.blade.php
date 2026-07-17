@props([
    'slotModel',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
])

<tr
    class="border-t border-slate-100 align-top transition hover:bg-slate-50/70"
    x-data="{
        openPropose: false,
        openEditSlot: false,
        assignedUserName: @js($slotModel->user?->name ?? 'Open'),
        slotIsOpen: @js($slotModel->isOpen()),
        assignedToCurrentUser: @js($slotModel->user_id === auth()->id()),
        hasPendingOwnRequest: @js($slotModel->assignments->contains(fn ($a) => $a->status === 'pending' && $a->type === 'request' && $a->actor_user_id === auth()->id())),
        busyAction: false,
        actionError: '',
        actionFeedback: '',
        proposeTargetUserId: @js($users->where('id', '!=', auth()->id())->first()?->id),
        proposeMessage: '',
        async requestSlot() {
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
            } catch (e) {
                this.actionError = 'Could not send request. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async takeSlot() {
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

                assignedUserName = @js(auth()->user()->name);
                slotIsOpen = false;
                assignedToCurrentUser = true;
            } catch (e) {
                this.actionError = 'Could not take slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitProposal() {
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
            } catch (e) {
                this.actionError = 'Could not send recommendation. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
    }"
    @keydown.escape.window="openPropose = false; openEditSlot = false"
>
    <td class="px-3 py-3 font-medium text-slate-700">{{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</td>
    <td class="px-3 py-3">
        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold shadow-sm {{ $slotModel->isOpen() ? 'border-amber-200 bg-amber-50/80 text-amber-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800' }}" x-text="assignedUserName">{{ $slotModel->user?->name ?? 'Open' }}</span>
    </td>
    <td class="px-3 py-3">
        <div class="flex flex-wrap gap-2">
            @if ($slotModel->user_id === auth()->id())
                <form method="POST" action="{{ route('slots.release', $slotModel) }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 opacity-60 transition hover:text-slate-800 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:opacity-100"
                        x-show="assignedToCurrentUser"
                        title="Give up this slot and make it available for others"
                        aria-label="Remove Me"
                    >
                        <x-heroicon-m-arrow-left-on-rectangle class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only">Remove Me</span>
                    </button>
                </form>
            @endif

            @if ($set->signups_open && $isSetOwner && $slotModel->user_id !== auth()->id())
                <x-secondary-button
                    type="button"
                    class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100"
                    x-show="slotIsOpen && !assignedToCurrentUser"
                    @click="takeSlot()"
                    x-bind:disabled="busyAction"
                >Take Slot</x-secondary-button>
            @elseif ($set->signups_open && $slotModel->user_id !== auth()->id())
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

            @if ($set->signups_open && $slotModel->isOpen())
                <button
                    type="button"
                    @click="openPropose = true"
                    x-show="slotIsOpen"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-bind:disabled="busyAction"
                    aria-label="Recommend"
                    title="Recommend someone for this slot"
                >
                    <x-heroicon-m-user-plus class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Recommend</span>
                </button>
            @endif

            @if ($canManageSet)
                <button
                    type="button"
                    @click="openEditSlot = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Edit Slot"
                    title="Edit Slot"
                >
                    <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Edit Slot</span>
                </button>
            @endif
        </div>

        <div x-show="openPropose" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openPropose = false"></div>
        <div x-show="openPropose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                <h6 class="text-base font-semibold text-slate-900">Propose someone for {{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</h6>
                <form @submit.prevent="submitProposal()" class="mt-4 space-y-4">
                    <div>
                        <x-input-label :value="'User'" />
                        <select x-model="proposeTargetUserId" class="mt-1 w-full rounded-md border-gray-300" required>
                            @foreach ($users as $user)
                                @if ($user != auth()->user())
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label :value="'Message (optional)'" />
                        <textarea x-model="proposeMessage" rows="3" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" @click="openPropose = false">Cancel</x-secondary-button>
                        <x-primary-button x-bind:disabled="busyAction">Send Proposal</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        @if ($canManageSet)
            <div x-show="openEditSlot" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSlot = false"></div>
            <div x-show="openEditSlot" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-md rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                    <h6 class="text-base font-semibold text-slate-900">Edit Slot</h6>
                    <form method="POST" action="{{ route('slots.update', $slotModel) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label :value="'Slot Name'" />
                            <select name="name" class="mt-1 w-full rounded-md border-gray-300">
                                @foreach ($slotOptions as $slotValue => $slotLabel)
                                    <option value="{{ $slotValue }}" @selected($slotModel->name === $slotValue)>{{ $slotLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :value="'Assigned User (optional)'" />
                            <select name="user_id" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="">Open</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($slotModel->user_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-secondary-button type="button" @click="openEditSlot = false">Cancel</x-secondary-button>
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('slots.destroy', $slotModel) }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Delete Slot</x-danger-button>
                    </form>
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
                        @if ($canRespond)
                            <x-primary-button type="button" @click="respond('accepted', @js($assignment->target->name), @js($assignment->target_user_id === auth()->id()))" x-bind:disabled="busy">Accept</x-primary-button>
                            <x-danger-button type="button" @click="respond('rejected')" x-bind:disabled="busy">Reject</x-danger-button>
                        @endif
                        @if ($canCancel)
                            <x-danger-button type="button" @click="respond('rejected')" x-bind:disabled="busy">Cancel</x-danger-button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </td>
</tr>
