@props([
    'song',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
])

<article
    class="rounded-xl border border-slate-300 bg-gradient-to-b from-slate-50 to-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow-md"
    data-song-id="{{ $song->id }}"
    draggable="{{ $isSetOwner ? 'true' : 'false' }}"
    @dragstart="onSongDragStart($event, {{ $song->id }})"
    @dragover="onSongDragOver($event)"
    @drop="onSongDrop({{ $song->id }})"
    x-data="{
        openEditSong: false,
        openAddSlot: false,
        songCollapsed: false,
        songKey: 'backstage:u{{ auth()->id() }}:song:{{ $song->id }}'
    }"
    x-init="songCollapsed = localStorage.getItem(songKey) === '1'"
    x-effect="localStorage.setItem(songKey, songCollapsed ? '1' : '0')"
    @keydown.escape.window="openEditSong = false; openAddSlot = false"
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
                <button
                    type="button"
                    @click="openEditSong = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Edit song"
                    title="Edit song"
                >
                    <x-heroicon-m-pencil-square class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Edit Song</span>
                </button>
                <button
                    type="button"
                    @click="openAddSlot = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Add slot"
                    title="Add slot"
                >
                    <x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" />
                    <span class="sr-only">Add Slot</span>
                </button>
            @endif
        </div>
    </div>

    @if ($canManageSet)
        <div x-show="openEditSong" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSong = false"></div>
        <div x-show="openEditSong" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                <h5 class="text-lg font-semibold text-slate-900">Edit Song</h5>
                <form method="POST" action="{{ route('songs.update', $song) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label :value="'Artist'" />
                            <x-text-input name="artist" :value="$song->artist" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label :value="'Title'" />
                            <x-text-input name="title" :value="$song->title" class="mt-1 block w-full" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label :value="'Notes'" />
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border-gray-300">{{ $song->notes }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" @click="openEditSong = false">Cancel</x-secondary-button>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
                <form method="POST" action="{{ route('songs.destroy', $song) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-danger-button type="submit">Delete Song</x-danger-button>
                </form>
            </div>
        </div>

        <div x-show="openAddSlot" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openAddSlot = false"></div>
        <div x-show="openAddSlot" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 text-slate-900 shadow-xl">
                <h5 class="text-lg font-semibold text-slate-900">Add Slot</h5>
                <form method="POST" action="{{ route('slots.store', $song) }}" class="mt-4 space-y-4">
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
                        <x-secondary-button type="button" @click="openAddSlot = false">Cancel</x-secondary-button>
                        <x-primary-button>Add Slot</x-primary-button>
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
            <tbody>
                @forelse ($song->slots as $slot)
                    <x-sessions.slot-row
                        :slot-model="$slot"
                        :set="$set"
                        :users="$users"
                        :slot-options="$slotOptions"
                        :is-set-owner="$isSetOwner"
                        :can-manage-set="$canManageSet"
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
