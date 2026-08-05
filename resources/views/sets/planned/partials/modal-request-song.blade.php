<x-modal name="planned-set-request-song" maxWidth="2xl" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">
            Request a Song for
            <span x-text="currentSongRequestSet()?.name || 'this set'"></span>
        </h3>

        <div class="mt-4 space-y-4">
            <p x-show="requestSongError" x-text="requestSongError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" x-cloak></p>

            <div class="space-y-2">
                <span class="block text-sm font-medium text-slate-700">Request source</span>
                <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" value="manual" x-model="requestSongMode" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                        Type manually
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" value="catalog" x-model="requestSongMode" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500">
                        Choose from catalog
                    </label>
                </div>
            </div>

            <div x-show="requestSongMode === 'catalog'" x-cloak>
                <x-input-label for="planned-set-request-catalog-song" value="Catalog song" />
                <x-select id="planned-set-request-catalog-song" x-model="requestCatalogSongId" @change="applyRequestCatalogSong()">
                    <option value="">Choose a song</option>
                    <template x-for="song in jamStandardSongs" :key="`planned-request-catalog-song-${song.id}`">
                        <option :value="String(song.id)" x-text="`${song.artist} - ${song.title}`"></option>
                    </template>
                </x-select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2" x-show="requestSongMode === 'manual'" x-cloak>
                <div class="relative">
                    <x-input-label for="planned-set-request-artist" value="Artist" />
                    <x-text-input
                        id="planned-set-request-artist"
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
                        <template x-for="artist in requestArtistSuggestions" :key="`planned-request-artist-${artist}`">
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
                    <x-input-label for="planned-set-request-title" value="Title" />
                    <x-text-input
                        id="planned-set-request-title"
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
                        <template x-for="track in requestTitleSuggestions" :key="`planned-request-title-${track.title}`">
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
                <x-input-label for="planned-set-request-notes" value="Notes" />
                <x-textarea-input id="planned-set-request-notes" x-model="requestSongNotes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 text-sm text-slate-900 transition focus:border-amber-500 focus:ring-2 focus:ring-amber-200" />
            </div>

            <div>
                <p class="text-sm font-medium text-slate-700">Slots I can cover for this song (optional)</p>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <template x-for="(slotLabel, slotValue) in slotOptions" :key="`planned-request-slot-${slotValue}`">
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                :checked="requestSongSlotNames.includes(slotValue)"
                                @change="toggleRequestSongSlotName(slotValue)"
                                class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500"
                            >
                            <span x-text="slotLabel"></span>
                        </label>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-modal-secondary-button type="button" @click="closeSongRequestModal()">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="submitSongRequest()" x-bind:disabled="requestSongBusy" x-text="requestSongBusy ? 'Sending...' : 'Send Request'"></x-modal-primary-button>
        </div>
    </div>
</x-modal>
