@props([
    'set',
    'slotModel',
    'setLocked' => false,
    'jamSessionClosed' => false,
    'canManageSet' => false,
    'isSetOwner' => false,
    'isAdminManagingOtherSet' => false,
    'slotManageMenuItemClass' => 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100',
])

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
        <template x-teleport="body">
        <div
            x-show="openActionMenu"
            x-cloak
            x-transition.origin.top.right
            @click.outside="openActionMenu = false"
            x-bind:style="actionMenuStyle"
            data-session-action-menu
            class="absolute z-[80] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
        >
            @if ($set->signups_open && $canManageSet && $slotModel->user_id !== auth()->id())
                <button
                    type="button"
                    @disabled($jamSessionClosed && !auth()->user()?->is_admin)
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                    x-show="slotIsOpen && !assignedToCurrentUser"
                    @click="openActionMenu = false; takeSlot()"
                    x-bind:disabled="busyAction || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                >
                    @if ($set->free_for_all)
                        <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                    @else
                        <x-heroicon-m-arrow-down-on-square class="h-4 w-4 text-slate-500" aria-hidden="true" />
                    @endif
                    <span>Take this slot</span>
                </button>
            @elseif ($set->signups_open && $set->free_for_all && $slotModel->user_id !== auth()->id())
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                    x-show="slotIsOpen && !assignedToCurrentUser"
                    @click="openActionMenu = false; takeSlot()"
                    x-bind:disabled="busyAction"
                >
                    <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
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
                    @disabled($jamSessionClosed && !auth()->user()?->is_admin)
                    @click="openActionMenu = false; openProposeModal()"
                    x-show="slotIsOpen"
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                    x-bind:disabled="busyAction || proposalUserOptions.length === 0 || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                >
                    <x-heroicon-m-user-plus class="h-4 w-4 text-slate-500" aria-hidden="true" />
                    <span>Recommend someone else</span>
                </button>
            @endif

            @if ($canManageSet)
                <button
                    type="button"
                    @click="openActionMenu = false; clearSlot()"
                    @disabled($jamSessionClosed && !auth()->user()?->is_admin)
                    x-show="!slotIsOpen && !assignedToCurrentUser"
                    x-bind:disabled="busyAction || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 {{ $slotManageMenuItemClass }}"
                >
                    <x-heroicon-m-x-circle class="h-4 w-4" aria-hidden="true" />
                    <span>
                        @if ($isAdminManagingOtherSet)
                            <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                            <span class="sr-only"> Admin action</span>
                        @endif
                        Clear Slot
                    </span>
                </button>
                <button
                    type="button"
                    @click="openActionMenu = false; openEditSlotModal()"
                    @disabled($jamSessionClosed && !auth()->user()?->is_admin)
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 {{ $slotManageMenuItemClass }}"
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
            <button
                type="button"
                @click="openActionMenu = false; copySlotDirectLink()"
                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
            >
                <x-heroicon-m-link class="h-4 w-4 text-slate-500" aria-hidden="true" />
                <span>Copy Direct Link</span>
            </button>
        </div>
        </template>
    </div>
@endif
