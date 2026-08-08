<x-modal name="planned-set-add-song" maxWidth="2xl" focusable>
    <div class="p-6 text-slate-900">
        <h3 class="text-lg font-semibold">Add Song</h3>
        <p class="mt-1 text-sm text-slate-600">Add a song to this planned set and optionally add slots now.</p>

        <div class="mt-4 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="relative">
                    <x-input-label for="planned-set-song-artist" value="Artist" />
                    <x-text-input
                        id="planned-set-song-artist"
                        x-model="songArtistQuery"
                        @input="queueSongArtistLookup()"
                        @focus="showSongArtistSuggestions = songArtistSuggestions.length > 0"
                        @keydown.escape.stop="showSongArtistSuggestions = false"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        placeholder="Start typing an artist..."
                    />
                    <ul
                        x-show="showSongArtistSuggestions && songArtistSuggestions.length > 0"
                        x-cloak
                        class="absolute z-30 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                        @click.outside="showSongArtistSuggestions = false"
                    >
                        <template x-for="artist in songArtistSuggestions" :key="`planned-artist-${artist}`">
                            <li>
                                <button
                                    type="button"
                                    @click="selectSongArtistSuggestion(artist)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                    x-text="artist"
                                ></button>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-1 text-xs text-slate-500" x-show="songArtistLookupBusy">Looking up artists...</p>
                    <p class="mt-1 text-xs text-rose-600" x-show="songArtistLookupError" x-text="songArtistLookupError"></p>
                </div>
                <div class="relative">
                    <x-input-label for="planned-set-song-title" value="Title" />
                    <x-text-input
                        id="planned-set-song-title"
                        x-model="songTitleQuery"
                        @input="queueSongTitleLookup()"
                        @focus="showSongTitleSuggestions = songTitleSuggestions.length > 0"
                        @keydown.escape.stop="showSongTitleSuggestions = false"
                        class="mt-1 block w-full"
                        autocomplete="off"
                        placeholder="Start typing a song title..."
                    />
                    <ul
                        x-show="showSongTitleSuggestions && songTitleSuggestions.length > 0"
                        x-cloak
                        class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                        @click.outside="showSongTitleSuggestions = false"
                    >
                        <template x-for="suggestion in songTitleSuggestions" :key="`planned-title-${suggestion.title}`">
                            <li>
                                <button
                                    type="button"
                                    @click="selectSongTitleSuggestion(suggestion.title, suggestion.duration)"
                                    class="w-full px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                >
                                    <span class="block font-medium" x-text="suggestion.title"></span>
                                    <span class="block text-xs text-slate-500" x-text="suggestion.album || ''"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-1 text-xs text-slate-500" x-show="songTitleLookupBusy">Looking up songs...</p>
                    <p class="mt-1 text-xs text-rose-600" x-show="songTitleLookupError" x-text="songTitleLookupError"></p>
                </div>
            </div>

            <div>
                <x-input-label for="planned-set-song-notes" value="Notes" />
                <x-textarea-input id="planned-set-song-notes" x-model="songEditor.notes" rows="3" class="mt-1 w-full" />
            </div>

            <div class="space-y-2">
                <span class="block text-sm font-medium text-slate-700">Add slots by</span>
                <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" value="template" x-model="songEditor.song_slot_addition_mode" class="border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Band template
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" value="manual" x-model="songEditor.song_slot_addition_mode" class="border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Choose slots manually
                    </label>
                </div>
            </div>

            <div x-show="songEditor.song_slot_addition_mode === 'template'" x-cloak>
                <x-input-label for="planned-set-song-template" value="Band Template" />
                <x-select id="planned-set-song-template" x-model="songEditor.band_template_id" class="focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Choose a band template</option>
                    <template x-for="template in templateOptions" :key="`planned-template-${template.id}`">
                        <option :value="String(template.id)" x-text="template.name"></option>
                    </template>
                </x-select>
            </div>

            <div x-show="songEditor.song_slot_addition_mode === 'manual'" x-cloak>
                <p class="text-sm font-medium text-slate-700">Choose slots manually</p>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <template x-for="(slotLabel, slotKey) in slotOptions" :key="`planned-slot-option-${slotKey}`">
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                :checked="songEditor.slot_names.includes(slotKey)"
                                @change="toggleSongSlotName(slotKey)"
                            >
                            <span x-text="slotLabel"></span>
                        </label>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-modal-secondary-button type="button" @click="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planned-set-add-song' }))">Cancel</x-modal-secondary-button>
            <x-modal-primary-button type="button" @click="saveSong()" x-bind:disabled="songBusy" x-text="songBusy ? 'Adding...' : 'Add Song'"></x-modal-primary-button>
        </div>
    </div>
</x-modal>
