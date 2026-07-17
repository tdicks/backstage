@props([
    'set',
    'sessions',
    'users',
    'templates',
    'slotOptions',
])

@php
    $canManageSet = auth()->user()->is_admin || $set->owner_id === auth()->id();
    $isSetOwner = $set->owner_id === auth()->id();
    $totalSlots = $set->songs->sum(fn ($song) => $song->slots->count());
    $filledSlots = $set->songs->sum(fn ($song) => $song->slots->whereNotNull('user_id')->count());
    $healthRatio = $totalSlots > 0 ? $filledSlots / $totalSlots : 0;
    $healthDotClass = match (true) {
        $healthRatio >= 1 => 'bg-emerald-400',
        $healthRatio >= 0.75 => 'bg-lime-500',
        $healthRatio >= 0.5 => 'bg-amber-400',
        $healthRatio > 0 => 'bg-orange-500',
        default => 'bg-rose-600',
    };
    $summarySlotNames = collect(array_keys($slotOptions))
        ->filter(fn (string $slotName) => $set->songs->contains(fn ($song) => $song->slots->contains('name', $slotName)))
        ->values();
@endphp

<section
    class="rounded-xl border border-slate-200 bg-slate-50/95 p-6 shadow-sm"
    x-data="{
        openSong: false,
        openSongRequest: false,
        openSetEdit: false,
        openSummary: false,
        summaryData: null,
        summaryLoading: false,
        summaryLoaded: false,
        summaryError: '',
        summaryLastUpdated: '',
        summaryPollId: null,
        setCollapsed: false,
        setKey: 'backstage:u{{ auth()->id() }}:set:{{ $set->id }}',
        canReorderSongs: @js($isSetOwner),
        reorderBusy: false,
        reorderError: '',
        reorderFeedback: '',
        dragSongId: null,
        draggingSongId: null,
        onSongDragStart(event, songId) {
            if (!this.canReorderSongs) {
                return;
            }

            this.dragSongId = songId;
            this.draggingSongId = songId;
            const songsContainer = this.$refs.songsContainer;
            const draggedEl = songsContainer ? songsContainer.querySelector(`[data-song-id='${songId}']`) : null;

            if (draggedEl && event.dataTransfer) {
                // Keep the full card under cursor while dragging from the grip handle.
                event.dataTransfer.setDragImage(draggedEl, 24, 16);
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(songId));
        },
        onSongDragEnd() {
            this.dragSongId = null;
            this.draggingSongId = null;
        },
        onSongDragOver(event) {
            if (!this.canReorderSongs || this.reorderBusy) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        },
        async onSongDrop(targetSongId) {
            if (!this.canReorderSongs || this.reorderBusy || this.dragSongId === null) {
                return;
            }

            if (this.dragSongId === targetSongId) {
                this.dragSongId = null;
                this.draggingSongId = null;
                return;
            }

            const songsContainer = this.$refs.songsContainer;
            const draggedEl = songsContainer.querySelector(`[data-song-id='${this.dragSongId}']`);
            const targetEl = songsContainer.querySelector(`[data-song-id='${targetSongId}']`);

            if (!draggedEl || !targetEl) {
                this.dragSongId = null;
                this.draggingSongId = null;
                return;
            }

            const draggedBeforeTarget = !!(draggedEl.compareDocumentPosition(targetEl) & Node.DOCUMENT_POSITION_FOLLOWING);
            songsContainer.insertBefore(draggedEl, draggedBeforeTarget ? targetEl.nextSibling : targetEl);

            this.dragSongId = null;
            this.draggingSongId = null;
            await this.persistSongOrder();
        },
        async persistSongOrder() {
            this.reorderBusy = true;
            this.reorderError = '';
            this.reorderFeedback = '';

            const songIds = Array.from(this.$refs.songsContainer.querySelectorAll('[data-song-id]'))
                .map((el) => Number(el.dataset.songId));

            try {
                const response = await fetch('{{ route('songs.reorder', $set) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ song_ids: songIds }),
                });

                if (!response.ok) {
                    throw new Error('Reorder failed');
                }

                this.reorderFeedback = 'Song order saved.';
            } catch (e) {
                this.reorderError = 'Could not save song order. Refresh and try again.';
            } finally {
                this.reorderBusy = false;
            }
        },
        async loadSummary(initial = false) {
            if (initial && !this.summaryLoaded) {
                this.summaryLoading = true;
            }

            this.summaryError = '';

            try {
                const response = await fetch('{{ route('sets.summary', $set) }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load summary');
                }

                const payload = await response.json();
                this.summaryData = payload;
                this.summaryLoaded = true;
                this.summaryLastUpdated = new Date().toLocaleString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                });
            } catch (e) {
                this.summaryError = 'Could not load the live summary right now.';
            } finally {
                this.summaryLoading = false;
            }
        },
        openSummaryModal() {
            this.openSummary = true;
            this.loadSummary(true);
            this.startSummaryPolling();
        },
        closeSummaryModal() {
            this.openSummary = false;
            this.stopSummaryPolling();
        },
        startSummaryPolling() {
            this.stopSummaryPolling();
            this.summaryPollId = setInterval(() => {
                if (this.openSummary) {
                    this.loadSummary(false);
                }
            }, 15000);
        },
        stopSummaryPolling() {
            if (this.summaryPollId) {
                clearInterval(this.summaryPollId);
                this.summaryPollId = null;
            }
        }
    }"
    x-init="setCollapsed = localStorage.getItem(setKey) === '1'"
    x-effect="localStorage.setItem(setKey, setCollapsed ? '1' : '0')"
    @keydown.escape.window="closeSummaryModal(); openSetEdit = false; openSong = false; openSongRequest = false"
>
    <div
        class="flex cursor-pointer flex-wrap items-center justify-between gap-3"
        @click="setCollapsed = !setCollapsed"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="setCollapsed = !setCollapsed"
        @keydown.space.prevent="setCollapsed = !setCollapsed"
        x-bind:aria-expanded="(!setCollapsed).toString()"
        x-bind:title="setCollapsed ? 'Click to show set songs and assignments' : 'Click to hide set songs and assignments'"
        aria-label="Toggle set details"
    >
        <div>
            <h3 class="text-lg font-semibold text-slate-900">{{ $set->name }}</h3>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                <span class="inline-flex items-center gap-1.5" title="Set owner">
                    <x-heroicon-m-user class="h-4 w-4 text-slate-500" aria-hidden="true" />
                    <span class="sr-only">Set owner</span>
                    <span>{{ $set->owner->name }}</span>
                </span>

                @if ($set->performed)
                    <span class="inline-flex items-center" title="Performed">
                        <x-heroicon-m-check-circle class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                        <span class="sr-only">Performed</span>
                    </span>
                @else
                    <span class="inline-flex items-center" title="Planned">
                        <x-heroicon-m-clock class="h-4 w-4 text-sky-600" aria-hidden="true" />
                        <span class="sr-only">Not performed yet</span>
                    </span>
                @endif

                <span class="inline-flex items-center" title="Sign ups {{ $set->signups_open ? 'open' : 'closed' }}">
                    @if ($set->signups_open)
                        <x-heroicon-m-lock-open class="h-4 w-4 text-emerald-700" aria-hidden="true" />
                        <span class="sr-only">Sign ups open</span>
                    @else
                        <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-700" aria-hidden="true" />
                        <span class="sr-only">Sign ups closed</span>
                    @endif
                </span>

                @if (auth()->user()->is_admin)
                    <span
                        class="inline-flex items-center"
                        title="Set health: {{ $filledSlots }}/{{ $totalSlots }} slots filled"
                    >
                        <span class="h-2.5 w-2.5 rounded-full {{ $healthDotClass }}"></span>
                        <span class="sr-only">Set health: {{ $filledSlots }}/{{ $totalSlots }} slots filled</span>
                    </span>
                @endif
            </div>
            @if ($set->description)
                <p class="mt-2 text-sm text-slate-700">{{ $set->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2" @click.stop>
            <button
                type="button"
                @click="openSummaryModal()"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                aria-label="Summary"
                title="Summary"
            >
                <x-heroicon-m-queue-list class="h-4 w-4" aria-hidden="true" />
                <span class="sr-only">Summary</span>
            </button>
            @if ($canManageSet)
                @if ($set->signups_open)
                    <form method="POST" action="{{ route('sets.close-signups', $set) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                            aria-label="Close Sign Ups"
                            title="Close Sign Ups"
                        >
                            <x-heroicon-m-lock-closed class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">Close Sign Ups</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('sets.open-signups', $set) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                            aria-label="Re-open Sign Ups"
                            title="Re-open Sign Ups"
                        >
                            <x-heroicon-m-lock-open class="h-4 w-4" aria-hidden="true" />
                            <span class="sr-only">Re-open Sign Ups</span>
                        </button>
                    </form>
                @endif
                <button
                    type="button"
                    @click="openSetEdit = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Edit Set"
                    title="Edit Set"
                >
                    <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Edit Set</span>
                </button>
                <button
                    type="button"
                    @click="openSong = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Add Song"
                    title="Add Song"
                >
                    <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Add Song</span>
                </button>
            @else
                @if ($set->signups_open)
                    <x-secondary-button @click="openSongRequest = true" class="border-slate-300 bg-white text-slate-700 opacity-90 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 hover:opacity-100 focus:opacity-100">Request Song</x-secondary-button>
                @endif
            @endif
        </div>
    </div>

    <div x-show="openSummary" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="closeSummaryModal()"></div>
    <div x-show="openSummary" x-cloak class="fixed inset-0 z-50 flex items-start justify-center p-2 sm:items-center sm:p-4">
        <div class="flex w-full max-w-6xl max-h-[calc(100dvh-1rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 text-slate-900 shadow-2xl sm:max-h-[calc(100dvh-2rem)]">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
                <div>
                    <h4 class="text-lg font-semibold text-slate-900">Set Summary: {{ $set->name }}</h4>
                    <p class="mt-1 text-sm text-slate-600">Set owner: {{ $set->owner->name }}</p>
                </div>
                <x-secondary-button type="button" @click="closeSummaryModal()" class="border-slate-300 bg-white text-slate-700 opacity-90 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 hover:opacity-100 focus:opacity-100">Close</x-secondary-button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">

            <p class="mt-3 text-xs text-slate-600" x-show="summaryLoading && summaryLoaded">Refreshing live status...</p>
            <p class="mt-4 text-sm text-slate-600" x-show="summaryLoading && !summaryLoaded">Loading summary...</p>
            <p class="mt-4 text-sm text-rose-600" x-show="summaryError" x-text="summaryError"></p>

            <template x-if="summaryLoaded && summaryData && summaryData.songs && summaryData.songs.length === 0">
                <p class="mt-4 text-sm text-slate-600">No songs in this set yet.</p>
            </template>

            <template x-if="summaryLoaded && summaryData && summaryData.slot_names && summaryData.slot_names.length === 0 && summaryData.songs && summaryData.songs.length > 0">
                <p class="mt-4 text-sm text-slate-600">No slots have been created for this set yet.</p>
            </template>

            <div x-show="summaryLoaded && summaryData && summaryData.songs && summaryData.songs.length > 0 && summaryData.slot_names && summaryData.slot_names.length > 0" class="mt-4 space-y-3">
                <div class="space-y-3 md:hidden">
                    <template x-for="song in summaryData.songs" :key="`mobile-${song.id}`">
                        <article class="rounded-xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                            <h5 class="text-sm font-semibold text-slate-900" x-text="`${song.artist} - ${song.title}`"></h5>
                            <div class="mt-3 space-y-2">
                                <template x-for="slot in summaryData.slot_names" :key="`mobile-${song.id}-${slot.name}`">
                                    <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                                        <span class="text-xs font-medium uppercase tracking-wide text-slate-600" x-text="slot.label"></span>
                                        <div class="text-right">
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'user'">
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-sm font-medium"
                                                        x-bind:class="song.slot_map[slot.name].checked_in ? 'text-emerald-700' : 'text-slate-900'"
                                                        x-text="song.slot_map[slot.name].display"
                                                    ></span>
                                                    <span
                                                        x-show="song.slot_map[slot.name].checked_in"
                                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                    >
                                                        Checked in
                                                    </span>
                                                </div>
                                            </template>
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'open'">
                                                <span class="text-sm font-medium text-amber-700">Open</span>
                                            </template>
                                            <template x-if="!song.slot_map[slot.name] || song.slot_map[slot.name].state === 'empty'">
                                                <span class="text-sm text-slate-500">-</span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full border border-slate-200 text-sm text-slate-900">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="border border-slate-200 px-3 py-2 text-left font-semibold text-slate-700">Artist/Title</th>
                                <template x-for="slot in summaryData.slot_names" :key="slot.name">
                                    <th class="border border-slate-200 px-3 py-2 text-left font-semibold text-slate-700" x-text="slot.label"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="song in summaryData.songs" :key="song.id">
                                <tr class="align-top">
                                    <td class="border border-slate-200 px-3 py-2 font-medium text-slate-900">
                                        <span x-text="`${song.artist} - ${song.title}`"></span>
                                    </td>
                                    <template x-for="slot in summaryData.slot_names" :key="`${song.id}-${slot.name}`">
                                        <td class="border border-slate-200 px-3 py-2">
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'user'">
                                                <div class="space-y-1">
                                                    <span
                                                        class="font-medium"
                                                        x-bind:class="song.slot_map[slot.name].checked_in ? 'text-emerald-700' : 'text-slate-900'"
                                                        x-text="song.slot_map[slot.name].display"
                                                    ></span>
                                                    <span
                                                        x-show="song.slot_map[slot.name].checked_in"
                                                        class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                    >
                                                        Checked in
                                                    </span>
                                                </div>
                                            </template>
                                            <template x-if="song.slot_map[slot.name] && song.slot_map[slot.name].state === 'open'">
                                                <span class="text-amber-700">Open</span>
                                            </template>
                                            <template x-if="!song.slot_map[slot.name] || song.slot_map[slot.name].state === 'empty'">
                                                <span class="text-xs text-slate-500">-</span>
                                            </template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-white/95 px-4 py-2 text-xs text-slate-500 backdrop-blur sm:px-6">
                <span x-show="summaryLastUpdated" x-cloak>
                    Last updated <span x-text="summaryLastUpdated"></span>
                </span>
            </div>
            </div>
        </div>
    </div>

    @if ($canManageSet)
        <div x-show="openSetEdit" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSetEdit = false"></div>
        <div x-show="openSetEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
                <h4 class="text-lg font-semibold text-slate-900">Edit Set</h4>
                <form method="POST" action="{{ route('sets.update', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label :value="'Set Name'" />
                        <x-text-input name="name" :value="$set->name" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                    </div>
                    <div>
                        <x-input-label :value="'Description'" />
                        <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ $set->description }}</textarea>
                    </div>
                    <div>
                        <x-input-label :value="'Jam Session'" />
                        <select name="jam_session_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" required>
                            @foreach ($sessions as $jamSessionOption)
                                <option value="{{ $jamSessionOption->id }}" @selected($set->jam_session_id === $jamSessionOption->id)>
                                    {{ $jamSessionOption->name }} ({{ $jamSessionOption->date->format('M j, Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if (auth()->user()->is_admin)
                        <div>
                            <x-input-label :value="'Set Owner'" />
                            <select name="owner_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" required>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($set->owner_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="performed" value="1" @checked($set->performed) class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        Mark as performed
                    </label>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" @click="openSetEdit = false" class="border-slate-300 bg-white text-slate-700 opacity-90 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 hover:opacity-100 focus:opacity-100">Cancel</x-secondary-button>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
                <form method="POST" action="{{ route('sets.destroy', $set) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-danger-button type="submit">Delete Set</x-danger-button>
                </form>
            </div>
        </div>

        <div x-show="openSong" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSong = false"></div>
        <div x-show="openSong" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 text-slate-900 shadow-2xl">
                <h4 class="text-lg font-semibold text-slate-900">Add Song to {{ $set->name }}</h4>
                <form method="POST" action="{{ route('songs.store', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label :value="'Artist'" />
                            <x-text-input name="artist" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                        </div>
                        <div>
                            <x-input-label :value="'Title'" />
                            <x-text-input name="title" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label :value="'Notes'" />
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                    </div>
                    <div>
                        <x-input-label :value="'Band Template (optional)'" />
                        <select name="band_template_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                            <option value="">None</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-700">Or choose manual slots</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                                    <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                    {{ $slotLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button type="button" @click="openSong = false" class="border-slate-300 bg-white text-slate-700 opacity-90 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 hover:opacity-100 focus:opacity-100">Cancel</x-secondary-button>
                        <x-primary-button>Add Song</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div x-show="openSongRequest" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSongRequest = false"></div>
        <div x-show="openSongRequest" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 shadow-2xl">
                <h4 class="text-lg font-semibold">Request a Song for {{ $set->name }}</h4>
                <form method="POST" action="{{ route('song-requests.store', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="request_artist_{{ $set->id }}" value="Artist" />
                            <x-text-input id="request_artist_{{ $set->id }}" name="artist" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                        </div>
                        <div>
                            <x-input-label for="request_title_{{ $set->id }}" value="Title" />
                            <x-text-input id="request_title_{{ $set->id }}" name="title" class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="request_notes_{{ $set->id }}" value="Notes" />
                        <textarea id="request_notes_{{ $set->id }}" name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button type="button" @click="openSongRequest = false" class="border-slate-300 bg-white text-slate-700 opacity-90 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 hover:opacity-100 focus:opacity-100">Cancel</x-secondary-button>
                        <x-primary-button>Send Request</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-5 space-y-4" x-show="!setCollapsed" x-transition>
        <p x-show="reorderError" x-text="reorderError" class="text-sm text-red-700"></p>
        <p x-show="reorderFeedback" x-text="reorderFeedback" class="text-sm text-emerald-700"></p>
        @if ($isSetOwner)
            <p class="text-xs text-slate-500">Tip: drag songs to reorder them.</p>
        @endif

        @if ($canManageSet && $set->songRequests->where('status', 'pending')->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50/80 p-4">
                <h4 class="text-sm font-semibold text-amber-900">Song requests</h4>
                <div class="mt-3 space-y-3">
                    @foreach ($set->songRequests->where('status', 'pending') as $songRequest)
                        <div class="rounded-lg border border-amber-200 bg-gradient-to-r from-amber-50/70 to-white p-4 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $songRequest->artist }} - {{ $songRequest->title }}</p>
                                    <p class="text-sm text-slate-600">Requested by {{ $songRequest->requester->name }}</p>
                                    @if ($songRequest->bandTemplate)
                                        <p class="text-sm text-slate-600">Requested template: {{ $songRequest->bandTemplate->name }}</p>
                                    @endif
                                    @if ($songRequest->notes)
                                        <p class="mt-1 text-sm text-slate-700">{{ $songRequest->notes }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-2 sm:min-w-64">
                                    <form method="POST" action="{{ route('song-requests.respond', $songRequest) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label class="block text-xs font-medium uppercase tracking-wide text-slate-500" for="band_template_id_{{ $songRequest->id }}">Band template for approval</label>
                                            <select id="band_template_id_{{ $songRequest->id }}" name="band_template_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                <option value="">None</option>
                                                @foreach ($templates as $template)
                                                    <option value="{{ $template->id }}" @selected($songRequest->band_template_id === $template->id)>{{ $template->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" name="status" value="accepted">
                                        <x-primary-button class="w-full justify-center">Approve</x-primary-button>
                                    </form>
                                    <form method="POST" action="{{ route('song-requests.respond', $songRequest) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="rejected">
                                        <x-secondary-button class="w-full justify-center border-slate-300 bg-white text-slate-700 opacity-90 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 hover:opacity-100 focus:opacity-100">Reject</x-secondary-button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-4" x-ref="songsContainer" @dragover="onSongDragOver($event)">
            @forelse ($set->songs as $song)
                <x-sessions.song-card
                    :song="$song"
                    :set="$set"
                    :users="$users"
                    :slot-options="$slotOptions"
                    :is-set-owner="$isSetOwner"
                    :can-manage-set="$canManageSet"
                />
            @empty
                <p class="rounded border border-dashed border-slate-300 bg-white/80 p-4 text-sm text-slate-500">No songs in this set yet.</p>
            @endforelse
        </div>
    </div>
</section>
