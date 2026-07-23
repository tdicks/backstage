@props([
    'song',
    'set',
    'users',
    'slotOptions',
    'pendingSlotAssignments' => [],
    'jamSessionClosed' => false,
    'isSetOwner' => false,
    'canManageSet' => false,
    'canReorderSongs' => false,
    'canMoveSongUp' => false,
    'canMoveSongDown' => false,
])

@php
    $setLocked = $set->performed;
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->is_admin;
    $isAdminManagingOtherSet = $isAdmin && ! $isSetOwner;
    $songActionMenuItemClass = $isAdminManagingOtherSet
        ? 'text-sky-700 hover:bg-sky-50 focus:bg-sky-50'
        : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100';
@endphp

<article
    id="song-{{ $song->id }}"
    data-session-song-card
    data-song-opened-event="session-song-opened"
    class="rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow-md"
    data-song-id="{{ $song->id }}"
    x-bind:data-song-open="(!songCollapsed).toString()"
    x-data="sessionSongCard(@js([
        'songKey' => 'backstage:u'.auth()->id().':song:'.$song->id,
        'canReorderSongs' => $canReorderSongs,
        'isAdminUser' => auth()->user()?->is_admin ?? false,
        'jamSessionClosed' => $jamSessionClosed,
        'setLocked' => $setLocked,
        'songDirectUrl' => route('sessions.show', $set->session).'#song-'.$song->id,
        'songsReorderUrl' => route('songs.reorder', $set),
        'slotsReorderUrl' => route('slots.reorder', $song),
        'slotsStoreUrl' => route('slots.store', $song),
        'csrfToken' => csrf_token(),
    ]))"
    x-init="songCollapsed = localStorage.getItem(songKey) === '1'"
    x-effect="localStorage.setItem(songKey, songCollapsed ? '1' : '0')"
    x-on:song-reorder-start.window="if ($event.detail.setId === {{ $set->id }}) mobileSongReorderBusy = true"
    x-on:song-reorder-complete.window="if ($event.detail.setId === {{ $set->id }}) mobileSongReorderBusy = false"
    x-on:mobile-slot-move.window="if ($event.detail.songId === {{ $song->id }}) moveSlot($event.detail.slotId, $event.detail.direction)"
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    @scroll.window="repositionActionMenu()"
    @resize.window="repositionActionMenu(); syncDesktopReorderEnabled()"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <template x-teleport="body">
        <div
            x-show="toast.visible"
            x-cloak
            x-transition.opacity.duration.200ms
            class="fixed right-4 top-20 z-[160] max-w-sm rounded-lg border px-4 py-3 text-sm shadow-xl"
            x-bind:class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
            role="status"
        >
            <p class="font-semibold" x-text="toast.type === 'error' ? 'Slot conflict' : 'Slot updated'"></p>
            <p class="mt-1" x-text="toast.message"></p>
        </div>
    </template>

    <div
        data-song-drag-handle
        x-bind:draggable="isDesktopReorderEnabled && canReorderSongs && !(jamSessionClosed && !isAdminUser) ? 'true' : 'false'"
        class="flex cursor-pointer select-none flex-wrap items-center justify-between gap-3 md:items-start md:!cursor-grab md:active:!cursor-grabbing"
        @click="toggleSongCollapsed()"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="toggleSongCollapsed()"
        @keydown.space.prevent="toggleSongCollapsed()"
        x-bind:aria-expanded="(!songCollapsed).toString()"
        x-bind:title="songCollapsed ? 'Click to show song slots and assignments' : 'Click to hide song slots and assignments'"
        aria-label="Toggle song details"
    >
        <div class="min-w-0 flex-1">
            <h4 class="text-base font-semibold text-slate-900">{{ $song->artist }} - {{ $song->title }}</h4>
            @if ($song->notes)
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $song->notes }}</p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2" @click.stop>
            @if ($isSetOwner && ! $setLocked)
                <div class="inline-flex w-7 flex-col overflow-hidden rounded-md border border-slate-200 bg-white text-slate-500 md:hidden">
                    <button
                        type="button"
                        @disabled(! $canMoveSongUp || ($jamSessionClosed && !auth()->user()?->is_admin))
                        @click.prevent="if (!mobileSongReorderBusy) { mobileSongReorderBusy = true; window.dispatchEvent(new CustomEvent('mobile-song-move', { detail: { setId: {{ $set->id }}, songId: {{ $song->id }}, direction: -1 } })) }"
                        x-bind:disabled="{{ $canMoveSongUp ? 'false' : 'true' }} || mobileSongReorderBusy || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                        class="inline-flex h-5 items-center justify-center transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Move song up"
                        title="Move song up"
                    >
                        <x-heroicon-m-chevron-up class="h-3 w-3" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        @disabled(! $canMoveSongDown || ($jamSessionClosed && !auth()->user()?->is_admin))
                        @click.prevent="if (!mobileSongReorderBusy) { mobileSongReorderBusy = true; window.dispatchEvent(new CustomEvent('mobile-song-move', { detail: { setId: {{ $set->id }}, songId: {{ $song->id }}, direction: 1 } })) }"
                        x-bind:disabled="{{ $canMoveSongDown ? 'false' : 'true' }} || mobileSongReorderBusy || ({{ $jamSessionClosed ? 'true' : 'false' }} && {{ auth()->user()?->is_admin ? 'false' : 'true' }})"
                        class="inline-flex h-5 items-center justify-center border-t border-slate-200 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Move song down"
                        title="Move song down"
                    >
                        <x-heroicon-m-chevron-down class="h-3 w-3" aria-hidden="true" />
                    </button>
                </div>
            @endif
            <div class="relative">
                <button
                    type="button"
                    x-ref="actionMenuButton"
                    @click="toggleActionMenu()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    x-bind:aria-expanded="openActionMenu.toString()"
                    aria-label="Song actions"
                    title="Song actions"
                >
                    <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Song actions</span>
                </button>
                <template x-teleport="body">
                    <div
                        x-show="openActionMenu"
                        x-cloak
                        x-transition.origin.top.right
                        @click.outside="openActionMenu = false"
                        x-bind:style="actionMenuStyle"
                        data-session-action-menu
                        class="z-[80] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                    >
                    @if ($canManageSet)
                        <button
                            type="button"
                            @click="openActionMenu = false; openAddSlotModal()"
                            @disabled($setLocked || ($jamSessionClosed && !auth()->user()?->is_admin))
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $songActionMenuItemClass }} disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Add Slot
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="openActionMenu = false; openEditSongModal()"
                            @disabled($setLocked || ($jamSessionClosed && !auth()->user()?->is_admin))
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $songActionMenuItemClass }} disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Edit Song
                            </span>
                        </button>
                    @endif
                    <button
                        type="button"
                        @click="openActionMenu = false; copySongDirectLink()"
                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    >
                        <x-heroicon-m-link class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        <span>Copy Direct Link</span>
                    </button>
                    </div>
                </template>
                <div
                    x-show="directLinkCopied"
                    x-transition.opacity.duration.150ms
                    x-cloak
                    role="status"
                    aria-live="polite"
                    class="absolute right-0 top-full z-[80] mt-2 whitespace-nowrap rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900 shadow-lg"
                >
                    Direct link copied
                </div>
            </div>
        </div>
    </div>

    @if ($canManageSet && ! $setLocked)
        <div x-show="openEditSong" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openEditSong = false"></div>
        <div x-show="openEditSong" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl" @click.stop>
                <h5 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                    {{ $isAdminManagingOtherSet ? 'Edit '.$set->owner->name.'\'s Song' : 'Edit Song' }}
                </h5>
                <form id="edit_song_form_{{ $song->id }}" method="POST" action="{{ route('songs.update', $song) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label :value="'Artist'" />
                            <x-text-input name="artist" :value="$song->artist" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                        </div>
                        <div>
                            <x-input-label :value="'Title'" />
                            <x-text-input name="title" :value="$song->title" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label :value="'Notes'" />
                        <x-textarea-input name="notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ $song->notes }}</x-textarea-input>
                    </div>
                </form>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <form method="POST" action="{{ route('songs.destroy', $song) }}" onsubmit="return confirm('Delete this song from the set? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Delete Song</x-danger-button>
                    </form>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openEditSong = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button type="submit" form="edit_song_form_{{ $song->id }}">
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

        <div x-show="openAddSlot" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openAddSlot = false"></div>
        <div x-show="openAddSlot" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 text-slate-900 shadow-xl" @click.stop>
                <h5 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                    {{ $isAdminManagingOtherSet ? 'Add Slot to '.$set->owner->name.'\'s Song' : 'Add Slot' }}
                </h5>
                <form method="POST" action="{{ route('slots.store', $song) }}" class="mt-4 space-y-4" @submit.prevent="submitAddSlot($event)">
                    @csrf
                    <div>
                        <x-input-label :value="'Slot Name'" />
                        <select name="name" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" required>
                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                <option value="{{ $slotValue }}">{{ $slotLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openAddSlot = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button x-bind:disabled="busyAction">
                            @if ($isAdminManagingOtherSet)
                                <x-admin-shield-icon class="mr-1 inline h-4 w-4 text-sky-500" aria-hidden="true" />
                                <span class="sr-only">Admin action: </span>
                            @endif
                            Add Slot
                        </x-modal-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white/80" x-show="!songCollapsed" x-transition>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-slate-600">
                    <th class="px-3 py-2">Slot</th>
                    <th class="px-3 py-2">Assigned</th>
                    <th class="px-3 py-2 text-right"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody x-ref="slotsContainer" data-song-slots-body data-song-slots-id="{{ $song->id }}" @dragover.stop="onSlotDragOver($event)" @drop.stop="onSlotDrop($event)">
                <x-sessions.song-slots
                    :song="$song"
                    :set="$set"
                    :users="$users"
                    :slot-options="$slotOptions"
                    :current-user-id="auth()->id()"
                    :jam-session-closed="$jamSessionClosed"
                    :is-set-owner="$isSetOwner"
                    :can-manage-set="$canManageSet"
                />
            </tbody>
        </table>
    </div>

    @php
        $songPendingSlotAssignments = collect($pendingSlotAssignments)->filter(fn ($assignment) => $assignment['song']->id === $song->id)->values();
    @endphp

    @if ($songPendingSlotAssignments->isNotEmpty())
        <div class="mt-4 space-y-3 rounded-md border border-amber-200 bg-amber-50/80 p-4 md:hidden" x-show="!songCollapsed" x-transition>
            @foreach ($songPendingSlotAssignments as $pendingSlotAssignment)
                @php
                    $assignment = $pendingSlotAssignment['assignment'];
                    $slot = $pendingSlotAssignment['slot'];
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

                                this.hidden = true;
                                window.dispatchEvent(new CustomEvent('refresh-session-sets'));
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
                        <p class="text-xs text-slate-600">{{ $slotLabel }}</p>
                        @if ($assignment->actor == $assignment->target)
                            <p class="text-sm text-slate-700">{{ ucfirst($requestorName) }} requested this slot.</p>
                        @else
                            <p class="text-sm text-slate-700">{{ ucfirst($requestorName) }} recommended {{ $targetName }} for this slot.</p>
                        @endif
                        @if ($assignment->message)
                            <p class="text-sm text-slate-600">"{{ $assignment->message }}"</p>
                        @endif
                        <p x-show="error" x-text="error" class="text-sm text-rose-700" x-cloak></p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($canRespond)
                            <button
                                type="button"
                                @click="respond('accepted')"
                                x-bind:disabled="busy"
                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:opacity-40"
                                aria-label="Accept slot assignment"
                                title="Accept"
                            >
                                <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                                <span>Accept</span>
                            </button>
                            <button
                                type="button"
                                @click="respond('declined')"
                                x-bind:disabled="busy"
                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400 disabled:opacity-40"
                                aria-label="Decline slot assignment"
                                title="Decline"
                            >
                                <x-heroicon-m-x-mark class="h-3.5 w-3.5" aria-hidden="true" />
                                <span>Decline</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</article>
