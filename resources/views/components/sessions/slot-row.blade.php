@props([
    'slotModel',
    'set',
    'users',
    'assignmentUsers',
    'notGoingUserIds',
    'slotOptions',
    'currentUserId',
    'jamSessionClosed' => false,
    'isSetOwner' => false,
    'canManageSet' => false,
    'canReorderSlots' => false,
    'canMoveSlotUp' => false,
    'canMoveSlotDown' => false,
])

@php
    $setLocked = $set->performed;
    $canEditSlot = ($canManageSet || ($set->session?->jam_manager_id === $currentUserId)) && ! $setLocked;
    $viewer = auth()->user();
    $currentUserNotGoing = in_array((int) $currentUserId, $notGoingUserIds->all(), true);
    $assignedUserIsNotGoing = $slotModel->user_id !== null && in_array((int) $slotModel->user_id, $notGoingUserIds->all(), true);
    $isClaimableManual = $slotModel->user_id !== null && (bool) $slotModel->is_claimable_manual;
    $isClaimableByDropout = $assignedUserIsNotGoing;
    $isSlotClaimable = $isClaimableManual || $isClaimableByDropout;
    $noProposableUsersMessage = 'No users are currently available for slot proposals.';
    $proposalUsers = collect($assignmentUsers)
        ->where('id', '!=', $currentUserId)
        ->filter(function (array $option) use ($users, $viewer) {
            $user = $users->firstWhere('id', (int) $option['id']);

            if (! $user) {
                return false;
            }

            if ($viewer?->is_admin) {
                return true;
            }

            return ! $user->hide_from_slot_proposals;
        })
        ->values();
    $isAdminManagingOtherSet = auth()->user()?->is_admin && ! $isSetOwner;
    $slotManageMenuItemClass = $isAdminManagingOtherSet
        ? 'text-sky-700 hover:bg-sky-50 focus:bg-sky-50'
        : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100';
    $canManageSlotAttachments = $canManageSet || (int) $slotModel->user_id === (int) auth()->id();
@endphp

<tr
    id="slot-{{ $slotModel->id }}"
    class="border-t border-slate-100 align-middle transition hover:bg-slate-50/70 md:align-top"
    data-slot-id="{{ $slotModel->id }}"
    x-bind:draggable="isDesktopReorderEnabled && canReorderSlots && !jamSessionClosed ? 'true' : 'false'"
    @dragstart.stop.self="onSlotDragStart($event, {{ $slotModel->id }})"
    @dragover.stop="onSlotDragOver($event, Number($event.target.closest('[data-slot-id]')?.dataset.slotId) || null)"
    @drop.stop="onSlotDrop($event)"
    @dragend.stop.self="onSlotDragEnd()"
    x-bind:class="{
        'opacity-70': draggingSlotId === {{ $slotModel->id }}
    }"
    x-data="sessionSlotRow(@js([
        'setId' => $set->id,
        'slotId' => $slotModel->id,
        'assignedUserName' => $slotModel->user_id === $currentUserId ? 'You' : $slotModel->assignedPerformerName(),
        'slotLabel' => $slotOptions[$slotModel->name] ?? $slotModel->name,
        'slotNotes' => $slotModel->notes ?? '',
        'slotIsOpen' => $slotModel->isOpen(),
        'slotIsClaimable' => $isSlotClaimable,
        'slotIsManuallyClaimable' => $isClaimableManual,
        'assignedUserIsNotGoing' => $assignedUserIsNotGoing,
        'assignmentIsManual' => ! $slotModel->user_id && filled($slotModel->manual_performer_name),
        'initialEditAssignedUserId' => (string) ($slotModel->user_id ?? ''),
        'initialEditAssignedUserName' => $slotModel->user?->name ?? '',
        'initialEditManualPerformerName' => $slotModel->manual_performer_name ?? '',
        'editAssignedUserId' => (string) ($slotModel->user_id ?? ''),
        'currentUserId' => (string) $currentUserId,
        'currentUserNotGoing' => $currentUserNotGoing,
        'assignedToCurrentUser' => $slotModel->user_id === $currentUserId,
        'hasPendingOwnRequest' => $slotModel->assignments->contains(fn ($a) => $a->status === 'pending' && $a->type === 'request' && $a->actor_user_id === $currentUserId),
        'proposalUserOptions' => $proposalUsers->values(),
        'users' => collect($assignmentUsers)->values(),
        'requestSlotUrl' => route('slot-assignments.request', $slotModel),
        'takeSlotUrl' => route('slots.take', $slotModel),
        'toggleSlotClaimableUrl' => route('slots.claimable', $slotModel),
        'proposeSlotUrl' => route('slot-assignments.propose', $slotModel),
        'releaseSlotUrl' => route('slots.release', $slotModel),
        'updateSlotUrl' => route('slots.update', $slotModel),
        'destroySlotUrl' => route('slots.destroy', $slotModel),
        'slotDirectUrl' => route('sessions.show', $set->session).'#slot-'.$slotModel->id,
        'attachmentsListUrl' => route('slots.attachments.index', $slotModel),
        'attachmentsStoreUrl' => route('slots.attachments.store', $slotModel),
        'canManageAttachments' => $canManageSlotAttachments,
        'slotName' => $slotModel->name,
        'slotPosition' => $slotModel->position,
        'noProposableUsersMessage' => $noProposableUsersMessage,
        'setLocked' => $setLocked,
        'canReorderSlots' => $canReorderSlots,
        'canMoveSlotUp' => $canMoveSlotUp,
        'canMoveSlotDown' => $canMoveSlotDown,
        'csrfToken' => csrf_token(),
    ]))"
    @scroll.window="repositionActionMenu()"
    @resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    x-on:slot-order-changed.window="if ($event.detail.songId === {{ $slotModel->song_id }}) syncMobileSlotOrder()"
    x-on:slot-updated.window="applySlotPayload($event.detail.slot)"
    x-on:slot-conflict-toast.window="if ($event.detail.slotId === {{ $slotModel->id }}) showToast('error', $event.detail.message)"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <td class="px-3 py-3 font-medium text-slate-700">
        <div class="inline-flex items-center gap-2">
            <span x-text="slotLabel">{{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</span>
            @if (($slotModel->attachments_count ?? 0) > 0)
                <button
                    type="button"
                    @click.stop="openAttachmentsModal()"
                    class="inline-flex items-center rounded-sm text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Open slot attachments"
                    title="Open slot attachments"
                >
                    <x-heroicon-m-paper-clip class="h-3.5 w-3.5" aria-hidden="true" />
                    <span class="sr-only">Slot has attachments</span>
                </button>
            @endif
        </div>
        <span
            x-show="slotIsClaimable"
            x-cloak
            class="mt-1 inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-violet-700 md:hidden"
            title="This slot can be claimed"
        >
            Claimable
        </span>
        <p x-show="slotNotes" x-cloak x-text="slotNotes" class="mt-1 text-xs font-normal leading-5 text-slate-500 whitespace-pre-wrap">{{ $slotModel->notes }}</p>
    </td>
    <td class="px-3 py-3">
        <div class="inline-flex items-center gap-2">
            <x-sessions.slot-assignee-pill :slot-model="$slotModel" :can-edit-slot="$canEditSlot" />
            <span
                x-show="slotIsClaimable"
                x-cloak
                class="hidden items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-violet-700 md:inline-flex"
                title="This slot can be claimed"
            >
                Claimable
            </span>
        </div>
    </td>
    <td x-ref="toastAnchor" class="relative px-3 py-3 text-right">
        <div class="flex items-center justify-end gap-2 md:items-start">
            @if ($canReorderSlots)
                <div class="inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden">
                    <button
                        type="button"
                        @disabled(! $canMoveSlotUp || ($jamSessionClosed && !auth()->user()?->is_admin))
                        @click.prevent="window.dispatchEvent(new CustomEvent('mobile-slot-move', { detail: { songId: {{ $slotModel->song_id }}, slotId: {{ $slotModel->id }}, direction: -1 } }))"
                        x-bind:disabled="!canMoveSlotUp || busyAction || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                        class="inline-flex h-5 items-center justify-center transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Move slot up"
                        title="Move slot up"
                    >
                        <x-heroicon-m-chevron-up class="h-3 w-3" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        @disabled(! $canMoveSlotDown || ($jamSessionClosed && !auth()->user()?->is_admin))
                        @click.prevent="window.dispatchEvent(new CustomEvent('mobile-slot-move', { detail: { songId: {{ $slotModel->song_id }}, slotId: {{ $slotModel->id }}, direction: 1 } }))"
                        x-bind:disabled="!canMoveSlotDown || busyAction || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                        class="inline-flex h-5 items-center justify-center border-t border-slate-200 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Move slot down"
                        title="Move slot down"
                    >
                        <x-heroicon-m-chevron-down class="h-3 w-3" aria-hidden="true" />
                    </button>
                </div>
            @endif
            <x-sessions.slot-action-menu
                :set="$set"
                :slot-model="$slotModel"
                :set-locked="$setLocked"
                :jam-session-closed="$jamSessionClosed"
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

        <div
            x-show="actionFeedback"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="pointer-events-none mt-2 flex justify-start"
        >
            <div class="rounded-md border border-emerald-200 bg-emerald-50/95 px-3 py-1.5 text-xs font-medium tracking-wide text-emerald-800 whitespace-nowrap shadow-sm shadow-emerald-200/70 backdrop-blur-sm">
                <span x-text="actionFeedback"></span>
            </div>
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
            :can-manage-set="$canEditSlot"
            :set-locked="$setLocked"
            :is-admin-managing-other-set="$isAdminManagingOtherSet"
            :set="$set"
            :slot-model="$slotModel"
            :slot-options="$slotOptions"
            :users="$users"
        />

        <x-sessions.attachments-modal />

        <div class="mt-2 hidden flex-wrap justify-start gap-1.5 text-left md:flex">
            <p x-show="actionError" x-text="actionError" class="text-xs text-red-700"></p>
            <x-sessions.slot-assignment-pills :slot-model="$slotModel" :set="$set" :set-locked="$setLocked" />
        </div>
    </td>
</tr>
