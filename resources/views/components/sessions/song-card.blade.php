@props([
    'song',
    'set',
    'users',
    'slotOptions',
    'isSetOwner' => false,
    'canManageSet' => false,
])

<article
    class="rounded-md border border-gray-200 p-4"
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
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h4 class="font-semibold text-gray-900">{{ $song->artist }} - {{ $song->title }}</h4>
            @if ($song->notes)
                <p class="text-sm text-gray-600">{{ $song->notes }}</p>
            @endif
        </div>

        <div class="flex gap-2">
            <x-secondary-button
                type="button"
                @click="songCollapsed = !songCollapsed"
                x-bind:aria-label="songCollapsed ? 'Expand song' : 'Collapse song'"
                x-bind:title="songCollapsed ? 'Expand song' : 'Collapse song'"
                class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100"
            >
                <svg x-show="!songCollapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                </svg>
                <svg x-show="songCollapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </x-secondary-button>
            @if ($canManageSet)
                <x-secondary-button @click="openEditSong = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Edit Song</x-secondary-button>
                <x-secondary-button @click="openAddSlot = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Add Slot</x-secondary-button>
            @endif
        </div>
    </div>

    @if ($canManageSet)
        <div x-show="openEditSong" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSong = false"></div>
        <div x-show="openEditSong" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h5 class="text-lg font-semibold">Edit Song</h5>
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
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h5 class="text-lg font-semibold">Add Slot</h5>
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

    <div class="mt-4 overflow-x-auto" x-show="!songCollapsed" x-transition>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-2">Slot</th>
                    <th class="py-2">Assigned</th>
                    <th class="py-2">Actions</th>
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
                        <td colspan="3" class="py-3 text-sm text-gray-500">No slots yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>
