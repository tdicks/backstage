<x-modal name="planned-set-edit-song" maxWidth="xl" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">Edit Song</h3>
        <div class="mt-4 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="relative">
                    <x-input-label for="planned-set-edit-song-artist" value="Artist" />
                    <x-text-input
                        id="planned-set-edit-song-artist"
                        x-model="songEditArtistQuery"
                        @input="queueSongEditArtistLookup()"
                        @focus="showSongEditArtistSuggestions = songEditArtistSuggestions.length > 0"
                        @keydown.escape.stop="showSongEditArtistSuggestions = false"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        placeholder="Start typing an artist..."
                    />
                    <ul
                        x-show="showSongEditArtistSuggestions && songEditArtistSuggestions.length > 0"
                        x-cloak
                        class="absolute z-30 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                        @click.outside="showSongEditArtistSuggestions = false"
                    >
                        <template x-for="artist in songEditArtistSuggestions" :key="`planned-edit-artist-${artist}`">
                            <li>
                                <button
                                    type="button"
                                    @click="selectSongEditArtistSuggestion(artist)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                    x-text="artist"
                                ></button>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-1 text-xs text-slate-500" x-show="songEditArtistLookupBusy">Looking up artists...</p>
                    <p class="mt-1 text-xs text-rose-600" x-show="songEditArtistLookupError" x-text="songEditArtistLookupError"></p>
                </div>
                <div class="relative">
                    <x-input-label for="planned-set-edit-song-title" value="Title" />
                    <x-text-input
                        id="planned-set-edit-song-title"
                        x-model="songEditTitleQuery"
                        @input="queueSongEditTitleLookup()"
                        @focus="showSongEditTitleSuggestions = songEditTitleSuggestions.length > 0"
                        @keydown.escape.stop="showSongEditTitleSuggestions = false"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        placeholder="Start typing a song title..."
                    />
                    <ul
                        x-show="showSongEditTitleSuggestions && songEditTitleSuggestions.length > 0"
                        x-cloak
                        class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                        @click.outside="showSongEditTitleSuggestions = false"
                    >
                        <template x-for="suggestion in songEditTitleSuggestions" :key="`planned-edit-title-${suggestion.title}`">
                            <li>
                                <button
                                    type="button"
                                    @click="selectSongEditTitleSuggestion(suggestion.title, suggestion.duration)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                >
                                    <span class="block font-medium" x-text="suggestion.title"></span>
                                    <span class="block text-xs text-slate-500" x-text="suggestion.album || ''"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-1 text-xs text-slate-500" x-show="songEditTitleLookupBusy">Looking up songs...</p>
                    <p class="mt-1 text-xs text-rose-600" x-show="songEditTitleLookupError" x-text="songEditTitleLookupError"></p>
                </div>
            </div>

            <div>
                <x-input-label for="planned-set-edit-song-notes" value="Notes" />
                <x-textarea-input id="planned-set-edit-song-notes" x-model="songEditEditor.notes" rows="3" class="mt-1 w-full" />
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-edit-song' }))">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="saveSongEdit()" x-bind:disabled="songEditBusy" x-text="songEditBusy ? 'Saving...' : 'Save'"></x-modal-primary-button>
        </div>
    </div>
</x-modal>
