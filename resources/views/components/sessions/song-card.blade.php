@props([
    'song',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
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
    class="rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow-md"
    data-song-id="{{ $song->id }}"
    draggable="{{ $isSetOwner && ! $setLocked ? 'true' : 'false' }}"
    @dragstart="onSongDragStart($event, {{ $song->id }})"
    @dragover="onSongDragOver($event, {{ $song->id }})"
    @drop="onSongDrop($event)"
    x-bind:class="{
        'opacity-70': draggingSongId === {{ $song->id }}
    }"
    x-data="{
        openEditSong: false,
        openAddSlot: false,
        openActionMenu: false,
        songCollapsed: false,
        songKey: 'backstage:u{{ auth()->id() }}:song:{{ $song->id }}',
        busyAction: false,
        actionError: '',
        canReorderSlots: @js($canManageSet && ! $setLocked),
        dragSlotId: null,
        draggingSlotId: null,
        dropTargetSlotId: null,
        hasOpenDragBlockingModal() {
            return Array.from(document.querySelectorAll('[data-drag-blocking-modal]')).some((el) => window.getComputedStyle(el).display !== 'none');
        },
        canDragSlots() {
            return this.canReorderSlots && !this.hasOpenDragBlockingModal();
        },
        refreshSessionSets() {
            window.dispatchEvent(new CustomEvent('refresh-session-sets'));
        },
        closeSessionModals() {
            this.openEditSong = false;
            this.openAddSlot = false;
        },
        closeSessionActionMenus() {
            this.openActionMenu = false;
        },
        toggleActionMenu() {
            const shouldOpen = !this.openActionMenu;
            window.dispatchEvent(new CustomEvent('close-session-action-menus'));
            this.openActionMenu = shouldOpen;
        },
        openEditSongModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openEditSong = true;
        },
        openAddSlotModal() {
            window.dispatchEvent(new CustomEvent('close-session-modals'));
            this.openAddSlot = true;
        },
        clearSlotDropPlaceholder() {
            this.$refs.slotDropPlaceholder?.classList.add('hidden');
        },
        onSlotDragStart(event, slotId) {
            if (!this.canDragSlots()) {
                event.preventDefault();
                return;
            }

            this.dragSlotId = slotId;
            this.draggingSlotId = slotId;
            this.dropTargetSlotId = null;

            const slotsContainer = this.$refs.slotsContainer;
            const draggedEl = slotsContainer ? slotsContainer.querySelector(`[data-slot-id='${slotId}']`) : null;

            if (draggedEl && event.dataTransfer) {
                event.dataTransfer.setDragImage(draggedEl, 24, 16);
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(slotId));
        },
        onSlotDragEnd() {
            this.dragSlotId = null;
            this.draggingSlotId = null;
            this.dropTargetSlotId = null;
            this.clearSlotDropPlaceholder();
        },
        onSlotDragOver(event, targetSlotId = null) {
            if (!this.canDragSlots() || this.busyAction) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            if (this.dragSlotId === null || targetSlotId === null || this.dragSlotId === targetSlotId) {
                return;
            }

            const slotsContainer = this.$refs.slotsContainer;
            const draggedEl = slotsContainer.querySelector(`[data-slot-id='${this.dragSlotId}']`);
            const targetEl = slotsContainer.querySelector(`[data-slot-id='${targetSlotId}']`);

            if (!draggedEl || !targetEl) {
                return;
            }

            const targetRect = targetEl.getBoundingClientRect();
            const placeAfter = event.clientY > (targetRect.top + targetRect.height / 2);
            const insertionReference = placeAfter ? targetEl.nextElementSibling : targetEl;

            const slotElements = Array.from(slotsContainer.querySelectorAll('[data-slot-id]'));
            const currentIndex = slotElements.indexOf(draggedEl);
            const referenceIndex = insertionReference ? slotElements.indexOf(insertionReference) : slotElements.length;
            const prospectiveIndex = insertionReference
                ? (referenceIndex > currentIndex ? referenceIndex - 1 : referenceIndex)
                : slotElements.length - 1;

            if (prospectiveIndex === currentIndex) {
                this.clearSlotDropPlaceholder();
                this.dropTargetSlotId = null;
                return;
            }

            const placeholderEl = this.$refs.slotDropPlaceholder;
            placeholderEl.classList.remove('hidden');
            placeholderEl.querySelector('[data-slot-drop-label]').style.minHeight = `${draggedEl.offsetHeight}px`;

            if (insertionReference !== placeholderEl) {
                slotsContainer.insertBefore(placeholderEl, insertionReference);
            }

            this.dropTargetSlotId = targetSlotId;
        },
        async onSlotDrop(event) {
            event.preventDefault();

            if (!this.canDragSlots() || this.busyAction) {
                this.clearSlotDropPlaceholder();
                return;
            }

            if (this.dragSlotId === null) {
                this.clearSlotDropPlaceholder();
                return;
            }

            const slotsContainer = this.$refs.slotsContainer;
            const draggedEl = slotsContainer.querySelector(`[data-slot-id='${this.dragSlotId}']`);

            if (draggedEl && this.$refs.slotDropPlaceholder?.parentNode === slotsContainer) {
                slotsContainer.insertBefore(draggedEl, this.$refs.slotDropPlaceholder);
            }

            this.clearSlotDropPlaceholder();

            this.dragSlotId = null;
            this.draggingSlotId = null;
            this.dropTargetSlotId = null;
            await this.persistSlotOrder();
        },
        async persistSlotOrder() {
            this.busyAction = true;
            this.actionError = '';

            const slotIds = Array.from(this.$refs.slotsContainer.querySelectorAll('[data-slot-id]'))
                .map((el) => Number(el.dataset.slotId));

            try {
                const response = await fetch('{{ route('slots.reorder', $song) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ slot_ids: slotIds }),
                });

                if (!response.ok) {
                    throw new Error('Reorder failed');
                }

                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not save slot order. Refresh and try again.';
            } finally {
                this.busyAction = false;
            }
        },
        async submitAddSlot(event) {
            this.busyAction = true;
            this.actionError = '';

            try {
                const response = await fetch('{{ route('slots.store', $song) }}', {
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

                this.openAddSlot = false;
                this.refreshSessionSets();
            } catch (e) {
                this.actionError = 'Could not add slot. Try again.';
            } finally {
                this.busyAction = false;
            }
        },
    }"
    x-init="songCollapsed = localStorage.getItem(songKey) === '1'"
    x-effect="localStorage.setItem(songKey, songCollapsed ? '1' : '0')"
    @close-session-modals.window="closeSessionModals()"
    @close-session-action-menus.window="closeSessionActionMenus()"
    @keydown.escape.window="closeSessionModals(); openActionMenu = false"
>
    <div
        class="flex cursor-pointer flex-wrap items-start justify-between gap-3"
        @click="songCollapsed = !songCollapsed"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="songCollapsed = !songCollapsed"
        @keydown.space.prevent="songCollapsed = !songCollapsed"
        x-bind:aria-expanded="(!songCollapsed).toString()"
        x-bind:title="songCollapsed ? 'Click to show song slots and assignments' : 'Click to hide song slots and assignments'"
        aria-label="Toggle song details"
    >
        <div>
            <h4 class="text-base font-semibold text-slate-900">{{ $song->artist }} - {{ $song->title }}</h4>
            @if ($song->notes)
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $song->notes }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2" @click.stop>
            @if ($canManageSet)
                <div class="relative">
                    <button
                        type="button"
                        @click="toggleActionMenu()"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                        x-bind:aria-expanded="openActionMenu.toString()"
                        aria-label="Song actions"
                        title="Song actions"
                    >
                        <x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" />
                        <span class="sr-only">Song actions</span>
                    </button>
                    <div
                        x-show="openActionMenu"
                        x-cloak
                        x-transition.origin.top.right
                        @click.outside="openActionMenu = false"
                        class="absolute right-0 top-full z-[80] mt-2 w-72 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                    >
                        <button
                            type="button"
                            @click="openActionMenu = false; openAddSlotModal()"
                            @disabled($setLocked)
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $songActionMenuItemClass }} disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <span aria-hidden="true">🛡️ </span>
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Add Slot
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="openActionMenu = false; openEditSongModal()"
                            @disabled($setLocked)
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition focus:outline-none {{ $songActionMenuItemClass }} disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                            <span>
                                @if ($isAdminManagingOtherSet)
                                    <span aria-hidden="true">🛡️ </span>
                                    <span class="sr-only"> Admin action</span>
                                @endif
                                Edit Song
                            </span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($canManageSet && ! $setLocked)
        <div x-show="openEditSong" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal class="fixed inset-0 z-40 bg-black/40" @click="openEditSong = false"></div>
        <div x-show="openEditSong" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
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
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ $song->notes }}</textarea>
                    </div>
                </form>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <form method="POST" action="{{ route('songs.destroy', $song) }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Delete Song</x-danger-button>
                    </form>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openEditSong = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button type="submit" form="edit_song_form_{{ $song->id }}">
                            @if ($isAdminManagingOtherSet)
                                <span aria-hidden="true">🛡️ </span>
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
            <div class="w-full max-w-md rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                <h5 class="text-lg font-semibold {{ $isAdminManagingOtherSet ? 'text-sky-700' : 'text-slate-900' }}">
                    {{ $isAdminManagingOtherSet ? 'Add Slot to '.$set->owner->name.'\'s Song' : 'Add Slot' }}
                </h5>
                <form method="POST" action="{{ route('slots.store', $song) }}" class="mt-4 space-y-4" @submit.prevent="submitAddSlot($event)">
                    @csrf
                    <div>
                        <x-input-label :value="'Slot Name'" />
                        <select name="name" class="mt-1 w-full rounded-md border-gray-300" required>
                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                <option value="{{ $slotValue }}">{{ $slotLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-modal-secondary-button type="button" @click="openAddSlot = false">Cancel</x-modal-secondary-button>
                        <x-modal-primary-button x-bind:disabled="busyAction">
                            @if ($isAdminManagingOtherSet)
                                <span aria-hidden="true">🛡️ </span>
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
                    <th class="px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody x-ref="slotsContainer" @dragover="onSlotDragOver($event)" @drop="onSlotDrop($event)">
                <tr x-ref="slotDropPlaceholder" class="hidden">
                    <td colspan="3" class="px-3 py-3">
                        <div data-slot-drop-label class="rounded-xl border-2 border-dashed border-sky-400 bg-sky-50/70 p-4 text-sm font-medium text-sky-700 shadow-sm">Drop slot here</div>
                    </td>
                </tr>
                @forelse ($song->slots as $slot)
                    <x-sessions.slot-row
                        :slot-model="$slot"
                        :set="$set"
                        :users="$users"
                        :slot-options="$slotOptions"
                        :is-set-owner="$isSetOwner"
                        :can-manage-set="$canManageSet"
                        :can-reorder-slots="$canManageSet && ! $setLocked"
                    />
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-4 text-sm text-slate-500">No slots yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>
