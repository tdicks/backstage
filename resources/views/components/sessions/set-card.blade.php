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
    $summarySlotNames = collect(array_keys($slotOptions))
        ->filter(fn (string $slotName) => $set->songs->contains(fn ($song) => $song->slots->contains('name', $slotName)))
        ->values();
@endphp

<section
    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
    x-data="{
        openSong: false,
        openSongRequest: false,
        openSetEdit: false,
        openSummary: false,
        setCollapsed: false,
        setKey: 'backstage:u{{ auth()->id() }}:set:{{ $set->id }}',
        canReorderSongs: @js($isSetOwner),
        reorderBusy: false,
        reorderError: '',
        reorderFeedback: '',
        dragSongId: null,
        onSongDragStart(event, songId) {
            if (!this.canReorderSongs) {
                return;
            }

            this.dragSongId = songId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(songId));
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
                return;
            }

            const songsContainer = this.$refs.songsContainer;
            const draggedEl = songsContainer.querySelector(`[data-song-id='${this.dragSongId}']`);
            const targetEl = songsContainer.querySelector(`[data-song-id='${targetSongId}']`);

            if (!draggedEl || !targetEl) {
                this.dragSongId = null;
                return;
            }

            const draggedBeforeTarget = !!(draggedEl.compareDocumentPosition(targetEl) & Node.DOCUMENT_POSITION_FOLLOWING);
            songsContainer.insertBefore(draggedEl, draggedBeforeTarget ? targetEl.nextSibling : targetEl);

            this.dragSongId = null;
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
        }
    }"
    x-init="setCollapsed = localStorage.getItem(setKey) === '1'"
    x-effect="localStorage.setItem(setKey, setCollapsed ? '1' : '0')"
    @keydown.escape.window="openSummary = false; openSetEdit = false; openSong = false; openSongRequest = false"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $set->name }}</h3>
            <p class="text-sm text-gray-600">Owner: {{ $set->owner->name }} · {{ $set->performed ? 'Performed' : 'Not performed yet' }}</p>
            <p class="text-sm {{ $set->signups_open ? 'text-emerald-700' : 'text-amber-700' }}">
                Sign ups {{ $set->signups_open ? 'open' : 'closed' }}
            </p>
            @if ($set->description)
                <p class="mt-2 text-sm text-gray-700">{{ $set->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <x-secondary-button
                type="button"
                @click="setCollapsed = !setCollapsed"
                x-bind:aria-label="setCollapsed ? 'Expand set' : 'Collapse set'"
                x-bind:title="setCollapsed ? 'Expand set' : 'Collapse set'"
                class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100"
            >
                <svg x-show="!setCollapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                </svg>
                <svg x-show="setCollapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </x-secondary-button>
            <x-secondary-button @click="openSummary = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Summary</x-secondary-button>
            @if ($canManageSet)
                @if ($set->signups_open)
                    <form method="POST" action="{{ route('sets.close-signups', $set) }}">
                        @csrf
                        @method('PATCH')
                        <x-secondary-button type="submit" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Close Sign Ups</x-secondary-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('sets.open-signups', $set) }}">
                        @csrf
                        @method('PATCH')
                        <x-secondary-button type="submit" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Re-open Sign Ups</x-secondary-button>
                    </form>
                @endif
                <x-secondary-button @click="openSetEdit = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Edit Set</x-secondary-button>
                <x-secondary-button @click="openSong = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Add Song</x-secondary-button>
            @else
                @if ($set->signups_open)
                    <x-secondary-button @click="openSongRequest = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Request Song</x-secondary-button>
                @endif
            @endif
        </div>
    </div>

    <div x-show="openSummary" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSummary = false"></div>
    <div x-show="openSummary" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-6xl rounded-lg bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between gap-3">
                <h4 class="text-lg font-semibold">Set Summary: {{ $set->name }}</h4>
                <x-secondary-button type="button" @click="openSummary = false">Close</x-secondary-button>
            </div>

            @if ($set->songs->isEmpty())
                <p class="mt-4 text-sm text-gray-500">No songs in this set yet.</p>
            @elseif ($summarySlotNames->isEmpty())
                <p class="mt-4 text-sm text-gray-500">No slots have been created for this set yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full border border-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-700">Artist/Title</th>
                                @foreach ($summarySlotNames as $slotName)
                                    <th class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-700">
                                        {{ $slotOptions[$slotName] ?? ucfirst(str_replace('_', ' ', $slotName)) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($set->songs as $song)
                                <tr class="align-top">
                                    <td class="border border-gray-200 px-3 py-2 font-medium text-gray-900">
                                        {{ $song->artist }} - {{ $song->title }}
                                    </td>
                                    @foreach ($summarySlotNames as $slotName)
                                        @php
                                            $slot = $song->slots->firstWhere('name', $slotName);
                                        @endphp
                                        <td class="border border-gray-200 px-3 py-2">
                                            @if (! $slot)
                                                <span class="text-xs text-gray-400">-</span>
                                            @elseif ($slot->user)
                                                <span class="font-medium text-emerald-700">{{ $slot->user->name }}</span>
                                            @else
                                                <span class="text-amber-700">Open</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($canManageSet)
        <div x-show="openSetEdit" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSetEdit = false"></div>
        <div x-show="openSetEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h4 class="text-lg font-semibold">Edit Set</h4>
                <form method="POST" action="{{ route('sets.update', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label :value="'Set Name'" />
                        <x-text-input name="name" :value="$set->name" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label :value="'Description'" />
                        <textarea name="description" rows="4" class="mt-1 w-full rounded-md border-gray-300">{{ $set->description }}</textarea>
                    </div>
                    <div>
                        <x-input-label :value="'Jam Session'" />
                        <select name="jam_session_id" class="mt-1 w-full rounded-md border-gray-300" required>
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
                            <select name="owner_id" class="mt-1 w-full rounded-md border-gray-300" required>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($set->owner_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="performed" value="1" @checked($set->performed)>
                        Mark as performed
                    </label>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" @click="openSetEdit = false">Cancel</x-secondary-button>
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
            <div class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                <h4 class="text-lg font-semibold">Add Song to {{ $set->name }}</h4>
                <form method="POST" action="{{ route('songs.store', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label :value="'Artist'" />
                            <x-text-input name="artist" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label :value="'Title'" />
                            <x-text-input name="title" class="mt-1 block w-full" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label :value="'Notes'" />
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                    </div>
                    <div>
                        <x-input-label :value="'Band Template (optional)'" />
                        <select name="band_template_id" class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">None</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Or choose manual slots</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="slot_names[]" value="{{ $slotValue }}">
                                    {{ $slotLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button type="button" @click="openSong = false">Cancel</x-secondary-button>
                        <x-primary-button>Add Song</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div x-show="openSongRequest" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSongRequest = false"></div>
        <div x-show="openSongRequest" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                <h4 class="text-lg font-semibold">Request a Song for {{ $set->name }}</h4>
                <form method="POST" action="{{ route('song-requests.store', $set) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="request_artist_{{ $set->id }}" value="Artist" />
                            <x-text-input id="request_artist_{{ $set->id }}" name="artist" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="request_title_{{ $set->id }}" value="Title" />
                            <x-text-input id="request_title_{{ $set->id }}" name="title" class="mt-1 block w-full" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="request_notes_{{ $set->id }}" value="Notes" />
                        <textarea id="request_notes_{{ $set->id }}" name="notes" rows="3" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <x-secondary-button type="button" @click="openSongRequest = false">Cancel</x-secondary-button>
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
            <p class="text-xs text-gray-500">Tip: drag songs to reorder them.</p>
        @endif

        @if ($canManageSet && $set->songRequests->where('status', 'pending')->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                <h4 class="text-sm font-semibold text-amber-900">Song requests</h4>
                <div class="mt-3 space-y-3">
                    @foreach ($set->songRequests->where('status', 'pending') as $songRequest)
                        <div class="rounded-md border border-amber-200 bg-white p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $songRequest->artist }} - {{ $songRequest->title }}</p>
                                    <p class="text-sm text-gray-600">Requested by {{ $songRequest->requester->name }}</p>
                                    @if ($songRequest->bandTemplate)
                                        <p class="text-sm text-gray-600">Requested template: {{ $songRequest->bandTemplate->name }}</p>
                                    @endif
                                    @if ($songRequest->notes)
                                        <p class="mt-1 text-sm text-gray-700">{{ $songRequest->notes }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-2 sm:min-w-64">
                                    <form method="POST" action="{{ route('song-requests.respond', $songRequest) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-500" for="band_template_id_{{ $songRequest->id }}">Band template for approval</label>
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
                                        <x-secondary-button class="w-full justify-center">Reject</x-secondary-button>
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
                <p class="rounded border border-dashed border-gray-300 p-4 text-sm text-gray-500">No songs in this set yet.</p>
            @endforelse
        </div>
    </div>
</section>
