<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-100">Jam Standards</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    Regular songs most of our performers know.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if (auth()->user()->is_admin)
                    <x-primary-button type="button" onclick="window.dispatchEvent(new CustomEvent('open-catalog-add-song'))" class="gap-1.5"><x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" /><span>Add Song</span></x-primary-button>
                @else
                    <x-primary-button type="button" onclick="window.dispatchEvent(new CustomEvent('open-catalog-request-song'))" class="gap-1.5"><x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" /><span>Request Song</span></x-primary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
        @open-catalog-add-song.window="openAddSong = true; openRequestSong = false; resetCatalogSongForm(); window.dispatchEvent(new CustomEvent('open-modal', { detail: 'catalog-song-form' }))"
        @open-catalog-request-song.window="openAddSong = false; openRequestSong = true; resetCatalogSongForm(); window.dispatchEvent(new CustomEvent('open-modal', { detail: 'catalog-song-form' }))"
        @scroll.window="positionCatalogActionMenu()"
        @resize.window="positionCatalogActionMenu()"
        @keydown.escape.window="catalogActionMenuOpen = false; performerFilterOpen = false"
        x-data="jamStandardsCatalog({
            catalogUrl: @js(url('/jam-standards')),
            catalogRequestsUrl: @js(url('/jam-standards/requests')),
            artistLookupUrl: @js(route('lookups.deezer.artists')),
            titleLookupUrl: @js(route('lookups.deezer.tracks')),
            csrfToken: @js(csrf_token()),
            catalogPage: @js($catalogSongs->currentPage()),
            slotLabels: @js($slotOptions),
            canEditCatalog: @js(auth()->user()->is_admin),
            templateSlots: @js($templates->mapWithKeys(fn ($template) => [$template->id => $template->slots->pluck('name')->values()])->all()),
            initialStatusMessage: @js(session('status')),
            quickSetSongs: @js($catalogSongs->mapWithKeys(fn ($song) => [$song->id => [
                'id' => $song->id,
                'artist' => $song->artist,
                'title' => $song->title,
                'slots' => $song->slots->map(fn ($slot) => ['name' => $slot->name])->values(),
            ]])->all()),
            initialCatalogSongs: @js($catalogSongs->map(fn ($song) => [
                'id' => $song->id,
                'artist' => $song->artist,
                'title' => $song->title,
                'notes' => $song->notes,
                'duration' => $song->duration,
                'source' => $song->source,
                'band_template_id' => $song->band_template_id,
                'slots' => $song->slots->map(fn ($slot) => [
                    'name' => $slot->name,
                    'recent_capability_count' => max(0, (int) ($song->recent_capability_counts[$slot->name] ?? 0)),
                ])->values(),
                'user_slot_names' => $song->userSlots->pluck('slot_name')->values(),
                'performer_slots' => $searchedUserSlots[$song->id] ?? [],
            ])->values()),
            initialCatalogPagination: @js([
                'current_page' => $catalogSongs->currentPage(),
                'last_page' => $catalogSongs->lastPage(),
                'total' => $catalogSongs->total(),
            ]),
            initialPerformers: @js($selectedPerformers->values()),
            selectedPerformerIds: @js($selectedPerformers->pluck('id')->map(fn ($userId) => (string) $userId)->all()),
            performerNames: @js($users->mapWithKeys(fn ($user) => [$user->id => $user->name])->all()),
            slotConflicts: @js($slotConflicts),
        })"
    >
        <div x-show="statusMessage" x-cloak x-transition.opacity class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">
            <p x-text="statusMessage"></p>
        </div>
        @if ($pendingRequests->isNotEmpty())
            <section data-catalog-requests-panel class="mb-6 rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6">
                <h3 class="text-lg font-semibold text-slate-900">Catalog Requests</h3>
                <div data-catalog-requests class="mt-3 space-y-3">
                    @foreach ($pendingRequests as $songRequest)
                        <div data-catalog-request-id="{{ $songRequest->id }}" class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white/90 p-4 shadow-sm">
                            <p class="text-sm text-slate-700"><span class="font-semibold">{{ $songRequest->artist }} - {{ $songRequest->title }}</span> requested by {{ $songRequest->requester_user_id === auth()->id() ? 'you' : $songRequest->requester->name }}</p>
                            <div class="flex gap-2">
                                @if (auth()->user()->is_admin)
                                    <form method="POST" action="{{ route('jam-standards.requests.respond', $songRequest) }}" @submit.prevent="respondToCatalogRequest($event)">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-400" aria-label="Approve catalog request" title="Approve"><x-heroicon-m-check class="h-4 w-4" aria-hidden="true" /><span class="sr-only">Approve</span></button></form>
                                    <form method="POST" action="{{ route('jam-standards.requests.respond', $songRequest) }}" @submit.prevent="respondToCatalogRequest($event)">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-rose-700 transition hover:bg-rose-50 hover:text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-400" aria-label="Reject catalog request" title="Reject"><x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" /><span class="sr-only">Reject</span></button></form>
                                @endif
                                @if ($songRequest->requester_user_id === auth()->id())
                                    <form method="POST" action="{{ route('jam-standards.requests.destroy', $songRequest) }}" @submit.prevent="cancelCatalogRequest($event)">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400">Cancel</button></form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        <section data-catalog-section class="rounded-xl border border-slate-200 bg-slate-50/95 p-5 shadow-sm sm:p-6">
            <h3 class="mb-3 text-lg font-semibold text-slate-900">Song Catalog</h3>
            <p class="mb-4 text-sm text-slate-600">Search by artist or song title, then filter by performers who know parts on those songs.</p>

        @if (session('warning'))
            <div class="mb-4 border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <p>{{ session('warning') }}</p>
                @if (session('duplicateSuggestions'))
                    <ul class="mt-2 list-inside list-disc">
                        @foreach (session('duplicateSuggestions') as $suggestion)
                            <li>{{ $suggestion['artist'] }} - {{ $suggestion['title'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="mb-4" data-tour="jam-standards-search">
            <div class="flex flex-wrap items-center justify-between gap-3">
            <form x-ref="catalogSearch" method="GET" action="{{ route('jam-standards.index') }}" @submit.prevent="searchCatalog()" class="grid flex-1 gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
            <x-text-input name="q" value="{{ request('q') }}" class="block w-full" placeholder="Search artist or title" />
            <div class="relative" @click.outside="performerFilterOpen = false">
                <button type="button" @click="performerFilterOpen = ! performerFilterOpen" :aria-expanded="performerFilterOpen.toString()" aria-haspopup="true" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"><span x-text="selectedPerformerFilterLabel()"></span><x-heroicon-m-chevron-down class="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" /></button>
                <div x-show="performerFilterOpen" x-cloak x-transition.origin.top class="absolute z-30 mt-1 max-h-64 w-full min-w-56 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                    @foreach ($users as $user)
                        <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" x-model="selectedPerformerIds" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">{{ $user->name }}@if (($user->known_song_count ?? 0) > 0) ({{ $user->known_song_count }})@endif</label>
                    @endforeach
                </div>
            </div>
            <x-modal-secondary-button type="submit" x-bind:disabled="searchLoading" x-text="searchLoading ? 'Searching' : 'Search'"></x-modal-secondary-button>
            <x-modal-secondary-button type="button" x-bind:disabled="searchLoading" @click="resetCatalogSearch()">Reset</x-modal-secondary-button>
            </form>
            <div class="hidden md:block" x-show="selectedSongIds.length" x-cloak>
                <x-modal-primary-button data-tour="jam-standards-create-set" type="button" @click="openQuickSetModal()">Create Set <span class="ml-1" x-text="`(${selectedSongIds.length})`"></span></x-modal-primary-button>
            </div>
            </div>
            <div data-tour="jam-standards-select-songs-mobile" class="mt-3 md:hidden">
                <div x-show="!mobileSelectionMode" x-cloak>
                    <button type="button" @click="openMobileSelectionMode()" class="inline-flex items-center gap-2 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        <x-heroicon-m-check-circle class="h-4 w-4" aria-hidden="true" />
                        <span>Select Songs</span>
                    </button>
                </div>
                <div x-show="mobileSelectionMode" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3 text-sm text-emerald-900 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold"><span x-text="selectedSongIds.length"></span> selected</p>
                        <button data-tour="jam-standards-click-cancel-mobile" type="button" @click="cancelMobileSelectionMode()" class="text-sm font-medium text-emerald-800 underline decoration-emerald-300 underline-offset-2">Cancel</button>
                    </div>
                    <p class="mt-2 text-xs text-emerald-800/90">Tap cards to add or remove songs for this quick set.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="selectAllVisibleSongs()" class="inline-flex items-center rounded-md border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-emerald-800 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-400">Select All Visible</button>
                        <button type="button" @click="clearSelectedSongs()" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-400">Clear</button>
                    </div>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <template x-if="currentCatalogPerformers.length > 0">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-emerald-300 bg-emerald-50 text-emerald-700" title="Selected performers know these slots">
                        <x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                </template>
                <p class="text-xs font-medium text-slate-600" x-show="currentCatalogPerformers.length > 0" x-text="selectedPerformerLegend()"></p>
            </div>
        </div>

        <div class="space-y-4 md:hidden" x-ref="catalogCards">
            <template x-if="currentCatalogSongs.length === 0">
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    No catalog songs found.
                </div>
            </template>

            <template x-for="song in currentCatalogSongs" :key="song.id">
                <article x-bind:data-catalog-song-id="song.id" x-bind:class="catalogCardClass(selectedSongIds.includes(song.id))" @click="if (mobileSelectionMode) { toggleSong(song.id, !selectedSongIds.includes(song.id)) }" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <span class="block break-words text-sm font-semibold text-slate-900" x-text="song.artist"></span>
                            <span class="mt-0.5 block break-words text-sm text-slate-700" x-text="song.title"></span>
                            <span class="mt-1 block text-xs text-slate-500" x-show="song.notes" x-text="song.notes"></span>
                        </div>
                        <span x-show="mobileSelectionMode" x-cloak x-bind:class="selectedSongIds.includes(song.id) ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-slate-300 bg-slate-50 text-slate-600'" class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"><span x-text="selectedSongIds.includes(song.id) ? 'Selected' : 'Tap to select'"></span></span>
                        <button x-show="canEditCatalog" type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400" @click.stop="toggleCatalogActionMenu(song, $event.currentTarget)" x-bind:aria-expanded="(catalogActionMenuOpen && catalogActionSong?.id === song.id).toString()" aria-label="Song actions" title="Song actions"><x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" /><span class="sr-only">Song actions</span></button>
                    </div>
                    <div data-tour="jam-standards-select-parts" x-bind:class="mobileSelectionMode ? 'mt-4 border-t border-slate-100 pt-3 opacity-60 pointer-events-none' : 'mt-4 border-t border-slate-100 pt-3'">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Parts you know</p>
                            <p class="text-[11px] font-medium text-slate-500" x-show="hasPerformerMatches(song)">Selected performers marked with <span class="font-semibold text-emerald-700">check</span></p>
                        </div>
                        <form class="flex flex-wrap gap-2" x-show="(song.slots || []).length > 0">
                            <template x-for="slot in (song.slots || [])" :key="`mobile-song-${song.id}-slot-${slot.name}`">
                                <label x-bind:class="catalogSlotChipClass(songSlotSelected(song, slot.name))">
                                    <input type="checkbox" name="slot_names[]" x-bind:value="slot.name" x-bind:checked="songSlotSelected(song, slot.name)" @change="updateSongSlotSelection(song, slot.name, $event.target.checked, $el.form)" class="sr-only">
                                    <span x-text="slotLabel(slot.name)"></span>
                                    <span x-bind:class="catalogCapabilityCountClass(songSlotSelected(song, slot.name))" class="inline-flex h-5 min-w-5 items-center justify-center rounded-full border px-1 text-[11px] font-semibold" x-text="Math.max(0, Number(slot.recent_capability_count || 0))"></span>
                                    <span x-show="performersForSlot(song, slot.name).length > 0" class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-emerald-300 bg-emerald-50 text-emerald-700" x-bind:title="performerSlotBadgeTitle(song, slot.name)" x-bind:aria-label="performerSlotBadgeTitle(song, slot.name)"><x-heroicon-m-check class="h-3 w-3" aria-hidden="true" /></span>
                                </label>
                            </template>
                        </form>
                    </div>
                </article>
            </template>
        </div>

        <div x-show="mobileSelectionMode && selectedSongIds.length" x-cloak class="fixed inset-x-0 bottom-4 z-30 px-4 md:hidden">
            <div class="mx-auto flex max-w-md items-center justify-between gap-3 rounded-2xl border border-emerald-300 bg-white/95 px-4 py-3 shadow-2xl backdrop-blur">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">Create quick set</p>
                    <p class="text-xs text-slate-600"><span x-text="selectedSongIds.length"></span> songs selected</p>
                </div>
                <x-modal-primary-button data-tour="jam-standards-create-set-mobile" type="button" @click="openQuickSetModal()" class="shrink-0 bg-emerald-700 hover:bg-emerald-600 focus:ring-emerald-400 focus:ring-offset-white">
                    Create Set
                </x-modal-primary-button>
            </div>
        </div>

        <div class="hidden overflow-x-auto border border-slate-200 bg-white shadow-sm [contain:paint] md:block">
            <table data-tour="jam-standards-songs-list" class="w-full table-fixed divide-y divide-slate-200 text-left md:table-auto">
                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-600">
                    <tr>
                        <th scope="col" class="w-10 px-2 py-2 sm:w-12 sm:px-4 sm:py-3"><span class="sr-only">Select song</span></th>
                        <th scope="col" class="w-[24%] px-2 py-2 sm:px-4 sm:py-3">Artist</th>
                        <th scope="col" class="w-[28%] px-2 py-2 sm:px-4 sm:py-3">Title</th>
                        <th scope="col" class="w-[42%] px-2 py-2 sm:px-4 sm:py-3">Select the parts you know</th>
                        @if (auth()->user()->is_admin)<th scope="col" class="w-10 px-2 py-2 sm:w-12 sm:px-4 sm:py-3"><span class="sr-only">Actions</span></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" x-ref="catalogRows">
                    <template x-if="currentCatalogSongs.length === 0">
                        <tr>
                            <td x-bind:colspan="canEditCatalog ? 5 : 4" class="px-4 py-10 text-center text-sm text-slate-500">No catalog songs found.</td>
                        </tr>
                    </template>
                    <template x-for="song in currentCatalogSongs" :key="`table-song-${song.id}`">
                        <tr x-bind:data-catalog-song-id="song.id" x-bind:class="catalogRowClass(selectedSongIds.includes(song.id))">
                            <td data-tour="jam-standards-select-songs" class="cursor-pointer px-2 py-2 align-top sm:px-4 sm:py-3">
                                <input type="checkbox" x-bind:value="song.id" x-bind:checked="selectedSongIds.includes(song.id)" @change="toggleSong(song.id, $event.target.checked)" class="cursor-pointer rounded border-slate-300 text-amber-600 focus:ring-amber-500" x-bind:aria-label="`Select ${song.artist} - ${song.title}`">
                            </td>
                            <td data-catalog-artist class="break-words px-2 py-2 text-sm font-medium text-slate-900 align-top sm:px-4 sm:py-3" x-text="song.artist"></td>
                            <td data-catalog-title class="break-words px-2 py-2 text-sm text-slate-700 align-top sm:px-4 sm:py-3"><span x-text="song.title"></span><p class="mt-1 text-xs text-slate-500" x-show="song.notes" x-text="song.notes"></p></td>
                            <td data-tour="jam-standards-select-parts" data-catalog-slots class="px-2 py-2 text-sm text-slate-700 align-top sm:px-4 sm:py-3">
                                <form class="flex flex-wrap gap-2" x-show="(song.slots || []).length > 0">
                                    <template x-for="slot in (song.slots || [])" :key="`table-song-${song.id}-slot-${slot.name}`">
                                        <label x-bind:class="catalogSlotChipClass(songSlotSelected(song, slot.name))">
                                            <input type="checkbox" name="slot_names[]" x-bind:value="slot.name" x-bind:checked="songSlotSelected(song, slot.name)" @change="updateSongSlotSelection(song, slot.name, $event.target.checked, $el.form)" class="sr-only">
                                            <span x-text="slotLabel(slot.name)"></span>
                                            <span x-bind:class="catalogCapabilityCountClass(songSlotSelected(song, slot.name))" class="inline-flex h-5 min-w-5 items-center justify-center rounded-full border px-1 text-[11px] font-semibold" x-text="Math.max(0, Number(slot.recent_capability_count || 0))"></span>
                                            <span x-show="performersForSlot(song, slot.name).length > 0" class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-emerald-300 bg-emerald-50 text-emerald-700" x-bind:title="performerSlotBadgeTitle(song, slot.name)" x-bind:aria-label="performerSlotBadgeTitle(song, slot.name)"><x-heroicon-m-check class="h-3 w-3" aria-hidden="true" /></span>
                                        </label>
                                    </template>
                                </form>
                            </td>
                            <template x-if="canEditCatalog">
                                <td class="px-2 py-2 text-right align-top sm:px-4 sm:py-3">
                                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400" @click="toggleCatalogActionMenu(song, $event.currentTarget)" x-bind:aria-expanded="(catalogActionMenuOpen && catalogActionSong?.id === song.id).toString()" aria-label="Song actions" title="Song actions"><x-heroicon-m-bars-3 class="h-4 w-4" aria-hidden="true" /><span class="sr-only">Song actions</span></button>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-4" x-show="Number(catalogPagination?.total || 0) > 0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600" x-text="catalogSummaryText()"></p>
                <div class="flex gap-2" x-show="Number(catalogPagination?.last_page || 0) > 1">
                    <button type="button" @click="goToCatalogPage(Number(catalogPagination.current_page) - 1)" x-bind:disabled="Number(catalogPagination.current_page) <= 1" class="inline-flex items-center border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Previous</button>
                    <button type="button" @click="goToCatalogPage(Number(catalogPagination.current_page) + 1)" x-bind:disabled="Number(catalogPagination.current_page) >= Number(catalogPagination.last_page)" class="inline-flex items-center border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>

        </section>

        @if (auth()->user()->is_admin)
            <template x-teleport="body">
                <div x-ref="catalogActionMenu" x-show="catalogActionMenuOpen" x-cloak x-transition.origin.top.right @click.outside="catalogActionMenuOpen = false" x-bind:style="catalogActionMenuStyle" class="z-[80] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl">
                    <button type="button" @click="editSong(catalogActionSong)" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"><x-heroicon-m-pencil-square class="h-4 w-4 text-slate-500" aria-hidden="true" /><span>Edit song</span></button>
                    <button type="button" @click="showCoverage(catalogActionSong)" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"><x-heroicon-m-list-bullet class="h-4 w-4 text-slate-500" aria-hidden="true" /><span>Song Coverage</span></button>
                </div>
            </template>

            <template x-teleport="body">
                <x-modal name="catalog-song-coverage" maxWidth="md">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-slate-900" x-text="coverageSong ? `Coverage: ${coverageSong.artist} - ${coverageSong.title}` : 'Song Coverage'"></h3>
                        <p x-show="coverageLoading" x-cloak class="mt-4 text-sm text-slate-500">Loading coverage...</p>
                        <p x-show="coverageError" x-cloak x-text="coverageError" class="mt-4 text-sm text-rose-700"></p>
                        <p x-show="!coverageLoading && !coverageError && coverageUsers.length === 0" x-cloak class="mt-4 text-sm text-slate-500">No one has indicated coverage for this song.</p>
                        <ul x-show="!coverageLoading && coverageUsers.length" x-cloak class="mt-4 divide-y divide-slate-200 border-y border-slate-200">
                            <template x-for="user in coverageUsers" :key="user.id">
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3"><p class="text-sm font-medium text-slate-900" x-text="user.name"></p><div class="flex flex-wrap justify-end gap-1.5"><template x-for="slotName in user.slot_names" :key="slotName"><span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700" x-text="slotLabel(slotName)"></span></template></div></li>
                            </template>
                        </ul>
                        <div class="mt-6 flex justify-end"><x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'catalog-song-coverage' }))">Close</x-modal-secondary-button></div>
                    </div>
                </x-modal>
            </template>
        @endif

        <template x-teleport="body">
            <x-modal name="catalog-song-form" maxWidth="xl" focusable>
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-slate-900" x-text="openAddSong ? 'Add Catalog Song' : 'Request Catalog Song'"></h3>
                        <form method="POST" :action="openAddSong ? '{{ route('jam-standards.store') }}' : '{{ route('jam-standards.requests.store') }}'" class="mt-4 space-y-4" @submit.prevent="openAddSong ? submitCatalogSong($event) : submitCatalogRequest($event)">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2"><div class="relative"><x-input-label value="Artist" /><x-text-input name="artist" x-model="catalogArtistQuery" @input="queueCatalogArtistLookup()" @focus="showCatalogArtistSuggestions = catalogArtistSuggestions.length > 0" class="mt-1 block w-full text-slate-900 placeholder:text-slate-500" autocomplete="off" required /><p class="mt-1 text-xs text-slate-500">Start typing an artist to fetch Deezer suggestions.</p><p x-show="catalogArtistLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up Deezer artists...</p><ul x-show="showCatalogArtistSuggestions" x-cloak @click.outside="showCatalogArtistSuggestions = false" class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"><template x-for="artist in catalogArtistSuggestions" :key="artist"><li><button type="button" @click="selectCatalogArtistSuggestion(artist)" class="w-full px-3 py-2 text-left text-sm text-slate-900 hover:bg-slate-50" x-text="artist"></button></li></template></ul></div><div class="relative"><x-input-label value="Title" /><x-text-input name="title" x-model="catalogTitleQuery" @input="queueCatalogTitleLookup()" @focus="showCatalogTitleSuggestions = catalogTitleSuggestions.length > 0" class="mt-1 block w-full text-slate-900 placeholder:text-slate-500" autocomplete="off" required /><p class="mt-1 text-xs text-slate-500">Song suggestions are scoped to the selected artist.</p><p x-show="catalogTitleLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up Deezer songs...</p><ul x-show="showCatalogTitleSuggestions" x-cloak @click.outside="showCatalogTitleSuggestions = false" class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"><template x-for="track in catalogTitleSuggestions" :key="track.title"><li><button type="button" @click="selectCatalogTitleSuggestion(track)" class="w-full px-3 py-2 text-left text-sm text-slate-900 hover:bg-slate-50" x-text="track.title"></button></li></template></ul></div></div>
                            <input type="hidden" name="duration" :value="catalogDeezerTitleSelected && catalogSelectedDeezerDuration ? catalogSelectedDeezerDuration : ''">
                            <input type="hidden" name="source" :value="catalogDeezerTitleSelected ? 'deezer' : ''">
                            <fieldset><legend class="text-sm font-medium text-slate-700">Slots</legend><p class="mt-1 text-xs text-slate-500">You can change the slots on this song by selecting a different band template, or choosing slots manually. Slot coverage will be lost for any removed slots.</p><div class="mt-2 flex gap-4 text-sm"><label class="flex items-center gap-2 text-slate-700"><input type="radio" value="template" x-model="entryMode"> Band template</label><label class="flex items-center gap-2 text-slate-700"><input type="radio" value="manual" x-model="entryMode"> Choose manually</label></div></fieldset>
                            <div x-show="entryMode === 'template'"><x-input-label value="Band Template" /><x-select name="band_template_id" x-model="requestTemplateId" x-bind:disabled="entryMode !== 'template'" class="mt-1"><option value="">Choose a template</option>@foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</x-select></div>
                            <div x-show="entryMode === 'manual'"><div class="grid grid-cols-2 gap-2 sm:grid-cols-3">@foreach ($slotOptions as $slotValue => $slotLabel)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" x-model="entrySlotNames" :disabled="entryMode !== 'manual'" class="rounded border-slate-300 text-amber-600">{{ $slotLabel }}</label>@endforeach</div></div>
                            <fieldset x-show="openRequestSong" class="border-t border-slate-200 pt-4"><legend class="text-sm font-medium text-slate-700">I can perform</legend><div class="mt-2 flex flex-wrap gap-2">@foreach ($slotOptions as $slotValue => $slotLabel)<label x-data="{ checked: false }" :class="!availableRequesterSlots().includes('{{ $slotValue }}') ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' : (checked ? 'cursor-pointer border-sky-300 bg-sky-50 text-sky-700' : 'cursor-pointer border-slate-200 bg-white text-slate-600 hover:border-slate-300')" class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition"><input type="checkbox" name="requester_slot_names[]" value="{{ $slotValue }}" :disabled="!availableRequesterSlots().includes('{{ $slotValue }}')" @change="checked = $event.target.checked" class="sr-only">{{ $slotLabel }}</label>@endforeach</div></fieldset>
                            <div><x-input-label value="Notes" /><x-textarea-input name="notes" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm text-slate-900" /></div>
                            <div class="flex justify-end gap-2"><x-modal-secondary-button type="button" @click="closeCatalogSongForm()">Cancel</x-modal-secondary-button><x-modal-primary-button x-text="openAddSong ? 'Add Song' : 'Send Request'"></x-modal-primary-button></div>
                        </form>
                    </div>
            </x-modal>
        </template>

        @if (auth()->user()->is_admin)
            <template x-teleport="body">
                <x-modal name="catalog-song-edit" maxWidth="xl" focusable>
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-slate-900">Edit Catalog Song</h3>
                            <form method="POST" :action="`{{ url('/jam-standards') }}/${editingSong?.id}`" class="mt-4 space-y-4" @submit.prevent="submitCatalogEdit($event)">
                                @csrf @method('PUT')
                                <div class="grid gap-3 sm:grid-cols-2"><div class="relative"><x-input-label value="Artist" /><x-text-input name="artist" x-model="catalogArtistQuery" @input="queueCatalogArtistLookup()" @focus="showCatalogArtistSuggestions = catalogArtistSuggestions.length > 0" class="mt-1 block w-full text-slate-900 placeholder:text-slate-500" autocomplete="off" required /><p class="mt-1 text-xs text-slate-500">Start typing an artist to fetch Deezer suggestions.</p><p x-show="catalogArtistLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up Deezer artists...</p><ul x-show="showCatalogArtistSuggestions" x-cloak @click.outside="showCatalogArtistSuggestions = false" class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"><template x-for="artist in catalogArtistSuggestions" :key="artist"><li><button type="button" @click="selectCatalogArtistSuggestion(artist)" class="w-full px-3 py-2 text-left text-sm text-slate-900 hover:bg-slate-50" x-text="artist"></button></li></template></ul></div><div class="relative"><x-input-label value="Title" /><x-text-input name="title" x-model="catalogTitleQuery" @input="queueCatalogTitleLookup()" @focus="showCatalogTitleSuggestions = catalogTitleSuggestions.length > 0" class="mt-1 block w-full text-slate-900 placeholder:text-slate-500" autocomplete="off" required /><p class="mt-1 text-xs text-slate-500">Song suggestions are scoped to the selected artist.</p><p x-show="catalogTitleLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up Deezer songs...</p><ul x-show="showCatalogTitleSuggestions" x-cloak @click.outside="showCatalogTitleSuggestions = false" class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"><template x-for="track in catalogTitleSuggestions" :key="track.title"><li><button type="button" @click="selectCatalogTitleSuggestion(track)" class="w-full px-3 py-2 text-left text-sm text-slate-900 hover:bg-slate-50" x-text="track.title"></button></li></template></ul></div></div>
                                <input type="hidden" name="duration" :value="catalogDeezerTitleSelected && catalogSelectedDeezerDuration ? catalogSelectedDeezerDuration : editingSong?.duration || ''">
                                <input type="hidden" name="source" :value="catalogDeezerTitleSelected ? 'deezer' : editingSong?.source || ''">
                                <fieldset><legend class="text-sm font-medium text-slate-700">Slots</legend><p class="mt-1 text-xs text-slate-500">You can change the slots on this song by selecting a different band template, or choosing slots manually. Slot coverage will be lost for any removed slots.</p><div class="mt-2 flex gap-4 text-sm"><label class="flex items-center gap-2 text-slate-700"><input type="radio" value="template" x-model="entryMode"> Band template</label><label class="flex items-center gap-2 text-slate-700"><input type="radio" value="manual" x-model="entryMode"> Choose manually</label></div></fieldset>
                                <div x-show="entryMode === 'template'"><x-input-label value="Band Template" /><x-select name="band_template_id" x-bind:disabled="entryMode !== 'template'" class="mt-1"><option value="">Choose a template</option>@foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</x-select></div>
                                <div x-show="entryMode === 'manual'" class="grid grid-cols-2 gap-2 sm:grid-cols-3">@foreach ($slotOptions as $slotValue => $slotLabel)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="slot_names[]" value="{{ $slotValue }}" :checked="editingSong?.slots.includes('{{ $slotValue }}')" :disabled="entryMode !== 'manual'" class="rounded border-slate-300 text-amber-600">{{ $slotLabel }}</label>@endforeach</div>
                                <div><x-input-label value="Notes" /><x-textarea-input name="notes" x-bind:value="editingSong?.notes ?? ''" @input="if (editingSong) { editingSong.notes = $event.target.value }" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm text-slate-900" /></div>
                                <div class="flex items-center justify-between gap-3"><x-danger-button type="button" @click="deleteCatalogSong()">Delete Song</x-danger-button><div class="flex gap-2"><x-modal-secondary-button type="button" @click="openEditSong = false; resetCatalogAutocomplete(); window.dispatchEvent(new CustomEvent('close-modal', { detail: 'catalog-song-edit' }))">Cancel</x-modal-secondary-button><x-modal-primary-button>Update Song</x-modal-primary-button></div></div>
                            </form>
                        </div>
                </x-modal>
            </template>
        @endif

        <template x-teleport="body">
            <x-modal name="catalog-quick-set" maxWidth="2xl" focusable>
                <div data-tour="jam-standards-quick-set-modal" class="flex max-h-[calc(100vh-3rem)] flex-col text-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4"><h3 class="text-lg font-semibold">Quick Set</h3></div>
                    <form data-tour="jam-standards-quick-set-form" method="POST" action="{{ route('jam-standards.quick-set.store') }}" @submit="if (! confirmQuickSetSubmission()) { $event.preventDefault() }" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
                            @csrf
                            <p class="text-sm font-semibold text-slate-700">Select the parts you want to take on for each song.</p>
                            <template x-for="song in selectedQuickSetSongs()" :key="song.id">
                                <section class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <input type="hidden" name="catalog_song_ids[]" :value="song.id">
                                    <p class="text-sm font-semibold" x-text="`${song.artist} - ${song.title}`"></p>
                                    <div class="mt-2 flex flex-wrap gap-2"><template x-for="slot in song.slots" :key="slot.name"><label :class="isQuickSetSlotDisabled(song.id, slot.name) ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' : ((quickSetAssignments[song.id] || []).includes(slot.name) ? 'cursor-pointer border-sky-300 bg-sky-50 text-sky-700' : 'cursor-pointer border-slate-200 bg-white text-slate-600 hover:border-slate-300')" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition"><input type="checkbox" :name="`song_slots[${song.id}][]`" :value="slot.name" :checked="(quickSetAssignments[song.id] || []).includes(slot.name)" :disabled="isQuickSetSlotDisabled(song.id, slot.name)" @change="toggleQuickSetAssignment(song.id, slot.name, $event.target.checked)" class="sr-only"><span x-show="(quickSetAssignments[song.id] || []).includes(slot.name)" x-cloak class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-sky-600 text-white"><x-heroicon-m-check class="h-3 w-3" aria-hidden="true" /></span><span x-text="slotLabel(slot.name)"></span></label></template></div>
                                </section>
                            </template>
                        <div class="space-y-4 border-t border-slate-200 pt-4">
                            <div class="grid gap-4 sm:grid-cols-2"><div><label for="catalog-quick-set-session" class="block text-sm font-medium text-slate-700">Jam Session <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">required</span></label><select id="catalog-quick-set-session" name="jam_session_id" x-model="quickSetJamSessionId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" required><option value="">Choose a session</option>@foreach ($sessions as $session)<option value="{{ $session->id }}" @disabled($session->is_closed && ! auth()->user()->is_admin)>{{ $session->name }} ({{ $session->date->format('M j, Y') }})</option>@endforeach</select></div><div><label for="catalog-quick-set-name" class="block text-sm font-medium text-slate-700">Set Name <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">required</span></label><x-text-input id="catalog-quick-set-name" name="set_name" x-model="quickSetName" class="mt-1 block w-full" required /></div></div>
                            <div class="flex flex-wrap gap-4"><label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_hidden" value="1" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500"><x-heroicon-m-eye-slash class="h-4 w-4 text-sky-500" aria-hidden="true" /> Hidden set</label><label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="free_for_all" value="1" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500"><x-heroicon-m-fire class="h-4 w-4 text-orange-500" aria-hidden="true" /> Free for all mode</label></div>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4"><x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'catalog-quick-set' }))">Cancel</x-modal-secondary-button><x-modal-primary-button data-tour="jam-standards-quick-set-submit" x-bind:disabled="!quickSetJamSessionId || !quickSetName.trim()" class="disabled:cursor-not-allowed disabled:opacity-40">Create Set</x-modal-primary-button></div>
                    </form>
                </div>
            </x-modal>
        </template>
    </div>
</x-app-layout>