@props([
    'slotModel',
    'set',
    'users',
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
    $noProposableUsersMessage = 'No users are currently available for slot proposals.';
    $proposalUsers = $users
        ->where('id', '!=', $currentUserId)
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
    x-bind:draggable="isDesktopReorderEnabled && canReorderSlots && !jamSessionClosed ? 'true' : 'false'"
    @dragstart.self="onSlotDragStart($event, {{ $slotModel->id }})"
    @dragover.stop="onSlotDragOver($event, {{ $slotModel->id }})"
    @drop.stop="onSlotDrop($event)"
    @dragend.self="onSlotDragEnd()"
    x-bind:class="{
        'opacity-70': draggingSlotId === {{ $slotModel->id }}
    }"
    x-data="sessionSlotRow(@js([
        'assignedUserName' => $slotModel->user_id === $currentUserId ? 'You' : $slotModel->assignedPerformerName(),
        'slotLabel' => $slotOptions[$slotModel->name] ?? $slotModel->name,
        'slotIsOpen' => $slotModel->isOpen(),
        'assignmentIsManual' => ! $slotModel->user_id && filled($slotModel->manual_performer_name),
        'initialEditAssignedUserId' => (string) ($slotModel->user_id ?? ''),
        'initialEditAssignedUserName' => $slotModel->user?->name ?? '',
        'initialEditManualPerformerName' => $slotModel->manual_performer_name ?? '',
        'editAssignedUserId' => (string) ($slotModel->user_id ?? ''),
        'currentUserId' => (string) $currentUserId,
        'assignedToCurrentUser' => $slotModel->user_id === $currentUserId,
        'hasPendingOwnRequest' => $slotModel->assignments->contains(fn ($a) => $a->status === 'pending' && $a->type === 'request' && $a->actor_user_id === $currentUserId),
        'proposalUserOptions' => $proposalUsers->map(fn ($user) => ['id' => (string) $user->id, 'name' => $user->name])->values(),
        'users' => $users->map(fn ($user) => ['id' => (string) $user->id, 'name' => $user->name])->values(),
        'requestSlotUrl' => route('slot-assignments.request', $slotModel),
        'takeSlotUrl' => route('slots.take', $slotModel),
        'proposeSlotUrl' => route('slot-assignments.propose', $slotModel),
        'releaseSlotUrl' => route('slots.release', $slotModel),
        'updateSlotUrl' => route('slots.update', $slotModel),
        'destroySlotUrl' => route('slots.destroy', $slotModel),
        'slotDirectUrl' => route('sessions.show', $set->session).'#slot-'.$slotModel->id,
        'slotName' => $slotModel->name,
        'slotPosition' => $slotModel->position,
        'noProposableUsersMessage' => $noProposableUsersMessage,
        'setLocked' => $setLocked,
        'canReorderSlots' => $canReorderSlots,
        'csrfToken' => csrf_token(),
    ]))"
    @scroll.window="repositionActionMenu()"
    @resize.window="repositionActionMenu()"
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    x-on:slot-conflict-toast.window="if ($event.detail.slotId === {{ $slotModel->id }}) showToast('error', $event.detail.message)"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <td class="px-3 py-3 font-medium text-slate-700" x-text="slotLabel">{{ $slotOptions[$slotModel->name] ?? $slotModel->name }}</td>
    <td class="px-3 py-3">
        <x-sessions.slot-assignee-pill :slot-model="$slotModel" :can-edit-slot="$canEditSlot" />
    </td>
    <td x-ref="toastAnchor" class="px-3 py-3 text-right">
        <div class="flex flex-wrap justify-end gap-2">
            @if ($canReorderSlots)
                <div class="flex items-center gap-1 md:hidden">
                    @if ($canMoveSlotUp)
                        <button
                            type="button"
                            @disabled($jamSessionClosed && !auth()->user()?->is_admin)
                            @click.prevent="window.dispatchEvent(new CustomEvent('mobile-slot-move', { detail: { songId: {{ $slotModel->song_id }}, slotId: {{ $slotModel->id }}, direction: -1 } }))"
                            x-bind:disabled="busyAction || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
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
                            @disabled($jamSessionClosed && !auth()->user()?->is_admin)
                            @click.prevent="window.dispatchEvent(new CustomEvent('mobile-slot-move', { detail: { songId: {{ $slotModel->song_id }}, slotId: {{ $slotModel->id }}, direction: 1 } }))"
                            x-bind:disabled="busyAction || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
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

        <div class="mt-2 hidden flex-wrap justify-start gap-1.5 text-left md:flex">
            <p x-show="actionError" x-text="actionError" class="text-xs text-red-700"></p>
            <p x-show="actionFeedback" x-text="actionFeedback" class="text-xs text-emerald-700"></p>
            <x-sessions.slot-assignment-pills :slot-model="$slotModel" :set="$set" :set-locked="$setLocked" />
        </div>
    </td>
</tr>
