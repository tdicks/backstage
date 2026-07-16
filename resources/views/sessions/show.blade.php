<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4" x-data="{ openEditSession: false, openSet: false }">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ $session->name }}</h2>
                <p class="text-sm text-gray-500">{{ $session->date->format('l, F j, Y') }}</p>
            </div>

            <div class="flex items-center gap-2">
                @can('update', $session)
                    <x-secondary-button @click="openEditSession = true">Edit Session</x-secondary-button>
                @endcan
                <x-secondary-button @click="openSet = true">Create Set</x-secondary-button>
            </div>

            @can('update', $session)
                <div x-show="openEditSession" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSession = false"></div>
                <div x-show="openEditSession" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold">Edit Jam Session</h3>
                        <form method="POST" action="{{ route('sessions.update', $session) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PATCH')
                            <div>
                                <x-input-label for="session_name" value="Name" />
                                <x-text-input id="session_name" name="name" :value="$session->name" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="session_date" value="Date" />
                                <x-text-input id="session_date" type="date" name="date" :value="$session->date->toDateString()" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="session_description" value="Description (Markdown)" />
                                <textarea id="session_description" name="description" rows="6" class="mt-1 w-full rounded-md border-gray-300">{{ $session->description }}</textarea>
                            </div>
                            <div class="flex justify-end gap-3">
                                <x-secondary-button type="button" @click="openEditSession = false">Cancel</x-secondary-button>
                                <x-primary-button>Save</x-primary-button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('sessions.destroy', $session) }}" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <x-danger-button type="submit">Delete Session</x-danger-button>
                        </form>
                    </div>
                </div>
            @endcan

            <div x-show="openSet" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openSet = false"></div>
            <div x-show="openSet" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold">New Set for {{ $session->name }}</h3>
                    <form method="POST" action="{{ route('sets.store', $session) }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="set_name" value="Set Name" />
                            <x-text-input id="set_name" name="name" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="set_description" value="Description" />
                            <textarea id="set_description" name="description" rows="4" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <x-secondary-button type="button" @click="openSet = false">Cancel</x-secondary-button>
                            <x-primary-button>Create Set</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if ($session->description)
                <div class="session-markdown rounded-lg bg-white p-6 shadow-sm">
                    {!! Illuminate\Support\Str::markdown($session->description) !!}
                </div>
            @endif

            @forelse ($session->sets as $set)
                @php
                    $canManageSet = auth()->user()->is_admin || $set->owner_id === auth()->id();
                    $isSetOwner = $set->owner_id === auth()->id();
                @endphp

                <section
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                    x-data="{
                        openSong: false,
                        openSongRequest: false,
                        openSetEdit: false,
                        setCollapsed: false,
                        setKey: 'backstage:u{{ auth()->id() }}:set:{{ $set->id }}'
                    }"
                    x-init="setCollapsed = localStorage.getItem(setKey) === '1'"
                    x-effect="localStorage.setItem(setKey, setCollapsed ? '1' : '0')"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $set->name }}</h3>
                            <p class="text-sm text-gray-600">Owner: {{ $set->owner->name }} · {{ $set->performed ? 'Performed' : 'Not performed yet' }}</p>
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
                            @if ($canManageSet)
                                <x-secondary-button @click="openSetEdit = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Edit Set</x-secondary-button>
                                <x-secondary-button @click="openSong = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Add Song</x-secondary-button>
                            @else
                                <x-secondary-button @click="openSongRequest = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Request Song</x-secondary-button>
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

                        @forelse ($set->songs as $song)
                            <article
                                class="rounded-md border border-gray-200 p-4"
                                x-data="{
                                    openEditSong: false,
                                    openAddSlot: false,
                                    songCollapsed: false,
                                    songKey: 'backstage:u{{ auth()->id() }}:song:{{ $song->id }}'
                                }"
                                x-init="songCollapsed = localStorage.getItem(songKey) === '1'"
                                x-effect="localStorage.setItem(songKey, songCollapsed ? '1' : '0')"
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
                                                <tr class="border-t border-gray-100 align-top" x-data="{ openPropose: false, openEditSlot: false }">
                                                    <td class="py-3">{{ $slotOptions[$slot->name] ?? $slot->name }}</td>
                                                    <td class="py-3">{{ $slot->user?->name ?? 'Open' }}</td>
                                                    <td class="py-3">
                                                        <div class="flex flex-wrap gap-2">
                                                            @if ($isSetOwner && $slot->user_id !== auth()->id())
                                                                <form method="POST" action="{{ route('slots.take', $slot) }}">
                                                                    @csrf
                                                                    <x-secondary-button type="submit" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Take Slot</x-secondary-button>
                                                                </form>
                                                            @elseif ($slot->user_id !== auth()->id())
                                                                <form method="POST" action="{{ route('slot-assignments.request', $slot) }}">
                                                                    @csrf
                                                                    <x-secondary-button type="submit" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Request</x-secondary-button>
                                                                </form>
                                                            @endif

                                                            <x-secondary-button @click="openPropose = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Recommend</x-secondary-button>

                                                            @if ($canManageSet)
                                                                <x-secondary-button @click="openEditSlot = true" class="opacity-60 transition-opacity hover:opacity-100 focus:opacity-100">Edit Slot</x-secondary-button>
                                                            @endif
                                                        </div>

                                                        <div x-show="openPropose" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openPropose = false"></div>
                                                        <div x-show="openPropose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                                            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                                                                <h6 class="text-base font-semibold">Propose someone for {{ $slotOptions[$slot->name] ?? $slot->name }}</h6>
                                                                <form method="POST" action="{{ route('slot-assignments.propose', $slot) }}" class="mt-4 space-y-4">
                                                                    @csrf
                                                                    <div>
                                                                        <x-input-label :value="'User'" />
                                                                        <select name="target_user_id" class="mt-1 w-full rounded-md border-gray-300" required>
                                                                            @foreach ($users as $user)
                                                                                @if ($user != auth()->user())
                                                                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                                                                @endif
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div>
                                                                        <x-input-label :value="'Message (optional)'" />
                                                                        <textarea name="message" rows="3" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                                                                    </div>
                                                                    <div class="flex justify-end gap-2">
                                                                        <x-secondary-button type="button" @click="openPropose = false">Cancel</x-secondary-button>
                                                                        <x-primary-button>Send Proposal</x-primary-button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        @if ($canManageSet)
                                                            <div x-show="openEditSlot" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openEditSlot = false"></div>
                                                            <div x-show="openEditSlot" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                                                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                                                                    <h6 class="text-base font-semibold">Edit Slot</h6>
                                                                    <form method="POST" action="{{ route('slots.update', $slot) }}" class="mt-4 space-y-4">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <div>
                                                                            <x-input-label :value="'Slot Name'" />
                                                                            <select name="name" class="mt-1 w-full rounded-md border-gray-300">
                                                                                @foreach ($slotOptions as $slotValue => $slotLabel)
                                                                                    <option value="{{ $slotValue }}" @selected($slot->name === $slotValue)>{{ $slotLabel }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div>
                                                                            <x-input-label :value="'Assigned User (optional)'" />
                                                                            <select name="user_id" class="mt-1 w-full rounded-md border-gray-300">
                                                                                <option value="">Open</option>
                                                                                @foreach ($users as $user)
                                                                                    <option value="{{ $user->id }}" @selected($slot->user_id === $user->id)>{{ $user->name }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="flex justify-end gap-2">
                                                                            <x-secondary-button type="button" @click="openEditSlot = false">Cancel</x-secondary-button>
                                                                            <x-primary-button>Save</x-primary-button>
                                                                        </div>
                                                                    </form>
                                                                    <form method="POST" action="{{ route('slots.destroy', $slot) }}" class="mt-4">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <x-danger-button type="submit">Delete Slot</x-danger-button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <div class="mt-2 space-y-2">
                                                            @foreach ($slot->assignments->where('status', 'pending') as $assignment)
                                                                <div class="rounded border border-amber-200 bg-amber-50 p-2 text-xs text-gray-700">
                                                                    <p>
                                                                        @php
                                                                            $requestorName = $assignment->actor->name;
                                                                            $targetName = $assignment->target->name;
                                                                            if (auth()->user() == $assignment->actor)
                                                                            {
                                                                                $requestorName = 'you';
                                                                            }
                                                                            if (auth()->user() == $assignment->target)
                                                                            {
                                                                                $targetName = 'you';
                                                                            }
                                                                        @endphp
                                                                        @if ($assignment->actor == $assignment->target)
                                                                            {{ ucfirst($requestorName) }} requested this slot
                                                                        @else
                                                                            {{ ucfirst($requestorName) }} recommended {{ $targetName }} for this slot
                                                                        @endif
                                                                    </p>
                                                                    @if ($assignment->message)
                                                                        <p class="mt-1">"{{ $assignment->message }}"</p>
                                                                    @endif
                                                                    <div class="mt-2 flex gap-2">
                                                                        @php
                                                                            // Users who proposed someone else should not respond to assignments, but they can cancel the assignment
                                                                            if ($assignment->actor == auth()->user())
                                                                            {
                                                                                $canRespond = false;
                                                                                $canCancel = true;
                                                                            }
                                                                            else
                                                                            {
                                                                                // Otherwise, admins can do everything, and the target user can respond to the proposal.
                                                                                $canRespond = auth()->user()->is_admin || $assignment->target == auth()->user();
                                                                                $canCancel = false;
                                                                            }
                                                                        @endphp
                                                                        @if ($canRespond)
                                                                            <form method="POST" action="{{ route('slot-assignments.respond', $assignment) }}">
                                                                                @csrf
                                                                                @method('PATCH')
                                                                                <input type="hidden" name="status" value="accepted">
                                                                                <x-primary-button type="submit">Accept</x-primary-button>
                                                                            </form>
                                                                            <form method="POST" action="{{ route('slot-assignments.respond', $assignment) }}">
                                                                                @csrf
                                                                                @method('PATCH')
                                                                                <input type="hidden" name="status" value="rejected">
                                                                                <x-danger-button type="submit">Reject</x-danger-button>
                                                                            </form>
                                                                        @endif
                                                                        @if ($canCancel)
                                                                            <form method="POST" action="{{ route('slot-assignments.respond', $assignment) }}">
                                                                                @csrf
                                                                                @method('PATCH')
                                                                                <input type="hidden" name="status" value="rejected">
                                                                                <x-danger-button type="submit">Cancel</x-danger-button>
                                                                            </form>
                                                                        @endif                                                                            
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="py-3 text-sm text-gray-500">No slots yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @empty
                            <p class="rounded border border-dashed border-gray-300 p-4 text-sm text-gray-500">No songs in this set yet.</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    No sets for this jam session yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
