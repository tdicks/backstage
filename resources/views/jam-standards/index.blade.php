<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Jam Standards</h2>
                <p class="text-sm text-slate-400">Build catalog songs and spin up quick sets faster.</p>
            </div>
        </div>
    </x-slot>

    <div
        class="py-8"
        x-data="{
            openAddSong: false,
            openRequestSong: false,
            selectedSongCount: 0,
            async updateCapabilities(songId, form) {
                const response = await fetch(`/jam-standards/${songId}/capabilities`, {
                    method: 'PUT',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: new FormData(form),
                });
                if (!response.ok) { window.alert('Could not save your song capability choices.'); }
            },
            async submitCatalogSong(event) {
                const response = await fetch(event.target.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: new FormData(event.target),
                });
                if (!response.ok) { window.alert('Could not add the catalog song.'); return; }
                const { song, near_matches: nearMatches } = await response.json();
                const entry = document.createElement('article');
                entry.className = 'rounded-lg border border-slate-200 bg-white px-3 py-2';
                entry.textContent = `${song.artist} - ${song.title}`;
                this.$refs.catalogList.prepend(entry);
                event.target.reset();
                this.openAddSong = false;
                if (nearMatches.length) { window.alert(`A similar song already exists: ${nearMatches[0].artist} - ${nearMatches[0].title}`); }
            },
            updateSelectedSongCount() {
                this.selectedSongCount = this.$root.querySelectorAll('[data-catalog-song-select]:checked').length;
            },
        }"
    >
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Song Catalog</h3>
                <p class="mt-1 text-sm text-slate-600">Use this list as reusable building blocks for quick sets.</p>

                @if (session('status'))
                    <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
                @endif

                @if (session('warning'))
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ session('warning') }}</p>
                    @if (session('duplicateSuggestions'))
                        <ul class="mt-2 space-y-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            @foreach (session('duplicateSuggestions') as $suggestion)
                                <li>{{ $suggestion['artist'] }} - {{ $suggestion['title'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if (auth()->user()->is_admin)
                        <x-modal-primary-button type="button" @click="openAddSong = true">Add Song</x-modal-primary-button>
                    @endif
                    @if (! auth()->user()->is_admin)
                        <x-modal-primary-button type="button" @click="openRequestSong = true">Request Song</x-modal-primary-button>
                    @endif
                </div>

                <form method="GET" class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <x-text-input name="q" value="{{ request('q') }}" class="block w-full" placeholder="Search artist or title" />
                    <select name="user_id" class="rounded border-slate-300 text-sm text-slate-900">
                        <option value="">Any performer</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) request('user_id') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <x-modal-secondary-button>Search</x-modal-secondary-button>
                </form>

                <div x-ref="catalogList" class="mt-4 max-h-[30rem] space-y-2 overflow-y-auto">
                    @forelse ($catalogSongs as $song)
                        <article class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $song->artist }} - {{ $song->title }}</p>
                            @if ($song->notes)
                                <p class="mt-1 text-xs text-slate-600">{{ $song->notes }}</p>
                            @endif
                            @if ($selectedPerformer && ! empty($searchedUserSlots[$song->id]))
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ $selectedPerformer->name }} can play:
                                    {{ collect($searchedUserSlots[$song->id])->map(fn ($slotName) => $slotOptions[$slotName] ?? $slotName)->join(', ') }}
                                </p>
                            @endif
                            @if ($song->slots->isNotEmpty())
                                <form class="mt-2" @change="updateCapabilities({{ $song->id }}, $el)">
                                    <p class="text-xs font-medium text-slate-600">I can play</p>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @foreach ($song->slots as $slot)
                                            <label class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700">
                                                <input type="checkbox" name="slot_names[]" value="{{ $slot->name }}" @checked($song->userSlots->contains('slot_name', $slot->name)) class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                                {{ $slotOptions[$slot->name] ?? $slot->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">No catalog songs yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Quick Set</h3>
                    <p class="mt-1 text-sm text-slate-600">Create your own set from selected catalog songs.</p>

                    <form method="POST" action="{{ route('jam-standards.quick-set.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label value="Jam Session" />
                            <select name="jam_session_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" required>
                                <option value="">Choose a session</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}" @disabled($session->is_closed && ! auth()->user()->is_admin)>
                                        {{ $session->name }} ({{ $session->date->format('M j, Y') }}){{ $session->is_closed ? ' - Closed' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Set Name" />
                            <x-text-input name="set_name" class="mt-1 block w-full" value="Quick Set" required />
                        </div>
                        <label class="flex items-center gap-2 rounded-lg border border-sky-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 shadow-[inset_0_0_6px_rgb(125_211_252_/_0.45),inset_0_0_14px_rgb(186_230_253_/_0.35)]">
                            <input type="hidden" name="is_hidden" value="0">
                            <input type="checkbox" name="is_hidden" value="1" class="rounded border-slate-300 text-slate-600 shadow-sm focus:ring-slate-500">
                            <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                            Hidden set
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <input type="hidden" name="free_for_all" value="0">
                            <input type="checkbox" name="free_for_all" value="1" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                            <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                            Free for all mode
                        </label>

                        <div class="max-h-64 space-y-3 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3">
                            @foreach ($catalogSongs as $song)
                                <article class="rounded-lg border border-slate-200 bg-white p-3">
                                    <label class="flex items-start gap-2 text-sm text-slate-800">
                                        <input data-catalog-song-select @change="updateSelectedSongCount()" type="checkbox" name="catalog_song_ids[]" value="{{ $song->id }}" class="mt-0.5 rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                        <span class="font-semibold">{{ $song->artist }} - {{ $song->title }}</span>
                                    </label>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach ($slotOptions as $slotValue => $slotLabel)
                                            <label class="flex items-center gap-2 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700">
                                                <input type="checkbox" name="song_slots[{{ $song->id }}][]" value="{{ $slotValue }}" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                                {{ $slotLabel }}
                                            </label>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="flex justify-end" x-show="selectedSongCount > 0" x-cloak>
                            <x-modal-primary-button>Create Quick Set</x-modal-primary-button>
                        </div>
                    </form>
                </div>

                @if (auth()->user()->is_admin)
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-900">Live Quick Set</h3>
                        <p class="mt-1 text-sm text-slate-600">Create a set for live use. Partial assignments are allowed.</p>

                        <form method="POST" action="{{ route('jam-standards.live-quick-set.store') }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <x-input-label value="Jam Session" />
                                <select name="jam_session_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" required>
                                    <option value="">Choose a session</option>
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}">{{ $session->name }} ({{ $session->date->format('M j, Y') }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Set Name" />
                                <x-text-input name="set_name" class="mt-1 block w-full" value="Live Quick Set" required />
                            </div>
                            <label class="flex items-center gap-2 rounded-lg border border-sky-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 shadow-[inset_0_0_6px_rgb(125_211_252_/_0.45),inset_0_0_14px_rgb(186_230_253_/_0.35)]">
                                <input type="hidden" name="is_hidden" value="0">
                                <input type="checkbox" name="is_hidden" value="1" class="rounded border-slate-300 text-slate-600 shadow-sm focus:ring-slate-500">
                                <x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" />
                                Hidden set
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <input type="hidden" name="free_for_all" value="0">
                                <input type="checkbox" name="free_for_all" value="1" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                <x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" />
                                Free for all mode
                            </label>

                            <div class="max-h-64 space-y-3 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3">
                                @foreach ($catalogSongs as $song)
                                    <article class="rounded-lg border border-slate-200 bg-white p-3">
                                        <label class="flex items-start gap-2 text-sm text-slate-800">
                                            <input type="checkbox" name="catalog_song_ids[]" value="{{ $song->id }}" class="mt-0.5 rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                            <span class="font-semibold">{{ $song->artist }} - {{ $song->title }}</span>
                                        </label>
                                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach ($slotOptions as $slotValue => $slotLabel)
                                                <div class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700">
                                                    <label class="flex items-center gap-2">
                                                        <input type="checkbox" name="live_song_slots[{{ $song->id }}][]" value="{{ $slotValue }}" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                                        {{ $slotLabel }}
                                                    </label>
                                                    <select name="live_song_assignments[{{ $song->id }}][{{ $slotValue }}]" class="mt-1 w-full rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700">
                                                        <option value="">Unassigned</option>
                                                        @foreach ($users as $user)
                                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endforeach
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="flex justify-end">
                                <x-modal-primary-button>Create Live Quick Set</x-modal-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
            </section>
        </div>

        @if (auth()->user()->is_admin && $pendingRequests->isNotEmpty())
            <section class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="border-y border-slate-200 bg-white py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Catalog Requests</h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($pendingRequests as $songRequest)
                            <div class="flex flex-wrap items-center justify-between gap-3 border border-slate-200 px-3 py-2">
                                <div class="text-sm text-slate-700">
                                    <span class="font-semibold">{{ $songRequest->artist }} - {{ $songRequest->title }}</span>
                                    <span class="text-slate-500">requested by {{ $songRequest->requester->name }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('jam-standards.requests.respond', $songRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><x-modal-primary-button>Approve</x-modal-primary-button></form>
                                    <form method="POST" action="{{ route('jam-standards.requests.respond', $songRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><x-modal-secondary-button>Reject</x-modal-secondary-button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div x-show="openAddSong || openRequestSong" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="openAddSong = false; openRequestSong = false"></div>
        <div x-show="openAddSong || openRequestSong" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-xl border border-slate-200 bg-white p-6 shadow-2xl" @click.stop>
                <h3 class="text-lg font-semibold text-slate-900" x-text="openAddSong ? 'Add Catalog Song' : 'Request Catalog Song'"></h3>
                <form method="POST" :action="openAddSong ? '{{ route('jam-standards.store') }}' : '{{ route('jam-standards.requests.store') }}'" class="mt-4 space-y-4" @submit.prevent="openAddSong ? submitCatalogSong($event) : $event.target.submit()">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><x-input-label value="Artist" /><x-text-input name="artist" class="mt-1 block w-full" required /></div>
                        <div><x-input-label value="Title" /><x-text-input name="title" class="mt-1 block w-full" required /></div>
                    </div>
                    <div>
                        <x-input-label value="Band Template" />
                        <select name="band_template_id" class="mt-1 w-full rounded border-slate-300 text-sm text-slate-900"><option value="">Choose slots manually</option>@foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select>
                    </div>
                    <div><p class="text-sm font-medium text-slate-700">Slots</p><div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">@foreach ($slotOptions as $slotValue => $slotLabel)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" class="rounded border-slate-300 text-amber-600">{{ $slotLabel }}</label>@endforeach</div></div>
                    <div><x-input-label value="Notes" /><x-textarea-input name="notes" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm text-slate-900" /></div>
                    <div class="flex justify-end gap-2"><x-modal-secondary-button type="button" @click="openAddSong = false; openRequestSong = false">Cancel</x-modal-secondary-button><x-modal-primary-button x-text="openAddSong ? 'Add Song' : 'Send Request'"></x-modal-primary-button></div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
