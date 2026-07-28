@props(['set'])

<div x-show="openSongRequest" x-cloak x-transition.opacity.duration.150ms data-drag-blocking-modal data-modal-overlay class="fixed inset-0 z-40 bg-black/40" @click="openSongRequest = false; resetSongRequestAutocomplete()"></div>
<div x-show="openSongRequest" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-6 shadow-2xl" @click.stop>
        <h4 class="text-lg font-semibold text-slate-900">Request a Song for {{ $set->name }}</h4>
        <form method="POST" action="{{ route('song-requests.store', $set) }}" class="mt-4 space-y-4" @submit.prevent="submitSongRequest($event)">
            @csrf
            <p x-show="requestSongError" x-text="requestSongError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" x-cloak></p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="relative">
                    <x-input-label for="request_artist_{{ $set->id }}" value="Artist" />
                    <x-text-input
                        id="request_artist_{{ $set->id }}"
                        name="artist"
                        x-model="requestArtistQuery"
                        @input="queueRequestArtistLookup()"
                        @focus="showRequestArtistSuggestions = requestArtistSuggestions.length > 0"
                        @keydown.escape="showRequestArtistSuggestions = false"
                        class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                        autocomplete="off"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">Start typing an artist to fetch Deezer suggestions.</p>
                    <div x-show="requestArtistLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up artists...</div>
                    <div x-show="requestArtistLookupError" x-cloak x-text="requestArtistLookupError" class="mt-1 text-xs text-rose-600"></div>
                    <ul
                        x-show="showRequestArtistSuggestions"
                        x-cloak
                        class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                        @click.outside="showRequestArtistSuggestions = false"
                    >
                        <template x-for="artist in requestArtistSuggestions" :key="`request-artist-${artist}`">
                            <li>
                                <button
                                    type="button"
                                    @click="selectRequestArtistSuggestion(artist)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                    x-text="artist"
                                ></button>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="relative">
                    <x-input-label for="request_title_{{ $set->id }}" value="Title" />
                    <x-text-input
                        id="request_title_{{ $set->id }}"
                        name="title"
                        x-model="requestTitleQuery"
                        @input="queueRequestTitleLookup()"
                        @focus="showRequestTitleSuggestions = requestTitleSuggestions.length > 0"
                        @keydown.escape="showRequestTitleSuggestions = false"
                        class="mt-1 block w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-200"
                        autocomplete="off"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">Song suggestions are scoped to the selected artist.</p>
                    <div x-show="requestTitleLookupBusy" x-cloak class="mt-1 text-xs text-slate-500">Looking up songs...</div>
                    <div x-show="requestTitleLookupError" x-cloak x-text="requestTitleLookupError" class="mt-1 text-xs text-rose-600"></div>
                    <ul
                        x-show="showRequestTitleSuggestions"
                        x-cloak
                        class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                        @click.outside="showRequestTitleSuggestions = false"
                    >
                        <template x-for="track in requestTitleSuggestions" :key="`request-title-${track.title}`">
                            <li>
                                <button
                                    type="button"
                                    @click="selectRequestTitleSuggestion(track.title)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                    x-text="track.title"
                                ></button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
            <div>
                <x-input-label for="request_notes_{{ $set->id }}" value="Notes" />
                <x-textarea-input id="request_notes_{{ $set->id }}" name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" />
            </div>
            <div class="flex justify-end gap-3">
                <x-modal-secondary-button type="button" @click="openSongRequest = false; resetSongRequestAutocomplete()">Cancel</x-modal-secondary-button>
                <x-modal-primary-button x-bind:disabled="requestSongBusy">Send Request</x-modal-primary-button>
            </div>
        </form>
    </div>
</div>
