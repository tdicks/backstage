@props([
    'slotModel',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
    'canReorderSlots' => false,
])

@php
    $setLocked = $set->performed;
    $noProposableUsersMessage = 'No users are currently available for slot proposals.';
    $proposalUsers = $users
        ->where('id', '!=', auth()->id())
        ->where('hide_from_slot_proposals', false);
    $isAdminManagingOtherSet = auth()->user()?->is_admin && ! $isSetOwner;
    $slotManageMenuItemClass = $isAdminManagingOtherSet
        ? 'text-sky-700 hover:bg-sky-50 focus:bg-sky-50'
        : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100';
@endphp

<tr
    id="slot-{{ $slotModel->id }}"
    class="border-t border-slate-100 align-top transition hover:bg-slate-50/70"
    data-slot-id="{{ $slotModel->id }}"
    draggable="{{ $canReorderSlots ? 'true' : 'false' }}"
    @dragstart="onSlotDragStart($event, {{ $slotModel->id }})"
    @dragover="onSlotDragOver($event, {{ $slotModel->id }})"
    @drop="onSlotDrop($event)"
    @dragend="onSlotDragEnd()"
    x-bind:class="{
        'opacity-70': draggingSlotId === {{ $slotModel->id }}
    }"
    x-data="{
        openPropose: false,
        openEditSlot: false,
        openActionMenu: false,
        actionMenuStyle: '',
        assignedUserName: @js($slotModel->assignedPerformerName()),
        slotLabel: @js($slotOptions[$slotModel->name] ?? $slotModel->name),
        slotIsOpen: @js($slotModel->isOpen()),
        initialEditAssignedUserId: @js((string) ($slotModel->user_id ?? '')),
        editAssignedUserId: @js((string) ($slotModel->user_id ?? '')),
        currentUserId: @js((string) auth()->id()),
        assignedToCurrentUser: @js($slotModel->user_id === auth()->id()),
        hasPendingOwnRequest: @js($slotModel->assignments->contains(fn ($a) => $a->status === 'pending' && $a->type === 'request' && $a->actor_user_id === auth()->id())),
        busyAction: false,
        actionError: '',
        actionFeedback: '',
        toast: { visible: false, type: 'error', message: '' },
        toastStyle: '',
        toastTimer: null,
        proposalUserOptions: @js($proposalUsers->map(fn ($user) => ['id' => (string) $user->id, 'name' => $user->name])->values()),
        proposeTargetUserId: '',
        proposeTargetUserQuery: '',
        showProposalUserSuggestions: false,
        proposeMessage: '',
        filteredProposalUsers() {
            const query = this.proposeTargetUserQuery.trim().toLowerCase();
            if (query === '') {
                return [];
            }

            const users = query === ''
                ? this.proposalUserOptions
                : this.proposalUserOptions.filter((user) => user.name.toLowerCase().includes(query));

            return users.slice(0, 8);
        },
        updateProposalUserQuery() {
            const selectedUser = this.proposalUserOptions.find((user) => String(user.id) === String(this.proposeTargetUserId));
            if (!selectedUser || selectedUser.name !== this.proposeTargetUserQuery) {
                this.proposeTargetUserId = '';
            }

            this.showProposalUserSuggestions = true;
        },
        selectProposalUser(user) {
            this.proposeTargetUserId = String(user.id);
            this.proposeTargetUserQuery = user.name;
            this.showProposalUserSuggestions = false;
        },
        shouldShowAssigneeWarning() {
            const selectedUserId = String(this.editAssignedUserId ?? '');
            const initialUserId = String(this.initialEditAssignedUserId ?? '');
            const currentUserId = String(this.currentUserId ?? '');

            return selectedUserId !== initialUserId && selectedUserId !== '' && selectedUserId !== currentUserId;
        },
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets'));
        },
        showToast(type, message) {
            const anchorRect = (this.$refs.toastAnchor || this.$refs.actionMenuButton || this.$el).getBoundingClientRect();
            const viewportPadding = 12;
            const toastWidth = Math.min(384, window.innerWidth - (viewportPadding * 2));
            const left = Math.max(
                viewportPadding,
                Math.min(window.innerWidth - toastWidth - viewportPadding, anchorRect.right - toastWidth)
            );
            const top = Math.max(viewportPadding, anchorRect.top - 4);

            this.toastStyle = `left: ${left}px; top: ${top}px; width: ${toastWidth}px;`;
            this.toast = { visible: true, type, message };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toast.visible = false, 4500);
        },
        async failedResponseMessage(response, fallback) {
            let message = fallback;

            try {
                const payload = await response.json();
                const validationErrors = Object.values(payload.errors || {}).flat();
                message = validationErrors[0] || payload.message || fallback;
            } catch (e) {
                message = fallback;
            }

            if (response.status === 422) {
                this.showToast('error', message);
                return null;
            }

            return message;
        },
        closeSessionModals() {
            this.openPropose = false;
            this.openEditSlot = false;
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            if (shouldOpen) {
                const buttonRect = this.$refs.actionMenuButton.getBoundingClientRect();
                const menuWidth = 288;
                const viewportPadding = 8;
                const left = Math.max(
                    viewportPadding,
                    Math.min(window.innerWidth - menuWidth - viewportPadding, buttonRect.right - menuWidth)
                );
                const top = buttonRect.bottom + viewportPadding;

                this.actionMenuStyle = `left: ${left}px; top: ${top}px; width: ${menuWidth}px;`;
            }
            this.openActionMenu = shouldOpen;
        },
        openProposeModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.proposeTargetUserId = '';
            this.proposeTargetUserQuery = '';
            this.showProposalUserSuggestions = false;
            this.openPropose = true;
        },
        openEditSlotModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.editAssignedUserId = this.initialEditAssignedUserId;
            this.openEditSlot = true;
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
                    const message = await this.failedResponseMessage(response, 'Could not take slot. Try again.');
                    if (message === null) {
                        return;
                    }

                    throw new Error(message);
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = e.message || 'Could not take slot. Try again.';
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
                    const message = await this.failedResponseMessage(response, 'Could not save slot. Try again.');
                    if (message === null) {
                        return;
                    }

                    throw new Error(message);
                }

                this.openEditSlot = false;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = e.message || 'Could not save slot. Try again.';
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
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    x-on:slot-conflict-toast.window="if ($event.detail.slotId === {{ $slotModel->id }}) showToast('error', $event.detail.message)"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <td class="px-3 py-3 font-medium text-slate-700" x-text="slotLabel">{{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</td>
    <td class="px-3 py-3">
        <span
            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold shadow-sm"
            x-bind:class="assignedToCurrentUser ? 'border-sky-200 bg-sky-50/90 text-sky-800' : (slotIsOpen ? 'border-amber-200 bg-amber-50/80 text-amber-800' : 'border-emerald-200 bg-emerald-50/80 text-emerald-800')"
            x-text="assignedUserName"
        >{{ $slotModel->assignedPerformerName() }}</span>
    </td>
    <td x-ref="toastAnchor" class="px-3 py-3 text-right">
        <div class="flex flex-wrap justify-end gap-2">
            @if (! $setLocked && ($canManageSet || $set->signups_open || $slotModel->user_id === auth()->id()))
                <div class="relative">
                    <button
                        type="button"
                        x-ref="actionMenuButton"
                        @click="toggleActionMenu()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                        x-bind:aria-expanded="openActionMenu.toString()"
                        aria-label="Slot actions"
                        title="Slot actions"
                    >
                        <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only">Slot actions</span>
                    </button>
                    <div
                        x-show="openActionMenu"
                        x-cloak
                        x-transition.origin.top.right
                        @click.outside="openActionMenu = false"
                        x-bind:style="actionMenuStyle"
                        class="fixed z-[80] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                    >
                        @if ($set->signups_open && $isSetOwner && $slotModel->user_id !== auth()->id())
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                x-show="slotIsOpen && !assignedToCurrentUser"
                                @click="openActionMenu = false; takeSlot()"
                                x-bind:disabled="busyAction"
                            >
                                <x-heroicon-m-arrow-down-on-square class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                <span>Take this slot</span>
                            </button>
                        @elseif ($set->signups_open && $slotModel->user_id !== auth()->id())
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                x-show="slotIsOpen && !assignedToCurrentUser && !hasPendingOwnRequest"
                                @click="openActionMenu = false; requestSlot()"
                                x-bind:disabled="busyAction"
                            >
                                <x-heroicon-m-hand-raised class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                <span>Request slot</span>
                            </button>
                        @endif

                        @if ($slotModel->user_id === auth()->id())
                            <button
                                type="button"
                                @click="openActionMenu = false; releaseSlot()"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                x-show="assignedToCurrentUser"
                                x-bind:disabled="busyAction"
                            >
                                <x-heroicon-m-arrow-left-on-rectangle class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                <span>Release slot</span>
                            </button>
                        @endif

                        @if ($set->signups_open && $slotModel->isOpen())
                            <button
                                type="button"
                                @click="openActionMenu = false; openProposeModal()"
                                x-show="slotIsOpen"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                x-bind:disabled="busyAction || proposalUserOptions.length === 0"
                            >
                                <x-heroicon-m-user-plus class="h-4 w-4 text-slate-500" aria-hidden="true" />
                                <span>Recommend someone else</span>
                            </button>
                        @endif

                        @if ($canManageSet)
                            <button
                                type="button"
                                @click="openActionMenu = false; openEditSlotModal()"
                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $slotManageMenuItemClass }}"
                            >
                                <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                                <span>
                                    @if ($isAdminManagingOtherSet)
                                        <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                        <span class="sr-only"> Admin action</span>
                                    @endif
                                    Edit slot
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            <template x-teleport="body">
                <div
                    x-show="toast.visible"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]"
                    x-bind:style="toastStyle"
                    class="fixed z-[160] rounded-lg border px-4 py-3 text-left text-sm shadow-xl"
                    x-bind:class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                    role="status"
                >
                    <p class="font-semibold" x-text="toast.type === 'error' ? 'Slot conflict' : 'Slot updated'"></p>
                    <p class="mt-1" x-text="toast.message"></p>
                </div>
            </template>
        </div>

        @if (! $setLocked)
        <div x-show="openPropose" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openPropose = false"></div>
        <div x-show="openPropose" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 text-left text-slate-900 shadow-xl">
                <h6 class="text-base font-semibold text-slate-900">Recommend {{ $slotOptions[$slotModel->name] ?? $slotModel->name }} to someone</h6>
                <form @submit.prevent="submitProposal()" class="mt-4 space-y-4">
                    @if ($proposalUsers->isNotEmpty())
                        <div>
                            <p class="mb-3 text-xs leading-5 text-slate-500">Think someone would enjoy this slot? Recommend it to them!</p>
                            <div class="relative">
                                <x-input-label for="proposal_user_{{ $slotModel->id }}" :value="'Who?'" />
                                <x-text-input
                                    id="proposal_user_{{ $slotModel->id }}"
                                    type="search"
                                    x-model="proposeTargetUserQuery"
                                    @input="updateProposalUserQuery()"
                                    @focus="showProposalUserSuggestions = proposeTargetUserQuery.trim() !== ''"
                                    @keydown.escape="showProposalUserSuggestions = false"
                                    class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                                    autocomplete="off"
                                    required
                                />
                                <div
                                    x-show="showProposalUserSuggestions && filteredProposalUsers().length > 0"
                                    x-cloak
                                    class="absolute z-[120] mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                                    @click.outside="showProposalUserSuggestions = false"
                                >
                                    <template x-for="user in filteredProposalUsers()" :key="user.id">
                                        <button
                                            type="button"
                                            @click="selectProposalUser(user)"
                                            class="w-full px-3 py-2 text-left text-sm text-slate-800 transition hover:bg-amber-50 focus:bg-amber-50 focus:outline-none"
                                            x-text="user.name"
                                        ></button>
                                    </template>
                                </div>
                                <p x-show="showProposalUserSuggestions && proposeTargetUserQuery.trim() !== '' && filteredProposalUsers().length === 0" x-cloak class="mt-1 text-xs text-slate-500">
                                    No matching users are available for recommendations.
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-600">{{ $noProposableUsersMessage }}</p>
                    @endif
                    <div>
                        <x-input-label :value="'Message (optional)'" />
                        <textarea x-model="proposeMessage" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            @if ($isSetOwner)
                                They will get a chance to say yes before the slot changes.
                            @else
                                They will get a chance to say yes first, then the set organiser can give it the final nod.
                            @endif
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openPropose = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button x-bind:disabled="busyAction || !proposeTargetUserId" class="disabled:cursor-not-allowed disabled:opacity-40">Send Proposal</x-modal-primary-button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        @if ($canManageSet && ! $setLocked)
            <div x-show="openEditSlot" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openEditSlot = false"></div>
            <div x-show="openEditSlot" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-left text-slate-900 shadow-2xl">
                    <h6 class="text-base font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                        {{ $isAdminManagingOtherSet ? 'Edit '.$set->owner->name.'\'s Slot' : 'Edit Slot' }}
                    </h6>
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
                            <select name="user_id" x-model="editAssignedUserId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                                <option value="">Open</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($slotModel->user_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <p x-show="shouldShowAssigneeWarning()" x-cloak class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                Make sure you let the assignee know they've been added.
                            </p>
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
                            <x-modal-primary-button type="submit" form="edit_slot_form_{{ $slotModel->id }}" x-bind:disabled="busyAction">
                                @if ($isAdminManagingOtherSet)
                                    <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span class="sr-only">Admin action: </span>
                                @endif
                                Save
                            </x-modal-primary-button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-2 space-y-2 text-left">
            <p x-show="actionError" x-text="actionError" class="text-xs text-red-700"></p>
            <p x-show="actionFeedback" x-text="actionFeedback" class="text-xs text-emerald-700"></p>
            @foreach ($slotModel->assignments->whereIn('status', [\App\Models\SlotAssignment::STATUS_AWAITING_TARGET_CONSENT, \App\Models\SlotAssignment::STATUS_PENDING]) as $assignment)
                <div
                    class="rounded border border-amber-200 bg-amber-50 p-2 text-left text-xs text-amber-900"
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
                                    assignedUserName = targetName;
                                    slotIsOpen = false;
                                    assignedToCurrentUser = targetIsCurrentUser;
                                }

                                this.hidden = true;
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
                    <p>
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
                        @endphp
                        @if ($assignment->actor == $assignment->target)
                            {{ ucfirst($requestorName) }} requested this slot
                        @else
                            {{ ucfirst($requestorName) }} recommended {{ $targetName }} for this slot
                        @endif
                    </p>
                    @if ($awaitingTargetConsent)
                        <p class="mt-1 text-amber-800">Awaiting {{ $targetName }}'s consent.</p>
                    @elseif ($assignment->type === \App\Models\SlotAssignment::TYPE_PROPOSAL)
                        <p class="mt-1 text-amber-800">{{ ucfirst($targetName) }} accepted the recommendation. Awaiting set organiser approval.</p>
                    @endif
                    @if ($assignment->message)
                        <p class="mt-1">"{{ $assignment->message }}"</p>
                    @endif
                    <p x-show="error" x-text="error" class="mt-2 text-xs text-red-700"></p>
                    <div class="mt-2 flex gap-2">
                        @php
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
                        @if ($canRespond && ! $setLocked)
                            <button
                                type="button"
                                @click="respond('accepted', @js($awaitingTargetConsent ? null : $assignment->target->name), @js(! $awaitingTargetConsent && $assignment->target_user_id === auth()->id()))"
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
