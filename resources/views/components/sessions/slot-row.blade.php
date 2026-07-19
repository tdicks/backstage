@props([
    'slotModel',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
    'canReorderSlots' => false,
    'canMoveSlotUp' => false,
    'canMoveSlotDown' => false,
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
    x-bind:draggable="isDesktopReorderEnabled && canReorderSlots ? 'true' : 'false'"
    @dragstart.stop="onSlotDragStart($event, {{ $slotModel->id }})"
    @dragover.stop="onSlotDragOver($event, {{ $slotModel->id }})"
    @drop.stop="onSlotDrop($event)"
    @dragend.stop="onSlotDragEnd()"
    x-bind:class="{
        'opacity-70': draggingSlotId === {{ $slotModel->id }}
    }"
    x-data="{
        openPropose: false,
        openEditSlot: false,
        openActionMenu: false,
        actionMenuStyle: '',
        assignedUserName: @js($slotModel->user_id === auth()->id() ? 'You' : $slotModel->assignedPerformerName()),
        slotLabel: @js($slotOptions[$slotModel->name] ?? $slotModel->name),
        slotIsOpen: @js($slotModel->isOpen()),
        assignmentIsManual: @js(! $slotModel->user_id && filled($slotModel->manual_performer_name)),
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
        isDesktopReorderEnabled: window.matchMedia('(min-width: 768px)').matches,
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
            window.dispatchEvent(new CustomEvent('refresh-session-activity'));
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
        positionActionMenu() {
            const buttonRect = this.$refs.actionMenuButton.getBoundingClientRect();
            const viewportPadding = 8;
            const menuWidth = Math.min(288, window.innerWidth - (viewportPadding * 2));
            const left = window.scrollX + Math.max(
                viewportPadding,
                Math.min(window.innerWidth - menuWidth - viewportPadding, buttonRect.right - menuWidth)
            );
            const top = window.scrollY + buttonRect.bottom + viewportPadding;

            this.actionMenuStyle = `left: ${left}px; top: ${top}px; width: ${menuWidth}px;`;
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            if (shouldOpen) {
                this.positionActionMenu();
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
        async clearSlot() {
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
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        _method: 'PATCH',
                        name: @js($slotModel->name),
                        user_id: null,
                        manual_performer_name: '',
                        position: @js($slotModel->position),
                    }),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not clear slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async copySlotDirectLink() {
            await window.copyShareLink(@js(route('sessions.show', $set->session).'#slot-'.$slotModel->id));
            this.actionFeedback = 'Direct link copied.';
            setTimeout(() => {
                if (this.actionFeedback === 'Direct link copied.') {
                    this.actionFeedback = '';
                }
            }, 1800);
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
        <x-sessions.slot-assignee-pill :slot-model="$slotModel" />
    </td>
    <td x-ref="toastAnchor" class="px-3 py-3 text-right">
        <div class="flex flex-wrap justify-end gap-2">
            @if ($canReorderSlots)
                <div class="flex items-center gap-1 md:hidden">
                    @if ($canMoveSlotUp)
                        <button
                            type="button"
                            @click.prevent="window.dispatchEvent(new CustomEvent('mobile-slot-move', { detail: { songId: {{ $slotModel->song_id }}, slotId: {{ $slotModel->id }}, direction: -1 } }))"
                            x-bind:disabled="busyAction"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Move slot up"
                            title="Move slot up"
                        >
                            <x-heroicon-m-chevron-up class="h-4 w-4" aria-hidden="true" />
                        </button>
                    @endif
                    @if ($canMoveSlotDown)
                        <button
                            type="button"
                            @click.prevent="window.dispatchEvent(new CustomEvent('mobile-slot-move', { detail: { songId: {{ $slotModel->song_id }}, slotId: {{ $slotModel->id }}, direction: 1 } }))"
                            x-bind:disabled="busyAction"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Move slot down"
                            title="Move slot down"
                        >
                            <x-heroicon-m-chevron-down class="h-4 w-4" aria-hidden="true" />
                        </button>
                    @endif
                </div>
            @endif
            <x-sessions.slot-action-menu
                :set="$set"
                :slot-model="$slotModel"
                :set-locked="$setLocked"
                :can-manage-set="$canManageSet"
                :is-set-owner="$isSetOwner"
                :is-admin-managing-other-set="$isAdminManagingOtherSet"
                :slot-manage-menu-item-class="$slotManageMenuItemClass"
            />

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

        <x-sessions.slot-propose-modal
            :set-locked="$setLocked"
            :slot-model="$slotModel"
            :slot-options="$slotOptions"
            :proposal-users="$proposalUsers"
            :is-set-owner="$isSetOwner"
            :no-proposable-users-message="$noProposableUsersMessage"
        />

        <x-sessions.slot-edit-modal
            :can-manage-set="$canManageSet"
            :set-locked="$setLocked"
            :is-admin-managing-other-set="$isAdminManagingOtherSet"
            :set="$set"
            :slot-model="$slotModel"
            :slot-options="$slotOptions"
            :users="$users"
        />

        <div class="mt-2 flex flex-wrap justify-start gap-1.5 text-left">
            <p x-show="actionError" x-text="actionError" class="text-xs text-red-700"></p>
            <p x-show="actionFeedback" x-text="actionFeedback" class="text-xs text-emerald-700"></p>
            <x-sessions.slot-assignment-pills :slot-model="$slotModel" :set="$set" :set-locked="$setLocked" />
        </div>
    </td>
</tr>
